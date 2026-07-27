<?php

declare(strict_types=1);

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\EntryLine;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Ledger\Services\VoidTransaction;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Models\AuditLog;
use App\Support\Money;
use Illuminate\Support\Str;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

it('mengurangi saldo saat mencatat pengeluaran', function (): void {
    catatPengeluaran(50_000, $this->kas, 'Bensin');

    expect($this->kas->fresh()->balance()->minor)->toBe(95_000_000)
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('menambah saldo saat mencatat pemasukan', function (): void {
    catatPemasukan(250_000, $this->kas, 'Iuran bulanan');

    expect($this->kas->fresh()->balance()->format())->toBe('Rp 1.250.000');
});

it('memindahkan uang tanpa mengubah jumlah totalnya', function (): void {
    $bca = buatAkun('BCA', 0);

    catatPindah(300_000, $this->kas, $bca);

    expect($this->kas->fresh()->balance()->format())->toBe('Rp 700.000')
        ->and($bca->fresh()->balance()->format())->toBe('Rp 300.000');

    $total = $this->kas->fresh()->balance()->plus($bca->fresh()->balance());
    expect($total->format())->toBe('Rp 1.000.000');
});

it('membuat setiap transaksi dengan dua sisi yang berjumlah nol', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);

    expect($transaksi->entries)->toHaveCount(2)
        ->and($transaksi->entries->sum('amount_minor'))->toBe(0);
});

it('memakai akun sistem sebagai sisi lawan, dan menyembunyikannya dari daftar akun', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $beban = Account::query()->where('type', AccountType::Expense)->sole();

    expect($beban->is_system)->toBeTrue()
        ->and($beban->name)->toBe('Beban')
        ->and(Account::query()->milikPengguna()->pluck('name')->all())->toBe(['Kas']);
});

it('tidak menggandakan transaksi kalau ULID yang sama dikirim ulang', function (): void {
    // Inilah yang membuat antrean offline PWA aman: ponsel yang kehilangan
    // sinyal setelah server menerima kiriman akan mencoba lagi (aturan A9).
    $id = (string) Str::ulid();

    $pertama = catatPengeluaran(50_000, $this->kas, 'Bensin', id: $id);
    $kedua = catatPengeluaran(50_000, $this->kas, 'Bensin', id: $id);

    expect($pertama->getKey())->toBe($kedua->getKey())
        ->and(Transaction::query()->bukanSaldoAwal()->count())->toBe(1)
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('menolak draft yang tidak seimbang sebelum menyentuh database', function (): void {
    DraftTransaction::mentah(
        kind: TransactionKind::Adjustment,
        lines: [new EntryLine($this->kas->getKey(), 100)],
    );
})->throws(InvalidArgumentException::class, 'dua sisi');

it('menolak pindah ke akun yang sama', function (): void {
    DraftTransaction::pindah(Money::ofMajor(1_000, 'IDR'), $this->kas, $this->kas);
})->throws(InvalidArgumentException::class, 'harus berbeda');

it('membalik transaksi dan mengembalikan saldo', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas, 'Salah catat');
    expect($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');

    $pembalik = app(VoidTransaction::class)($transaksi, 'Salah akun');

    expect($this->kas->fresh()->balance()->format())->toBe('Rp 1.000.000')
        ->and($transaksi->fresh()->status)->toBe(TransactionStatus::Void)
        ->and($pembalik->reverses_transaction_id)->toBe($transaksi->getKey())
        ->and($pembalik->status)->toBe(TransactionStatus::Posted);
});

it('menyimpan transaksi yang dibatalkan, tidak menghapusnya', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas, 'Salah catat');

    app(VoidTransaction::class)($transaksi);

    // Buku kas yang bisa dihapus adalah buku kas yang tidak bisa dipercaya.
    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(2)
        ->and(Transaction::query()->find($transaksi->getKey()))->not->toBeNull();
});

it('menolak membalik transaksi yang sudah dibatalkan', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);
    app(VoidTransaction::class)($transaksi);

    app(VoidTransaction::class)($transaksi->fresh());
})->throws(RuntimeException::class, 'sudah dibatalkan');

it('menolak membalik draft', function (): void {
    $draft = Transaction::factory()->create();

    app(VoidTransaction::class)($draft);
})->throws(RuntimeException::class, 'Draft cukup dihapus');

it('tidak menghitung transaksi draft maupun void ke dalam saldo', function (): void {
    catatPengeluaran(50_000, $this->kas);
    $dibatalkan = catatPengeluaran(200_000, $this->kas);
    app(VoidTransaction::class)($dibatalkan);

    // 1.000.000 - 50.000 - 200.000 + 200.000 (pembalik) = 950.000
    expect($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('mencatat saldo awal sebagai transaksi sungguhan, bukan angka yatim', function (): void {
    $bca = buatAkun('BCA', 2_500_000);

    // Saldo awal punya sisi lawan di modal. Tanpa itu, neraca menunjukkan harta
    // yang tidak berasal dari mana pun.
    $pembukaan = Transaction::query()
        ->where('kind', TransactionKind::Opening)
        ->where('description', 'Saldo awal BCA')
        ->sole();

    expect($bca->fresh()->balance()->format())->toBe('Rp 2.500.000')
        ->and($pembukaan->entries)->toHaveCount(2)
        ->and($pembukaan->entries->sum('amount_minor'))->toBe(0)
        ->and($pembukaan->description)->toBe('Saldo awal BCA');

    catatPengeluaran(500_000, $bca);

    expect($bca->fresh()->balance()->format())->toBe('Rp 2.000.000');
});

it('mencatat siklus hidup transaksi di audit_logs', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas, 'Bensin');

    $tindakan = AuditLog::query()
        ->where('auditable_id', $transaksi->getKey())
        ->orderBy('created_at')
        ->orderBy('id')
        ->pluck('action')
        ->all();

    expect($tindakan)->toBe([AuditAction::TransactionCreated, AuditAction::TransactionPosted]);
});

it('menyimpan nominal di audit_logs sebagai angka, bukan teks terformat', function (): void {
    $akun = buatAkun('Dompet', 100_000);

    $baris = AuditLog::query()
        ->where('auditable_id', $akun->getKey())
        ->where('action', AuditAction::AccountCreated)
        ->sole();

    // audit_logs BOLEH memuat nominal — ia milik workspace dan tidak pernah
    // bisa dibaca admin platform (kebalikan dari security_events, aturan A6).
    // Disimpan sebagai minor unit apa adanya, bukan sebagai "Rp 100.000":
    // riwayat harus bisa dibandingkan angka-per-angka bertahun-tahun kemudian.
    expect($baris->after['opening_balance_minor'])->toBe(10_000_000);
});

it('tidak mencatat akun sistem ke audit_logs', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $sistem = Account::query()->where('is_system', true)->pluck('id');

    expect($sistem)->not->toBeEmpty()
        ->and(AuditLog::query()->whereIn('auditable_id', $sistem)->count())->toBe(0);
});

it('menghitung nominal transaksi dari sisi debitnya', function (): void {
    $transaksi = catatPengeluaran(75_000, $this->kas);

    expect($transaksi->load('entries')->amount()->format())->toBe('Rp 75.000');
});

it('memakai tanggal buku terpisah dari waktu pencatatan', function (): void {
    // Aturan A10: booked_date DATE di zona waktu pengguna, created_at UTC.
    $transaksi = catatPengeluaran(50_000, $this->kas, 'Kemarin', '2026-07-01');

    expect($transaksi->booked_date->toDateString())->toBe('2026-07-01')
        ->and($transaksi->created_at->toDateString())->not->toBe('2026-07-01');
});

it('menyimpan sumber transaksi supaya jalur masuknya bisa ditelusuri', function (): void {
    $transaksi = app(PostTransaction::class)(DraftTransaction::pengeluaran(
        amount: Money::ofMajor(50_000, 'IDR'),
        from: $this->kas,
        source: TransactionSource::Telegram,
        rawInput: '50k bensin',
    ));

    expect($transaksi->source)->toBe(TransactionSource::Telegram)
        ->and($transaksi->raw_input)->toBe('50k bensin');
});
