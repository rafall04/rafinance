<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Channels\Telegram\Models\TelegramLink;
use App\Channels\Telegram\Models\TelegramLinkCode;
use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Capture\Models\InboxItem;
use App\Domain\Capture\Services\CaptureText;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\LedgerView;
use App\Domain\Ledger\Services\VoidTransaction;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Mengarahkan setiap pesan Telegram ke tindakan yang tepat.
 *
 * Aturan yang tidak pernah dilanggar di sini: telegram_user_id dari webhook
 * hanya dipakai untuk MENCARI hubungan yang sudah ada, tidak pernah untuk
 * membuatnya. Hubungan hanya lahir dari kode enam digit yang diterbitkan di
 * web oleh orang yang sudah masuk.
 */
final class CommandRouter
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly ReplyBuilder $balasan,
        private readonly KeyboardBuilder $tombol,
        private readonly CaptureText $tangkap,
        private readonly TenantContext $tenant,
        private readonly LedgerView $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function tangani(array $payload): void
    {
        if (isset($payload['callback_query'])) {
            $this->tanganiTombol($payload['callback_query']);

            return;
        }

        $pesan = $payload['message'] ?? $payload['edited_message'] ?? null;

        if (! is_array($pesan)) {
            return;
        }

        $this->tanganiPesan($pesan);
    }

    /**
     * @param  array<string, mixed>  $pesan
     */
    private function tanganiPesan(array $pesan): void
    {
        $telegramUserId = $pesan['from']['id'] ?? null;
        $chatId = $pesan['chat']['id'] ?? null;

        if (! is_int($telegramUserId) || ! is_int($chatId)) {
            return;
        }

        $teks = trim((string) ($pesan['text'] ?? $pesan['caption'] ?? ''));
        $link = TelegramLink::query()->aktif()->where('telegram_user_id', $telegramUserId)->first();

        if ($link === null) {
            $this->tanganiTanpaHubungan($chatId, $telegramUserId, $teks, $pesan);

            return;
        }

        $this->pasangKonteks($link);

        if (Str::startsWith($teks, '/')) {
            $this->jalankanPerintah($link, $chatId, $teks);

            return;
        }

        if ($teks === '') {
            // Foto atau voice note tanpa teks. Simpan sebagai inbox; OCR dan
            // transkrip menunggu plan berbayar (aturan A12, sekarang mati).
            $this->simpanMediaKeInbox($link, $chatId, $pesan);

            return;
        }

        $this->catat($link, $chatId, $teks, $pesan);
    }

    /**
     * @param  array<string, mixed>  $pesan
     */
    private function tanganiTanpaHubungan(int $chatId, int $telegramUserId, string $teks, array $pesan): void
    {
        if (preg_match('/^\/link\s+(\d{6})$/', $teks, $cocok) === 1) {
            $this->hubungkan($chatId, $telegramUserId, $cocok[1], $pesan);

            return;
        }

        $this->telegram->sendMessage($chatId, $this->balasan->belumTerhubung());
    }

    /**
     * @param  array<string, mixed>  $pesan
     */
    private function hubungkan(int $chatId, int $telegramUserId, string $kode, array $pesan): void
    {
        $tiket = TelegramLinkCode::query()->find($kode);

        if ($tiket === null || ! $tiket->masihBerlaku()) {
            // Jawaban yang sama untuk kode salah dan kode kedaluwarsa: keduanya
            // tidak perlu memberi tahu penebak sejauh mana ia sudah benar.
            $this->telegram->sendMessage($chatId, 'Kode tidak berlaku. Buat kode baru dari web.');

            return;
        }

        $pengguna = $tiket->user;

        if ($pengguna === null) {
            return;
        }

        $workspace = WorkspaceMember::query()
            ->where('user_id', $pengguna->getKey())
            ->oldest('joined_at')
            ->first()
            ?->workspace_id;

        TelegramLink::query()->updateOrCreate(
            ['telegram_user_id' => $telegramUserId],
            [
                'user_id' => $pengguna->getKey(),
                'chat_id' => $chatId,
                'active_workspace_id' => $workspace,
                'username' => $pesan['from']['username'] ?? null,
                'linked_at' => now(),
                'unlinked_at' => null,
            ],
        );

        $tiket->tandaiTerpakai();

        app(SecurityLogger::class)->log(
            SecurityEventType::TelegramLinked,
            user: $pengguna,
            metadata: ['channel' => 'telegram'],
            workspaceId: $workspace,
        );

        $this->telegram->sendMessage(
            $chatId,
            "Terhubung sebagai <b>{$this->aman($pengguna->name)}</b>.\n\nCoba ketik: <code>50k bensin</code>",
        );
    }

    private function jalankanPerintah(TelegramLink $link, int $chatId, string $teks): void
    {
        $perintah = Str::lower(Str::before(ltrim($teks, '/').' ', ' '));

        match ($perintah) {
            'start', 'bantuan', 'help' => $this->telegram->sendMessage($chatId, $this->balasan->bantuan()),
            'link' => $this->telegram->sendMessage($chatId, 'Akun ini sudah terhubung.'),
            'saldo' => $this->kirimSaldo($chatId),
            'hariini' => $this->kirimRingkasan($chatId, 'Hari ini', now()->startOfDay(), now()->endOfDay()),
            'bulanini' => $this->kirimRingkasan($chatId, 'Bulan ini', now()->startOfMonth(), now()->endOfMonth()),
            'laporan' => $this->kirimRingkasan($chatId, 'Bulan ini', now()->startOfMonth(), now()->endOfMonth()),
            'inbox' => $this->kirimInbox($chatId),
            'undo' => $this->batalkanTerakhir($chatId),
            'switch' => $this->kirimPilihanWorkspace($link, $chatId),
            default => $this->telegram->sendMessage($chatId, 'Perintah tidak dikenal. Ketik /bantuan.'),
        };
    }

    /**
     * @param  array<string, mixed>  $pesan
     */
    private function catat(TelegramLink $link, int $chatId, string $teks, array $pesan): void
    {
        $workspace = $this->tenant->workspace();

        if ($workspace === null) {
            $this->telegram->sendMessage($chatId, 'Belum ada buku aktif. Buka Rafin di web untuk membuatnya.');

            return;
        }

        $hasil = ($this->tangkap)(
            teks: $teks,
            sumber: TransactionSource::Telegram,
            pengguna: $link->user,
            currency: $workspace->currency,
            telegramChatId: $chatId,
            telegramMessageId: is_int($pesan['message_id'] ?? null) ? $pesan['message_id'] : null,
        );

        if (! $hasil->berhasil()) {
            $terkirim = $this->telegram->sendMessage(
                $chatId,
                $this->balasan->masukInbox($hasil->draft, $hasil->alasan()),
            );

            $this->ingatPesan($hasil->inboxItem, $terkirim);

            return;
        }

        $akun = Account::query()->find($hasil->draft->accountId);

        $terkirim = $this->telegram->sendMessage(
            $chatId,
            $this->balasan->tersimpan($hasil->transaction, $akun),
            $this->tombol->setelahTersimpan($hasil->transaction),
        );

        $this->ingatPesan($hasil->inboxItem, $terkirim);
    }

    /**
     * @param  array<string, mixed>  $pesan
     */
    private function simpanMediaKeInbox(TelegramLink $link, int $chatId, array $pesan): void
    {
        InboxItem::query()->create([
            'source' => TransactionSource::Telegram,
            'raw_text' => null,
            'raw_payload' => $pesan,
            'parse_status' => ParseStatus::Failed,
            'created_by' => $link->user_id,
            'telegram_chat_id' => $chatId,
            'telegram_message_id' => is_int($pesan['message_id'] ?? null) ? $pesan['message_id'] : null,
        ]);

        $this->telegram->sendMessage(
            $chatId,
            "📥 Tersimpan di inbox, lengkapi nanti.\n   Ketik nominalnya kalau ingin langsung dicatat.",
        );
    }

    private function kirimSaldo(int $chatId): void
    {
        $akun = $this->ledger->akunUang();
        $mataUang = $this->tenant->workspace()?->currency ?? 'IDR';

        if ($akun->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'Belum ada akun. Buat dulu di web.');

            return;
        }

        $this->telegram->sendMessage(
            $chatId,
            $this->balasan->saldo($akun, $this->ledger->saldoTotal($akun->pluck('id')->all(), $mataUang)),
        );
    }

    private function kirimRingkasan(int $chatId, string $judul, \DateTimeInterface $dari, \DateTimeInterface $sampai): void
    {
        $mataUang = $this->tenant->workspace()?->currency ?? 'IDR';

        $transaksi = Transaction::query()
            ->where('status', TransactionStatus::Posted)
            ->whereBetween('booked_date', [$dari->format('Y-m-d'), $sampai->format('Y-m-d')])
            ->with('entries')
            ->get();

        $idAkunUang = $this->ledger->idAkunUang();

        $masuk = Money::zero($mataUang);
        $keluar = Money::zero($mataUang);

        foreach ($transaksi as $satu) {
            $delta = (int) $satu->entries->whereIn('account_id', $idAkunUang)->sum('amount_minor');

            if ($delta > 0) {
                $masuk = $masuk->plus(Money::ofMinor($delta, $mataUang));
            } else {
                $keluar = $keluar->plus(Money::ofMinor(-$delta, $mataUang));
            }
        }

        $this->telegram->sendMessage(
            $chatId,
            $this->balasan->ringkasanPeriode($judul, $masuk, $keluar, $transaksi->count()),
        );
    }

    private function kirimInbox(int $chatId): void
    {
        $item = InboxItem::query()->belumSelesai()->latest()->limit(10)->get();

        if ($item->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'Inbox kosong. Semua sudah lengkap.');

            return;
        }

        $baris = ['<b>Perlu dilengkapi ('.$item->count().')</b>', ''];

        foreach ($item as $satu) {
            $baris[] = '   • '.$this->aman(Str::limit($satu->raw_text ?? '(foto atau suara)', 60));
        }

        $this->telegram->sendMessage(
            $chatId,
            implode("\n", $baris),
            $this->tombol->bukaDiWeb(route('app.inbox'), 'Lengkapi di web'),
        );
    }

    private function batalkanTerakhir(int $chatId): void
    {
        $transaksi = Transaction::query()
            ->where('status', TransactionStatus::Posted)
            ->where('source', TransactionSource::Telegram)
            ->latest('created_at')
            ->first();

        if ($transaksi === null) {
            $this->telegram->sendMessage($chatId, 'Tidak ada catatan dari Telegram yang bisa dibatalkan.');

            return;
        }

        app(VoidTransaction::class)($transaksi, 'Dibatalkan lewat /undo');

        $this->telegram->sendMessage(
            $chatId,
            "↩ Dibatalkan: {$this->aman($transaksi->description ?? 'transaksi terakhir')}\n"
            .'   Catatan lamanya tetap tersimpan sebagai riwayat.',
        );
    }

    private function kirimPilihanWorkspace(TelegramLink $link, int $chatId): void
    {
        $workspace = Workspace::query()
            ->whereIn('id', WorkspaceMember::query()->where('user_id', $link->user_id)->pluck('workspace_id'))
            ->get();

        if ($workspace->count() < 2) {
            $this->telegram->sendMessage($chatId, 'Anda hanya punya satu buku.');

            return;
        }

        $tombol = $workspace
            ->map(fn (Workspace $satu): array => [[
                'text' => ($satu->getKey() === $link->active_workspace_id ? '✓ ' : '').$satu->name,
                'callback_data' => 'ws:'.$satu->getKey(),
            ]])
            ->values()
            ->all();

        $this->telegram->sendMessage($chatId, 'Pilih buku:', $tombol);
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function tanganiTombol(array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $telegramUserId = $callback['from']['id'] ?? null;
        $callbackId = (string) ($callback['id'] ?? '');

        if (! is_int($chatId) || ! is_int($messageId) || ! is_int($telegramUserId)) {
            return;
        }

        $link = TelegramLink::query()->aktif()->where('telegram_user_id', $telegramUserId)->first();

        if ($link === null) {
            $this->telegram->answerCallbackQuery($callbackId, 'Akun belum terhubung.');

            return;
        }

        $this->pasangKonteks($link);

        [$aksi, $argumen] = array_pad(explode(':', $data, 2), 2, '');

        match ($aksi) {
            'kat' => $this->tampilkanPilihanKategori($chatId, $messageId, $argumen, $callbackId),
            'akn' => $this->tampilkanPilihanAkun($chatId, $messageId, $argumen, $callbackId),
            'sk' => $this->terapkanKategori($chatId, $messageId, $argumen, $callbackId),
            'sa' => $this->telegram->answerCallbackQuery($callbackId, 'Ubah akun perlu pembalikan — lakukan dari web.'),
            'btl' => $this->batalkanLewatTombol($chatId, $messageId, $argumen, $callbackId),
            'ws' => $this->gantiWorkspace($link, $chatId, $messageId, $argumen, $callbackId),
            default => $this->telegram->answerCallbackQuery($callbackId),
        };
    }

    private function tampilkanPilihanKategori(int $chatId, int $messageId, string $transaksiId, string $callbackId): void
    {
        $transaksi = Transaction::query()->find($transaksiId);

        if ($transaksi === null) {
            $this->telegram->answerCallbackQuery($callbackId, 'Transaksi tidak ditemukan.');

            return;
        }

        $jenis = $transaksi->kind->categoryKind();

        if ($jenis === null) {
            $this->telegram->answerCallbackQuery($callbackId, 'Transfer tidak punya kategori.');

            return;
        }

        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            'Pilih kategori:',
            $this->tombol->pilihKategori($transaksi, Category::query()->aktif()->where('kind', $jenis)->orderBy('name')->get()),
        );

        $this->telegram->answerCallbackQuery($callbackId);
    }

    private function tampilkanPilihanAkun(int $chatId, int $messageId, string $transaksiId, string $callbackId): void
    {
        $transaksi = Transaction::query()->find($transaksiId);

        if ($transaksi === null) {
            $this->telegram->answerCallbackQuery($callbackId, 'Transaksi tidak ditemukan.');

            return;
        }

        // Mengubah akun berarti mengubah entries, dan entries transaksi posted
        // terkunci (aturan A3). Jalurnya pembalikan, dan itu keputusan yang
        // pantas dilakukan sambil melihat layar penuh, bukan lewat satu tombol.
        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            "Mengubah akun berarti membalik transaksi ini dan mencatat ulang.\nLakukan dari web supaya terlihat jelas.",
            $this->tombol->bukaDiWeb(route('app.beranda')),
        );

        $this->telegram->answerCallbackQuery($callbackId);
    }

    private function terapkanKategori(int $chatId, int $messageId, string $argumen, string $callbackId): void
    {
        [$transaksiId, $kategoriId] = array_pad(explode(':', $argumen, 2), 2, '');

        $transaksi = Transaction::query()->find($transaksiId);
        $kategori = Category::query()->find($kategoriId);

        if ($transaksi === null || $kategori === null) {
            $this->telegram->answerCallbackQuery($callbackId, 'Sudah tidak berlaku.');

            return;
        }

        // Kategori adalah metadata pelaporan, bukan bagian dari entries — jadi
        // ia boleh berubah tanpa melanggar larangan mengubah transaksi posted.
        $transaksi->forceFill(['category_id' => $kategori->getKey()])->save();

        $akun = Account::query()->find(
            $transaksi->entries()->where('amount_minor', '<', 0)->value('account_id')
        );

        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            $akun !== null
                ? $this->balasan->tersimpan($transaksi->fresh(['entries', 'category']), $akun)
                : 'Kategori diperbarui.',
        );

        $this->telegram->answerCallbackQuery($callbackId, 'Kategori diperbarui.');
    }

    private function batalkanLewatTombol(int $chatId, int $messageId, string $transaksiId, string $callbackId): void
    {
        $transaksi = Transaction::query()->find($transaksiId);

        if ($transaksi === null || ! $transaksi->isPosted()) {
            $this->telegram->answerCallbackQuery($callbackId, 'Sudah tidak berlaku.');

            return;
        }

        app(VoidTransaction::class)($transaksi, 'Dibatalkan dari Telegram');

        // Tombol dihapus bersamaan dengan teksnya: tombol yang tidak lagi
        // berfungsi membuat sistem terasa rusak.
        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            "↩ Dibatalkan.\n   Catatan lamanya tetap tersimpan sebagai riwayat.",
        );

        $this->telegram->answerCallbackQuery($callbackId, 'Dibatalkan.');
    }

    private function gantiWorkspace(TelegramLink $link, int $chatId, int $messageId, string $workspaceId, string $callbackId): void
    {
        $anggota = WorkspaceMember::query()
            ->where('user_id', $link->user_id)
            ->where('workspace_id', $workspaceId)
            ->exists();

        if (! $anggota) {
            $this->telegram->answerCallbackQuery($callbackId, 'Buku itu bukan milik Anda.');

            return;
        }

        $link->forceFill(['active_workspace_id' => $workspaceId])->save();
        $this->pasangKonteks($link->fresh());

        $nama = Workspace::query()->find($workspaceId)?->name ?? 'buku';

        $this->telegram->editMessageText($chatId, $messageId, "Buku aktif: <b>{$this->aman($nama)}</b>");
        $this->telegram->answerCallbackQuery($callbackId);
    }

    private function pasangKonteks(?TelegramLink $link): void
    {
        if ($link === null) {
            return;
        }

        $this->tenant->setUserId((string) $link->user_id);

        if ($link->active_workspace_id !== null) {
            $this->tenant->setWorkspaceId((string) $link->active_workspace_id);
        }

        // Bot bertindak atas nama pemilik akun, dan audit log harus menunjukkan
        // siapa itu.
        if ($link->user instanceof User) {
            auth()->setUser($link->user);
        }
    }

    private function ingatPesan(InboxItem $item, ?array $balasanTelegram): void
    {
        $messageId = $balasanTelegram['result']['message_id'] ?? null;

        if (is_int($messageId)) {
            $item->forceFill(['telegram_message_id' => $messageId])->save();
        }
    }

    private function aman(string $teks): string
    {
        return htmlspecialchars($teks, ENT_NOQUOTES, 'UTF-8');
    }
}
