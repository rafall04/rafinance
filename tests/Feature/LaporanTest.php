<?php

declare(strict_types=1);

use App\Domain\Budgeting\Models\Budget;
use App\Domain\Budgeting\Services\BudgetProgress;
use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Ledger\Services\Reports;
use App\Domain\Ledger\Services\VoidTransaction;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Domain\Projects\Models\Project;
use App\Livewire\App\Anggaran;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
    $this->transportasi = Category::query()->create(['name' => 'Transportasi', 'kind' => CategoryKind::Expense]);
    $this->makan = Category::query()->create(['name' => 'Makan', 'kind' => CategoryKind::Expense]);

    $this->laporan = app(Reports::class);
    $this->dari = CarbonImmutable::parse('2026-07-01');
    $this->sampai = CarbonImmutable::parse('2026-07-31');
});

function catatBerkategori(int $rupiah, $akun, $kategori, string $tanggal): void
{
    app(PostTransaction::class)(
        DraftTransaction::pengeluaran(
            amount: Money::ofMajor($rupiah, 'IDR'),
            from: $akun,
            bookedDate: $tanggal,
            categoryId: $kategori->getKey(),
        ),
    );
}

it('menghitung arus kas harian', function (): void {
    catatPengeluaran(100_000, $this->kas, 'Bensin', '2026-07-05');
    catatPemasukan(500_000, $this->kas, 'Bayaran', '2026-07-05');
    catatPengeluaran(50_000, $this->kas, 'Kopi', '2026-07-06');

    $arus = $this->laporan->arusKas($this->dari, $this->sampai)->keyBy('periode');

    expect($arus['2026-07-05']->masuk->format())->toBe('Rp 500.000')
        ->and($arus['2026-07-05']->keluar->format())->toBe('Rp 100.000')
        ->and($arus['2026-07-05']->net->format())->toBe('Rp 400.000')
        ->and($arus['2026-07-06']->net->formatPlain())->toBe('-50.000')
        // Saldo awal Kas juga muncul, bertanggal hari akun dibuat.
        ->and($arus[now()->toDateString()]->masuk->format())->toBe('Rp 1.000.000');
});

it('mengelompokkan pengeluaran per kategori dari yang terbesar', function (): void {
    catatBerkategori(300_000, $this->kas, $this->transportasi, '2026-07-05');
    catatBerkategori(100_000, $this->kas, $this->makan, '2026-07-06');
    catatBerkategori(50_000, $this->kas, $this->transportasi, '2026-07-07');

    $per = $this->laporan->perKategori($this->dari, $this->sampai);

    expect($per->first()->nama)->toBe('Transportasi')
        ->and($per->first()->total->format())->toBe('Rp 350.000')
        ->and($per->first()->jumlah)->toBe(2)
        ->and($per->last()->nama)->toBe('Makan');
});

it('menempatkan transaksi tanpa kategori di keranjang sendiri', function (): void {
    catatPengeluaran(75_000, $this->kas, 'Entah apa', '2026-07-05');

    $per = $this->laporan->perKategori($this->dari, $this->sampai);

    expect($per->first()->nama)->toBe('Belum dikategorikan')
        ->and($per->first()->id)->toBeNull();
});

it('meringkas per akun', function (): void {
    $bca = buatAkun('BCA', 0, AccountSubtype::Bank);

    catatPengeluaran(100_000, $this->kas, 'Bensin', '2026-07-05');
    catatPengeluaran(400_000, $bca, 'Listrik', '2026-07-06');

    $per = $this->laporan->perDimensi('account', $this->dari, $this->sampai)->keyBy('nama');

    expect($per['Kas']->keluar->format())->toBe('Rp 100.000')
        ->and($per['BCA']->keluar->format())->toBe('Rp 400.000');
});

it('meringkas per proyek', function (): void {
    $proyek = Project::query()->create(['name' => 'Event Kantor', 'status' => 'active']);

    app(PostTransaction::class)(
        DraftTransaction::pengeluaran(
            amount: Money::ofMajor(2_000_000, 'IDR'),
            from: $this->kas,
            bookedDate: '2026-07-05',
            projectId: $proyek->getKey(),
        ),
    );

    app(PostTransaction::class)(
        DraftTransaction::pemasukan(
            amount: Money::ofMajor(5_000_000, 'IDR'),
            to: $this->kas,
            bookedDate: '2026-07-10',
            projectId: $proyek->getKey(),
        ),
    );

    $per = $this->laporan->perDimensi('project', $this->dari, $this->sampai)->keyBy('id');

    expect($per[$proyek->getKey()]->net->format())->toBe('Rp 3.000.000');
});

it('menghitung laba rugi', function (): void {
    catatPemasukan(5_000_000, $this->kas, 'Penjualan', '2026-07-05');
    catatPengeluaran(2_000_000, $this->kas, 'Bahan', '2026-07-06');

    $laba = $this->laporan->labaRugi($this->dari, $this->sampai);

    expect($laba->pendapatan->format())->toBe('Rp 5.000.000')
        ->and($laba->beban->format())->toBe('Rp 2.000.000')
        ->and($laba->laba->format())->toBe('Rp 3.000.000');
});

it('menghasilkan neraca yang seimbang', function (): void {
    catatPemasukan(5_000_000, $this->kas, 'Penjualan', '2026-07-05');
    catatPengeluaran(2_000_000, $this->kas, 'Bahan', '2026-07-06');

    $neraca = $this->laporan->neraca($this->sampai);

    // Harta = Utang + Modal. Kalau ini pernah gagal, ada entries yang lolos
    // tanpa melewati trigger keseimbangan (aturan A2).
    expect($neraca->seimbang)->toBeTrue()
        ->and($neraca->harta->format())->toBe('Rp 4.000.000')
        ->and($neraca->modal->format())->toBe('Rp 4.000.000');
});

it('membandingkan dengan periode sebelumnya yang sama panjang', function (): void {
    catatPemasukan(1_000_000, $this->kas, 'Juni', '2026-06-15');
    catatPemasukan(3_000_000, $this->kas, 'Juli', '2026-07-15');

    $banding = $this->laporan->banding($this->dari, $this->sampai);

    expect($banding->sekarang->laba->format())->toBe('Rp 3.000.000')
        ->and($banding->sebelumnya->laba->format())->toBe('Rp 1.000.000')
        ->and($banding->selisih->format())->toBe('Rp 2.000.000');
});

it('tidak menghitung transaksi draft ke dalam laporan', function (): void {
    Transaction::factory()->create([
        'booked_date' => '2026-07-10',
        'category_id' => $this->transportasi->getKey(),
    ]);

    expect($this->laporan->labaRugi($this->dari, $this->sampai)->beban->isZero())->toBeTrue();
});

it('menampilkan halaman laporan', function (): void {
    catatBerkategori(150_000, $this->kas, $this->transportasi, now()->toDateString());

    $this->actingAs($this->pengguna)->get('/app/laporan')
        ->assertOk()
        ->assertSee('Transportasi')
        ->assertSee('Pengeluaran')
        ->assertSee('Neraca per');
});

/*
|--------------------------------------------------------------------------
| Anggaran
|--------------------------------------------------------------------------
*/

it('menghitung sisa anggaran dari entries, bukan dari kolom cache', function (): void {
    Budget::factory()->create([
        'category_id' => $this->transportasi->getKey(),
        'amount_minor' => 50_000_000, // Rp 500.000
    ]);

    catatBerkategori(200_000, $this->kas, $this->transportasi, now()->toDateString());

    $kemajuan = app(BudgetProgress::class)->untukTanggal()->first();

    expect($kemajuan->terpakai->format())->toBe('Rp 200.000')
        ->and($kemajuan->sisa->format())->toBe('Rp 300.000')
        ->and($kemajuan->persentase)->toBe(40)
        ->and($kemajuan->terlampaui)->toBeFalse();
});

it('menandai anggaran yang terlampaui', function (): void {
    Budget::factory()->create([
        'category_id' => $this->transportasi->getKey(),
        'amount_minor' => 10_000_000, // Rp 100.000
    ]);

    catatBerkategori(150_000, $this->kas, $this->transportasi, now()->toDateString());

    $kemajuan = app(BudgetProgress::class)->untukTanggal()->first();

    expect($kemajuan->terlampaui)->toBeTrue()
        ->and($kemajuan->sisa->formatPlain())->toBe('-50.000')
        ->and($kemajuan->persentase)->toBe(100);
});

it('tidak mencampur anggaran antar kategori', function (): void {
    Budget::factory()->create(['category_id' => $this->transportasi->getKey(), 'amount_minor' => 50_000_000]);

    catatBerkategori(400_000, $this->kas, $this->makan, now()->toDateString());

    $kemajuan = app(BudgetProgress::class)->untukTanggal()->first();

    expect($kemajuan->terpakai->isZero())->toBeTrue();
});

it('membuat anggaran dari halaman anggaran', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Anggaran::class)
        ->call('bukaFormulir')
        ->set('kategoriId', $this->transportasi->getKey())
        ->set('jumlah', '500.000')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Budget::query()->sole()->amount_minor->format())->toBe('Rp 500.000');
});

/*
|--------------------------------------------------------------------------
| Ekspor
|--------------------------------------------------------------------------
*/

it('mengekspor transaksi sebagai CSV', function (): void {
    catatBerkategori(150_000, $this->kas, $this->transportasi, '2026-07-10');

    $balasan = $this->actingAs($this->pengguna)
        ->get('/app/ekspor?dari=2026-07-01&sampai=2026-07-31');

    $balasan->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $isi = $balasan->streamedContent();

    expect($isi)->toContain('Tanggal', 'Nominal (sen)')
        ->and($isi)->toContain('2026-07-10')
        ->and($isi)->toContain('Transportasi')
        ->and($isi)->toContain('15000000'); // minor unit, bisa dihitung ulang
});

it('mencatat ekspor sebagai peristiwa keamanan tanpa nominal', function (): void {
    catatBerkategori(150_000, $this->kas, $this->transportasi, '2026-07-10');

    $this->actingAs($this->pengguna)->get('/app/ekspor?dari=2026-07-01&sampai=2026-07-31')->streamedContent();

    actingInWorkspace($this->workspace, $this->pengguna);

    $peristiwa = SecurityEvent::query()->where('event', SecurityEventType::DataExported)->sole();

    // Dua baris: transaksi berkategori tadi, plus saldo awal Kas — yang juga
    // transaksi sungguhan dan memang harus ikut terekspor.
    //
    // Ekspor adalah satu-satunya jalur data keluar utuh; ia dicatat, tapi
    // catatannya sendiri tidak boleh memuat nominal (aturan A6).
    expect($peristiwa->metadata)->toHaveKey('row_count', 2)
        ->and($peristiwa->metadata)->not->toHaveKey('total')
        ->and(json_encode($peristiwa->metadata))->not->toContain('15000000');
});

it('membatasi ekspor lima kali per jam', function (): void {
    $this->actingAs($this->pengguna);

    foreach (range(1, 5) as $kali) {
        $this->get('/app/ekspor')->assertOk();
    }

    $this->get('/app/ekspor')->assertStatus(429);
});

/*
 * Transaksi yang dibatalkan tidak boleh muncul sebagai arus.
 *
 * Aturan A3 melarang menghapus, jadi koreksi selalu berupa pasangan: yang
 * lama ditandai void, dan pembaliknya dicatat. Pasangan itu menjumlahkan nol
 * pada saldo — tapi tidak pada "pemasukan" dan "pengeluaran", dua angka yang
 * paling sering dibaca orang.
 */

it('tidak menampilkan transaksi yang dibatalkan sebagai arus kas', function (): void {
    $kas = buatAkun('Kas', 1_000_000);

    $salah = catatPengeluaran(50_000, $kas, 'salah catat', '2026-05-10');
    app(VoidTransaction::class)($salah, 'salah orang');

    $benar = catatPengeluaran(30_000, $kas, 'yang benar', '2026-05-10');
    expect($benar->exists)->toBeTrue();

    $arus = app(Reports::class)->arusKas(
        new DateTimeImmutable('2026-05-01'),
        new DateTimeImmutable('2026-05-31'),
        'month',
    );

    expect($arus)->toHaveCount(1);

    // Hanya pengeluaran yang benar. Sebelum perbaikan ini, keluar = 80.000
    // dan masuk = 50.000 — uang yang tidak pernah masuk ke mana pun.
    expect($arus->first()->keluar->minor)->toBe(30_000 * 100)
        ->and($arus->first()->masuk->minor)->toBe(0);
});

it('tidak menghitung transaksi yang dibatalkan ke dalam laba rugi', function (): void {
    $kas = buatAkun('Kas', 1_000_000);

    $salah = catatPemasukan(90_000, $kas, 'salah', '2026-05-10');
    app(VoidTransaction::class)($salah);

    $laporan = app(Reports::class)->labaRugi(
        new DateTimeImmutable('2026-05-01'),
        new DateTimeImmutable('2026-05-31'),
    );

    expect($laporan->pendapatan->minor)->toBe(0)
        ->and($laporan->beban->minor)->toBe(0);
});

it('tetap membuat saldo akun sama dengan sebelum salah catat', function (): void {
    $kas = buatAkun('Kas', 1_000_000);
    $saldoAwal = $kas->fresh()->balance()->minor;

    $salah = catatPengeluaran(50_000, $kas, 'salah', '2026-05-10');
    app(VoidTransaction::class)($salah);

    // Neraca sengaja tetap menghitung pasangan void+pembalik, supaya angkanya
    // sama dengan saldo akun. Yang dikecualikan hanya laporan arus.
    expect($kas->fresh()->balance()->minor)->toBe($saldoAwal);
});
