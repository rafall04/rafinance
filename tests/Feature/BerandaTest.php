<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\LedgerView;
use App\Livewire\App\Akun;
use App\Livewire\App\Tambah;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

it('menampilkan saldo rail dengan saldo berjalan yang menurun ke bawah', function (): void {
    // Tanggalnya sesudah pembuatan akun, seperti keadaan sungguhan: saldo awal
    // menyatakan keadaan saat buku dibuka, transaksi datang sesudahnya.
    catatPengeluaran(100_000, $this->kas, 'Bensin', now()->addDay()->toDateString());
    catatPengeluaran(50_000, $this->kas, 'Makan', now()->addDays(2)->toDateString());
    catatPemasukan(200_000, $this->kas, 'Iuran', now()->addDays(3)->toDateString());

    $hari = app(LedgerView::class)->harian();

    // Diurutkan dari yang terbaru, dengan saldo SESUDAH tiap transaksi.
    // Baris terakhir adalah saldo awal, yang juga transaksi sungguhan.
    $saldo = $hari->flatMap(fn (object $h) => $h->baris)->map(fn (object $b): string => $b->saldo->formatPlain());

    expect($saldo->all())->toBe(['1.050.000', '850.000', '900.000', '1.000.000'])
        ->and($hari)->toHaveCount(4);
});

it('menghitung perubahan bersih per hari di kepala kelompok', function (): void {
    $besok = now()->addDay()->toDateString();
    $lusa = now()->addDays(2)->toDateString();

    catatPengeluaran(30_000, $this->kas, 'Kopi', $besok);
    catatPengeluaran(20_000, $this->kas, 'Parkir', $besok);
    catatPemasukan(500_000, $this->kas, 'Bayaran', $lusa);

    $hari = app(LedgerView::class)->harian()->keyBy(fn ($h): string => $h->tanggal->toDateString());

    expect($hari[$lusa]->netHarian->formatPlain())->toBe('500.000')
        ->and($hari[$besok]->netHarian->formatPlain())->toBe('-50.000');
});

it('menyaring buku besar per akun', function (): void {
    $bca = buatAkun('BCA', 0);

    catatPengeluaran(100_000, $this->kas, 'Bensin');
    catatPengeluaran(200_000, $bca, 'Listrik');

    $ledger = app(LedgerView::class);
    $baris = fn ($akun) => $ledger->harian($akun)->flatMap(fn ($h) => $h->baris);

    // Kas punya dua baris: saldo awalnya sendiri, dan satu pengeluaran.
    expect($baris($this->kas))->toHaveCount(2)
        ->and($baris($bca))->toHaveCount(1)
        ->and($baris(null))->toHaveCount(3);
});

it('menampilkan beranda beserta transaksinya', function (): void {
    catatPengeluaran(50_000, $this->kas, 'Bensin');

    $this->actingAs($this->pengguna)->get('/app')
        ->assertOk()
        ->assertSee('Bensin')
        ->assertSee('Saldo total')
        ->assertSee('Rp 950.000');
});

it('mencatat pengeluaran dari halaman tambah', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Tambah::class)
        ->set('akunId', $this->kas->getKey())
        ->call('tekan', '5')
        ->call('tekan', '000')
        ->set('keterangan', 'Bensin')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.beranda'));

    $transaksi = Transaction::query()->bukanSaldoAwal()->sole();

    expect($transaksi->description)->toBe('Bensin')
        ->and($transaksi->load('entries')->amount()->format())->toBe('Rp 5.000')
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 995.000');
});

it('menolak menyimpan tanpa nominal', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Tambah::class)
        ->set('akunId', $this->kas->getKey())
        ->call('simpan')
        ->assertHasErrors(['angka']);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(0);
});

it('menolak pindah ke akun yang sama dari halaman tambah', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Tambah::class)
        ->call('pilihArah', 'transfer')
        ->set('akunId', $this->kas->getKey())
        ->set('akunTujuanId', $this->kas->getKey())
        ->call('tekan', '1')
        ->call('simpan')
        ->assertHasErrors(['akunTujuanId']);
});

it('membatasi panjang nominal supaya salah tekan tidak jadi bencana', function (): void {
    $komponen = Livewire::actingAs($this->pengguna)->test(Tambah::class);

    foreach (range(1, 20) as $sekali) {
        $komponen->call('tekan', '9');
    }

    expect(strlen($komponen->get('angka')))->toBe(12);
});

it('menambah akun baru dengan saldo awal', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Akun::class)
        ->call('bukaFormulir')
        ->set('nama', 'BCA')
        ->set('subtype', 'bank')
        ->set('saldoAwal', '2.500.000')
        ->call('simpan')
        ->assertHasNoErrors();

    $akun = Account::query()->where('name', 'BCA')->sole();

    expect($akun->balance()->format())->toBe('Rp 2.500.000')
        ->and($akun->subtype->label())->toBe('Bank');
});

it('menutup akun tanpa menghapus riwayatnya', function (): void {
    catatPengeluaran(50_000, $this->kas, 'Bensin');

    Livewire::actingAs($this->pengguna)
        ->test(Akun::class)
        ->call('tutupAkun', $this->kas->getKey());

    expect($this->kas->fresh()->is_archived)->toBeTrue()
        ->and(Transaction::query()->bukanSaldoAwal()->count())->toBe(1);
});

it('tidak menampilkan akun sistem di halaman akun', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $halaman = $this->actingAs($this->pengguna)->get('/app/akun')->getContent();

    expect($halaman)->toContain('Kas')
        ->and($halaman)->not->toContain('>Beban<');
});
