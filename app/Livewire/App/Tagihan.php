<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Contacts\Models\Invoice;
use App\Domain\Contacts\Models\InvoiceItem;
use App\Domain\Contacts\Services\RecordInvoicePayment;
use App\Domain\Ledger\Models\Account;
use App\Domain\Tenancy\TenantContext;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Kontak, tagihan, dan umur piutang dalam satu halaman.
 *
 * Digabung karena begitulah orang memakainya: pertanyaannya bukan "siapa saja
 * pelanggan saya" melainkan "siapa yang belum bayar, dan sudah berapa lama".
 */
class Tagihan extends Component
{
    public bool $sedangMenambah = false;

    public string $namaKontak = '';

    public string $kontakId = '';

    public string $nomor = '';

    public string $jumlah = '';

    public string $jatuhTempo = '';

    public string $membayarId = '';

    public string $jumlahBayar = '';

    public string $akunPenerimaId = '';

    public function mount(): void
    {
        $this->jatuhTempo = now()->addDays(14)->toDateString();
        $this->nomor = $this->nomorBerikutnya();
        $this->akunPenerimaId = (string) (Account::query()->uang()->aktif()->value('id') ?? '');
    }

    public function bukaFormulir(): void
    {
        $this->sedangMenambah = true;
        $this->nomor = $this->nomorBerikutnya();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'namaKontak' => ['required_without:kontakId', 'nullable', 'string', 'max:80'],
            'kontakId' => ['nullable', 'ulid'],
            'nomor' => ['required', 'string', 'max:32'],
            'jumlah' => ['required', 'string', 'max:20'],
            'jatuhTempo' => ['required', 'date'],
        ], [], [
            'namaKontak' => 'nama kontak',
            'nomor' => 'nomor tagihan',
            'jumlah' => 'jumlah',
            'jatuhTempo' => 'jatuh tempo',
        ]);

        $mataUang = app(TenantContext::class)->workspace()?->currency ?? 'IDR';
        $total = Money::parse($data['jumlah'], $mataUang);

        if ($total->isZero()) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah tagihan harus lebih dari nol.']);
        }

        $kontak = $data['kontakId'] !== ''
            ? Contact::query()->findOrFail($data['kontakId'])
            : Contact::query()->create(['name' => $data['namaKontak'], 'type' => 'customer']);

        $tagihan = Invoice::query()->create([
            'contact_id' => $kontak->getKey(),
            'number' => $data['nomor'],
            'issue_date' => now()->toDateString(),
            'due_date' => $data['jatuhTempo'],
            'total_minor' => $total,
            'status' => 'sent',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $tagihan->getKey(),
            'description' => 'Tagihan '.$data['nomor'],
            'qty_milli' => InvoiceItem::SKALA_QTY,
            'unit_price_minor' => $total,
        ]);

        $this->sedangMenambah = false;
        $this->reset(['namaKontak', 'kontakId', 'jumlah']);
        $this->nomor = $this->nomorBerikutnya();

        session()->flash('kabar', 'Tagihan dibuat.');
    }

    public function bukaPembayaran(string $id): void
    {
        $tagihan = Invoice::query()->findOrFail($id);

        $this->membayarId = $id;
        $this->jumlahBayar = (string) intdiv($tagihan->sisa()->minor, 100);
    }

    public function catatPembayaran(RecordInvoicePayment $catat): void
    {
        $data = $this->validate([
            'jumlahBayar' => ['required', 'string', 'max:20'],
            'akunPenerimaId' => ['required', 'ulid'],
        ], [], ['jumlahBayar' => 'jumlah pembayaran', 'akunPenerimaId' => 'akun penerima']);

        $tagihan = Invoice::query()->findOrFail($this->membayarId);
        $akun = Account::query()->findOrFail($data['akunPenerimaId']);
        $mataUang = $akun->currency;

        try {
            $catat($tagihan, Money::parse($data['jumlahBayar'], $mataUang), $akun);
        } catch (\InvalidArgumentException $galat) {
            throw ValidationException::withMessages(['jumlahBayar' => $galat->getMessage()]);
        }

        $this->reset(['membayarId', 'jumlahBayar']);
        session()->flash('kabar', 'Pembayaran dicatat, dan uangnya masuk ke buku besar.');
    }

    private function nomorBerikutnya(): string
    {
        $urutan = Invoice::query()->count() + 1;

        return sprintf('INV-%s-%03d', now()->format('Ym'), $urutan);
    }

    public function render()
    {
        $belumLunas = Invoice::query()
            ->belumLunas()
            ->with(['contact', 'payments'])
            ->orderBy('due_date')
            ->get();

        $mataUang = app(TenantContext::class)->workspace()?->currency ?? 'IDR';

        // Laporan umur piutang: dikelompokkan 30/60/90 seperti kebiasaan
        // laporan aging, karena itulah bahasa yang dipakai saat menagih.
        $umur = $belumLunas
            ->groupBy(fn (Invoice $satu): string => $satu->kelompokUmur())
            ->map(fn ($kelompok) => (object) [
                'jumlah' => $kelompok->count(),
                'total' => $kelompok->reduce(
                    fn (Money $carry, Invoice $satu): Money => $carry->plus($satu->sisa()),
                    Money::zero($mataUang),
                ),
            ]);

        return view('livewire.app.tagihan', [
            'belumLunas' => $belumLunas,
            'umur' => $umur,
            'totalPiutang' => $belumLunas->reduce(
                fn (Money $carry, Invoice $satu): Money => $carry->plus($satu->sisa()),
                Money::zero($mataUang),
            ),
            'kontakPilihan' => Contact::query()->orderBy('name')->get(),
            'akunPilihan' => Account::query()->uang()->aktif()->orderBy('sort_order')->get(),
        ])->layout('components.layouts.app', ['title' => 'Tagihan']);
    }
}
