<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Tenancy\TenantContext;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mencatat transaksi: nominal dulu, sisanya belakangan.
 *
 * Urutannya bukan selera. Orang membuka layar ini karena baru saja mengeluarkan
 * uang, dan satu-satunya hal yang pasti ia ingat saat itu adalah angkanya.
 * Meminta kategori lebih dulu berarti meminta orang mengklasifikasi sebelum
 * mencatat — persis kebalikan dari prinsip produk ini.
 */
class Tambah extends Component
{
    /** Digit mentah dari papan angka, tanpa pemisah. */
    public string $angka = '';

    #[Url(as: 'kind')]
    public string $arah = 'expense';

    public string $akunId = '';

    public string $akunTujuanId = '';

    public string $kategoriId = '';

    public string $keterangan = '';

    public string $tanggal = '';

    public function mount(): void
    {
        $this->tanggal = now(auth()->user()?->effectiveTimezone() ?? 'Asia/Jakarta')->toDateString();
        $this->akunId = (string) (Account::query()->uang()->aktif()->orderBy('sort_order')->value('id') ?? '');
    }

    public function tekan(string $digit): void
    {
        // Batas 12 digit: Rp 999.999.999.999 sudah jauh di luar jangkauan
        // pengguna yang dituju, dan batas ini mencegah salah tekan panjang.
        if (strlen($this->angka) >= 12) {
            return;
        }

        if ($digit === '000' && $this->angka === '') {
            return;
        }

        $this->angka = ltrim($this->angka.$digit, '0');
    }

    public function hapusDigit(): void
    {
        $this->angka = substr($this->angka, 0, -1);
    }

    public function kosongkan(): void
    {
        $this->angka = '';
    }

    public function pilihArah(string $arah): void
    {
        $this->arah = $arah;
        $this->kategoriId = '';
    }

    public function simpan(PostTransaction $post): void
    {
        $data = $this->validate();

        $nominal = $this->nominal();

        if ($nominal === null || $nominal->isZero()) {
            throw ValidationException::withMessages([
                'angka' => 'Isi nominalnya dulu.',
            ]);
        }

        $akun = Account::query()->findOrFail($data['akunId']);
        $kind = TransactionKind::from($data['arah']);

        $draft = match ($kind) {
            TransactionKind::Expense => DraftTransaction::pengeluaran(
                amount: $nominal,
                from: $akun,
                bookedDate: $data['tanggal'],
                description: $data['keterangan'] ?: null,
                categoryId: $data['kategoriId'] ?: null,
            ),
            TransactionKind::Income => DraftTransaction::pemasukan(
                amount: $nominal,
                to: $akun,
                bookedDate: $data['tanggal'],
                description: $data['keterangan'] ?: null,
                categoryId: $data['kategoriId'] ?: null,
            ),
            TransactionKind::Transfer => DraftTransaction::pindah(
                amount: $nominal,
                from: $akun,
                to: Account::query()->findOrFail($data['akunTujuanId']),
                bookedDate: $data['tanggal'],
                description: $data['keterangan'] ?: null,
            ),
            default => throw ValidationException::withMessages(['arah' => 'Arah transaksi tidak dikenal.']),
        };

        $post($draft);

        session()->flash('kabar', 'Tersimpan.');

        $this->redirectRoute('app.beranda', navigate: true);
    }

    public function nominal(): ?Money
    {
        if ($this->angka === '') {
            return null;
        }

        $mataUang = app(TenantContext::class)->workspace()?->currency ?? 'IDR';

        return Money::ofMajor((int) $this->angka, $mataUang);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'angka' => ['required', 'string', 'regex:/^[1-9][0-9]*$/'],
            'arah' => ['required', 'in:expense,income,transfer'],
            'akunId' => ['required', 'ulid'],
            'akunTujuanId' => [
                $this->arah === 'transfer' ? 'required' : 'nullable',
                'ulid',
                'different:akunId',
            ],
            'kategoriId' => ['nullable', 'ulid'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'angka' => 'nominal',
            'akunId' => 'akun',
            'akunTujuanId' => 'akun tujuan',
            'kategoriId' => 'kategori',
            'keterangan' => 'keterangan',
            'tanggal' => 'tanggal',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'angka.required' => 'Isi nominalnya dulu.',
            'angka.regex' => 'Isi nominalnya dulu.',
            'akunTujuanId.different' => 'Akun asal dan tujuan harus berbeda.',
            'akunTujuanId.required' => 'Pilih akun tujuannya.',
        ];
    }

    public function render()
    {
        $kind = TransactionKind::tryFrom($this->arah) ?? TransactionKind::Expense;

        return view('livewire.app.tambah', [
            'akunPilihan' => Account::query()->uang()->aktif()->orderBy('sort_order')->orderBy('name')->get(),
            'kategoriPilihan' => $kind->categoryKind() === null
                ? collect()
                : Category::query()->aktif()->where('kind', $kind->categoryKind())->orderBy('name')->get(),
            'arahPilihan' => TransactionKind::userSelectable(),
            'nominal' => $this->nominal(),
        ])->layout('components.layouts.app', ['title' => 'Tambah']);
    }
}
