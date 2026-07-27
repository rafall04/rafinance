<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Channels\Telegram\ReplyBuilder;
use App\Channels\Telegram\TelegramClient;
use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Capture\Models\InboxItem;
use App\Domain\Capture\Models\ParseFailure;
use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Services\PostTransaction;
use App\Support\Money;
use Livewire\Component;

/**
 * Item yang belum lengkap dari semua kanal.
 *
 * Halaman ini adalah separuh kedua dari janji "capture dulu, klasifikasi
 * belakangan". Separuh pertama tidak ada artinya tanpa tempat yang enak untuk
 * menyelesaikannya.
 */
class Inbox extends Component
{
    public string $sedangDiisi = '';

    public string $nominal = '';

    public string $akunId = '';

    public string $kategoriId = '';

    public string $keterangan = '';

    public string $arah = 'expense';

    public function buka(string $id): void
    {
        $item = InboxItem::query()->findOrFail($id);

        $this->sedangDiisi = $id;
        $this->resetValidation();

        $draft = $item->parsed_draft ?? [];

        $this->arah = is_string($draft['kind'] ?? null) ? $draft['kind'] : 'expense';
        $this->keterangan = is_string($draft['description'] ?? null)
            ? $draft['description']
            : (string) ($item->raw_text ?? '');
        $this->kategoriId = is_string($draft['category_id'] ?? null) ? $draft['category_id'] : '';
        $this->akunId = is_string($draft['account_id'] ?? null)
            ? $draft['account_id']
            : (string) (Account::query()->uang()->aktif()->value('id') ?? '');

        // Nominal ditampilkan dalam satuan utuh: yang mengisi manusia, bukan
        // mesin, dan manusia menulis 50000 bukan 5000000.
        $minor = $draft['amount_minor'] ?? null;
        $this->nominal = is_int($minor) ? (string) intdiv($minor, 100) : '';
    }

    public function tutupFormulir(): void
    {
        $this->sedangDiisi = '';
        $this->reset(['nominal', 'akunId', 'kategoriId', 'keterangan']);
    }

    public function selesaikan(PostTransaction $post): void
    {
        $data = $this->validate();

        $item = InboxItem::query()->findOrFail($this->sedangDiisi);
        $akun = Account::query()->findOrFail($data['akunId']);
        $mataUang = $akun->currency;
        $jumlah = Money::parse($data['nominal'], $mataUang);

        $kind = TransactionKind::from($data['arah']);

        $draft = $kind === TransactionKind::Income
            ? DraftTransaction::pemasukan(
                amount: $jumlah,
                to: $akun,
                description: $data['keterangan'] ?: null,
                categoryId: $data['kategoriId'] ?: null,
                source: TransactionSource::Web,
                rawInput: $item->raw_text,
            )
            : DraftTransaction::pengeluaran(
                amount: $jumlah,
                from: $akun,
                description: $data['keterangan'] ?: null,
                categoryId: $data['kategoriId'] ?: null,
                source: TransactionSource::Web,
                rawInput: $item->raw_text,
            );

        $transaksi = $post($draft);

        $item->forceFill([
            'parse_status' => ParseStatus::Parsed,
            'transaction_id' => $transaksi->getKey(),
        ])->save();

        ParseFailure::query()
            ->where('inbox_item_id', $item->getKey())
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        $this->perbaruiPesanTelegram($item, $akun, $transaksi);

        $this->tutupFormulir();

        session()->flash('kabar', 'Tersimpan.');
    }

    public function abaikan(string $id): void
    {
        $item = InboxItem::query()->findOrFail($id);
        $item->forceFill(['parse_status' => ParseStatus::Dismissed])->save();

        ParseFailure::query()
            ->where('inbox_item_id', $item->getKey())
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        $this->perbaruiPesanTelegramDiabaikan($item);

        session()->flash('kabar', 'Diabaikan.');
    }

    /**
     * Memperbarui pesan lama di Telegram supaya tombolnya hilang.
     *
     * Tombol yang tidak lagi berfungsi membuat sistem terasa rusak, dan
     * pengguna yang menekannya akan mengira catatannya hilang.
     */
    private function perbaruiPesanTelegram(InboxItem $item, Account $akun, $transaksi): void
    {
        if (! $item->punyaPesanTelegram()) {
            return;
        }

        app(TelegramClient::class)->editMessageText(
            $item->telegram_chat_id,
            $item->telegram_message_id,
            app(ReplyBuilder::class)->tersimpan($transaksi, $akun),
        );
    }

    private function perbaruiPesanTelegramDiabaikan(InboxItem $item): void
    {
        if (! $item->punyaPesanTelegram()) {
            return;
        }

        app(TelegramClient::class)->editMessageText(
            $item->telegram_chat_id,
            $item->telegram_message_id,
            '🗑 Diabaikan dari web.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nominal' => ['required', 'string', 'max:20'],
            'arah' => ['required', 'in:expense,income'],
            'akunId' => ['required', 'ulid'],
            'kategoriId' => ['nullable', 'ulid'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['nominal' => 'nominal', 'akunId' => 'akun', 'kategoriId' => 'kategori', 'keterangan' => 'keterangan'];
    }

    public function render()
    {
        $kind = TransactionKind::tryFrom($this->arah) ?? TransactionKind::Expense;

        return view('livewire.app.inbox', [
            'daftar' => InboxItem::query()->belumSelesai()->latest()->limit(50)->get(),
            'akunPilihan' => Account::query()->uang()->aktif()->orderBy('sort_order')->get(),
            'kategoriPilihan' => $kind->categoryKind() === null
                ? collect()
                : Category::query()->aktif()->where('kind', $kind->categoryKind())->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Inbox']);
    }
}
