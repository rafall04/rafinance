<?php

declare(strict_types=1);

use App\Domain\Budgeting\Models\RecurringRule;
use App\Domain\Budgeting\Services\RunRecurringRules;
use App\Domain\Capture\BankNotificationParser;
use App\Domain\Capture\RuleBasedParser;
use App\Domain\Capture\Services\ImportCsv;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Contacts\Models\Invoice;
use App\Domain\Contacts\Models\InvoiceItem;
use App\Domain\Contacts\Services\RecordInvoicePayment;
use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Reconciliation\Models\Reconciliation;
use App\Domain\Reconciliation\Services\PerformCashCount;
use App\Livewire\App\Tagihan;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

/*
|--------------------------------------------------------------------------
| Tagihan dan piutang
|--------------------------------------------------------------------------
*/

it('mencatat pembayaran tagihan sekaligus memasukkannya ke buku besar', function (): void {
    $tagihan = Invoice::factory()->create(['total_minor' => 100_000_000]); // Rp 1.000.000

    app(RecordInvoicePayment::class)($tagihan, Money::ofMajor(1_000_000, 'IDR'), $this->kas);

    // Piutang yang lunas tanpa uang masuk ke akun mana pun akan membuat neraca
    // berbohong tanpa ada yang tahu.
    expect($tagihan->fresh()->status)->toBe('paid')
        ->and($tagihan->fresh()->sisa()->isZero())->toBeTrue()
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 2.000.000')
        ->and($tagihan->fresh()->payments()->sole()->transaction_id)->not->toBeNull();
});

it('menandai tagihan dibayar sebagian', function (): void {
    $tagihan = Invoice::factory()->create(['total_minor' => 100_000_000]);

    app(RecordInvoicePayment::class)($tagihan, Money::ofMajor(400_000, 'IDR'), $this->kas);

    expect($tagihan->fresh()->status)->toBe('partial')
        ->and($tagihan->fresh()->sisa()->format())->toBe('Rp 600.000');
});

it('menolak pembayaran melebihi sisa tagihan', function (): void {
    $tagihan = Invoice::factory()->create(['total_minor' => 100_000_000]);

    app(RecordInvoicePayment::class)($tagihan, Money::ofMajor(2_000_000, 'IDR'), $this->kas);
})->throws(InvalidArgumentException::class, 'melebihi sisa tagihan');

it('mengelompokkan piutang berdasarkan umurnya', function (): void {
    Invoice::factory()->jatuhTempo(10)->create(['total_minor' => 10_000_000]);
    Invoice::factory()->jatuhTempo(45)->create(['total_minor' => 20_000_000]);
    Invoice::factory()->jatuhTempo(120)->create(['total_minor' => 30_000_000]);

    $umur = Invoice::query()->belumLunas()->get()
        ->groupBy(fn (Invoice $satu): string => $satu->kelompokUmur());

    expect($umur->keys()->all())->toContain('1-30 hari', '31-60 hari', 'Lebih dari 90 hari');
});

it('menghitung subtotal baris tagihan tanpa float', function (): void {
    $tagihan = Invoice::factory()->create();

    // 2,5 jam × Rp 200.000 = Rp 500.000. Kuantitas disimpan per-seribu supaya
    // tetap bilangan bulat, dengan alasan yang sama seperti aturan A1.
    $baris = InvoiceItem::factory()->create([
        'invoice_id' => $tagihan->getKey(),
        'qty_milli' => 2_500,
        'unit_price_minor' => 20_000_000,
    ]);

    expect($baris->subtotal()->format())->toBe('Rp 500.000')
        ->and($baris->qty())->toBe('2,5');
});

it('membuat tagihan dari halaman tagihan', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Tagihan::class)
        ->call('bukaFormulir')
        ->set('namaKontak', 'Pak Budi')
        ->set('jumlah', '1.500.000')
        ->call('simpan')
        ->assertHasNoErrors();

    $tagihan = Invoice::query()->sole();

    expect($tagihan->total_minor->format())->toBe('Rp 1.500.000')
        ->and($tagihan->contact->name)->toBe('Pak Budi')
        ->and(Contact::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Cash opname
|--------------------------------------------------------------------------
*/

it('mencatat selisih cash opname sebagai penyesuaian', function (): void {
    // Buku bilang Rp 1.000.000, tapi di laci cuma ada Rp 965.000.
    $hasil = app(PerformCashCount::class)($this->kas, Money::ofMajor(965_000, 'IDR'));

    expect($hasil->difference_minor->formatPlain())->toBe('-35.000')
        ->and($hasil->cocok())->toBeFalse()
        ->and($hasil->adjustment_transaction_id)->not->toBeNull()
        // Setelah penyesuaian, buku cocok dengan kenyataan.
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 965.000');
});

it('tidak membuat penyesuaian kalau hitungan sudah cocok', function (): void {
    $hasil = app(PerformCashCount::class)($this->kas, Money::ofMajor(1_000_000, 'IDR'));

    expect($hasil->cocok())->toBeTrue()
        ->and($hasil->adjustment_transaction_id)->toBeNull()
        ->and(Reconciliation::query()->count())->toBe(1);
});

it('memunculkan selisih cash opname di laporan, bukan menyembunyikannya', function (): void {
    app(PerformCashCount::class)($this->kas, Money::ofMajor(965_000, 'IDR'));

    $penyesuaian = Transaction::query()->where('kind', TransactionKind::Adjustment)->sole();

    // Selisih yang tidak bisa dijelaskan adalah informasi, bukan aib.
    expect($penyesuaian->description)->toContain('cash opname')
        ->and($penyesuaian->entries->sum('amount_minor'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Aturan berulang
|--------------------------------------------------------------------------
*/

it('menjalankan aturan berulang yang jatuh tempo', function (): void {
    RecurringRule::factory()->jatuhTempo()->create([
        'label' => 'Sewa ruko',
        'template' => [
            'kind' => 'expense',
            'amount_minor' => 20_000_000,
            'account_id' => $this->kas->getKey(),
        ],
    ]);

    $hasil = app(RunRecurringRules::class)();

    expect($hasil['dijalankan'])->toBe(1)
        ->and($hasil['gagal'])->toBe(0)
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 800.000');

    $transaksi = Transaction::query()->where('description', 'Sewa ruko')->sole();
    expect($transaksi->source->value)->toBe('recurring');
});

it('menjadwalkan jalannya berikutnya setelah dijalankan', function (): void {
    $aturan = RecurringRule::factory()->jatuhTempo()->create([
        'template' => ['kind' => 'expense', 'amount_minor' => 10_000_000, 'account_id' => $this->kas->getKey()],
    ]);

    app(RunRecurringRules::class)();

    expect($aturan->fresh()->next_run_at->isFuture())->toBeTrue()
        ->and($aturan->fresh()->last_run_at)->not->toBeNull();
});

it('tidak menjalankan aturan yang belum jatuh tempo', function (): void {
    RecurringRule::factory()->create([
        'next_run_at' => now()->addMonth(),
        'template' => ['kind' => 'expense', 'amount_minor' => 10_000_000, 'account_id' => $this->kas->getKey()],
    ]);

    expect(app(RunRecurringRules::class)()['dijalankan'])->toBe(0);
});

it('melanjutkan aturan lain saat satu aturan rusak', function (): void {
    // Satu aturan yang rusak tidak boleh membuat sewa dan gaji bulan itu tidak
    // tercatat sama sekali.
    RecurringRule::factory()->jatuhTempo()->create([
        'label' => 'Rusak',
        'template' => ['kind' => 'expense', 'amount_minor' => 0, 'account_id' => $this->kas->getKey()],
    ]);

    RecurringRule::factory()->jatuhTempo()->create([
        'label' => 'Sehat',
        'template' => ['kind' => 'expense', 'amount_minor' => 15_000_000, 'account_id' => $this->kas->getKey()],
    ]);

    $hasil = app(RunRecurringRules::class)();

    expect($hasil['dijalankan'])->toBe(1)
        ->and($hasil['gagal'])->toBe(1)
        ->and(Transaction::query()->where('description', 'Sehat')->exists())->toBeTrue();
});

it('membatasi tanggal berulang di atas 28', function (): void {
    $aturan = RecurringRule::factory()->create(['day_of_period' => 31, 'frequency' => 'monthly']);

    // Aturan tanggal 31 akan melompat antara 28 Februari dan 31 Maret; orang
    // yang menyetelnya untuk "tanggal gajian" tidak mengharapkan itu.
    $berikutnya = $aturan->hitungBerikutnya(CarbonImmutable::parse('2026-01-15'));

    expect($berikutnya->day)->toBe(28);
});

/*
|--------------------------------------------------------------------------
| Impor CSV
|--------------------------------------------------------------------------
*/

it('mengimpor transaksi dari CSV', function (): void {
    $csv = <<<'CSV'
        tanggal,keterangan,nominal,jenis
        2026-07-01,Bensin,50000,keluar
        2026-07-02,Bayaran proyek,2500000,masuk
        CSV;

    $hasil = app(ImportCsv::class)($csv, $this->kas);

    expect($hasil['berhasil'])->toBe(2)
        ->and($hasil['gagal'])->toBeEmpty()
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 3.450.000');
});

it('melewati baris rusak tanpa menghentikan impor', function (): void {
    // Berkas ekspor bank hampir selalu punya beberapa baris aneh. Impor yang
    // berhenti di baris ke-2 dari 4 memaksa orang membersihkannya manual.
    $csv = <<<'CSV'
        tanggal,keterangan,nominal
        2026-07-01,Bensin,-50000
        ,Baris rusak,
        2026-07-03,Kopi,-30000
        CSV;

    $hasil = app(ImportCsv::class)($csv, $this->kas);

    expect($hasil['berhasil'])->toBe(2)
        ->and($hasil['gagal'])->toHaveCount(1)
        ->and($hasil['gagal'][0]['baris'])->toBe(3);
});

it('memahami CSV bertitik koma dari Excel Indonesia', function (): void {
    $csv = "tanggal;keterangan;nominal\n2026-07-01;Listrik;-150000";

    expect(app(ImportCsv::class)($csv, $this->kas)['berhasil'])->toBe(1);
});

it('menentukan arah dari tanda nominal kalau kolom jenis tidak ada', function (): void {
    $csv = "tanggal,keterangan,nominal\n2026-07-01,Keluar,-100000\n2026-07-02,Masuk,250000";

    app(ImportCsv::class)($csv, $this->kas);

    expect($this->kas->fresh()->balance()->format())->toBe('Rp 1.150.000');
});

/*
|--------------------------------------------------------------------------
| Notifikasi bank yang diteruskan
|--------------------------------------------------------------------------
*/

it('membaca notifikasi transfer BCA', function (): void {
    $bca = buatAkun('BCA', 0, AccountSubtype::Bank);

    $draft = app(RuleBasedParser::class)('BCA: Transfer Rp 500.000 ke BUDI SANTOSO berhasil pada 23/07');

    expect($draft->amount->format())->toBe('Rp 500.000')
        ->and($draft->kind)->toBe(TransactionKind::Expense)
        ->and($draft->accountId)->toBe($bca->getKey())
        ->and($draft->description)->toContain('BCA');
});

it('mengenali uang masuk dari notifikasi bank', function (): void {
    buatAkun('BCA', 0, AccountSubtype::Bank);

    $draft = app(RuleBasedParser::class)('BCA: Dana masuk Rp 2.500.000 diterima dari PT MAJU');

    expect($draft->kind)->toBe(TransactionKind::Income)
        ->and($draft->amount->format())->toBe('Rp 2.500.000');
});

it('mengambil nominal transaksi, bukan saldo akhir', function (): void {
    buatAkun('GoPay', 0, AccountSubtype::Ewallet);

    // Notifikasi sering memuat saldo di baris berikutnya, dan saldo hampir
    // selalu lebih besar dari nominal transaksinya.
    $hasil = app(BankNotificationParser::class)('GoPay: Pembayaran Rp 25.000 berhasil. Saldo Rp 1.200.000');

    expect($hasil['nominal']->format())->toBe('Rp 25.000');
});

it('mengenali beberapa penyedia', function (string $teks, string $penyedia): void {
    expect(app(BankNotificationParser::class)($teks)['penyedia'])->toBe($penyedia);
})->with([
    ['BCA: Transfer Rp 100.000 berhasil', 'BCA'],
    ['Livin Mandiri: Pembayaran Rp 250.000 berhasil', 'Mandiri'],
    ['BRImo: Transfer Rp 75.000 berhasil', 'BRI'],
    ['OVO: Pembayaran Rp 30.000 berhasil', 'OVO'],
    ['DANA: Transfer Rp 45.000 berhasil', 'DANA'],
    ['ShopeePay: Pembayaran Rp 15.000 berhasil', 'ShopeePay'],
]);

it('mencatat kalau akun untuk penyedianya belum ada', function (): void {
    // Tidak ditolak — hanya diberi catatan, dan tetap bisa dilengkapi di inbox.
    $draft = app(RuleBasedParser::class)('SeaBank: Transfer Rp 100.000 berhasil');

    expect($draft->accountId)->toBeNull()
        ->and($draft->catatan)->toContain('Belum ada akun bernama SeaBank.')
        ->and($draft->amount->format())->toBe('Rp 100.000');
});

it('tidak menyalahartikan teks biasa sebagai notifikasi bank', function (): void {
    $draft = app(RuleBasedParser::class)('50k bensin');

    expect($draft->description)->toBe('Bensin')
        ->and($draft->amount->format())->toBe('Rp 50.000');
});
