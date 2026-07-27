<?php

declare(strict_types=1);

use App\Channels\Telegram\CommandRouter;
use App\Channels\Telegram\Models\TelegramLink;
use App\Channels\Telegram\Models\TelegramLinkCode;
use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Capture\Models\InboxItem;
use App\Domain\Capture\Models\ParseFailure;
use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Livewire\App\Inbox;
use App\Livewire\App\Pengaturan\HubungkanTelegram;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function pesanDari(int $telegramUserId, string $teks, int $chatId = 777_001): array
{
    return [
        'message' => [
            'message_id' => 1234,
            'from' => ['id' => $telegramUserId, 'is_bot' => false, 'first_name' => 'Sri'],
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'date' => 1_700_000_000,
            'text' => $teks,
        ],
    ];
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4321]])]);

    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
    $this->transportasi = Category::query()->create(['name' => 'Transportasi', 'kind' => CategoryKind::Expense]);

    $this->router = app(CommandRouter::class);
});

it('menghubungkan akun lewat kode enam digit dari web', function (): void {
    $tiket = TelegramLinkCode::terbitkanUntuk($this->pengguna);

    $this->router->tangani(pesanDari(777_001, "/link {$tiket->code}"));

    $link = TelegramLink::query()->where('telegram_user_id', 777_001)->sole();

    expect($link->user_id)->toBe($this->pengguna->getKey())
        ->and($link->active_workspace_id)->toBe($this->workspace->getKey())
        ->and($tiket->fresh()->used_at)->not->toBeNull();
});

it('menolak kode yang sudah kedaluwarsa', function (): void {
    $tiket = TelegramLinkCode::terbitkanUntuk($this->pengguna);
    $tiket->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->router->tangani(pesanDari(777_002, "/link {$tiket->code}"));

    expect(TelegramLink::query()->count())->toBe(0);
});

it('menolak kode yang sudah dipakai', function (): void {
    $tiket = TelegramLinkCode::terbitkanUntuk($this->pengguna);
    $this->router->tangani(pesanDari(777_003, "/link {$tiket->code}"));

    // Percobaan kedua dengan kode yang sama, dari akun Telegram lain.
    $this->router->tangani(pesanDari(777_004, "/link {$tiket->code}"));

    expect(TelegramLink::query()->count())->toBe(1)
        ->and(TelegramLink::query()->sole()->telegram_user_id)->toBe(777_003);
});

it('menghanguskan kode lama saat kode baru diterbitkan', function (): void {
    $lama = TelegramLinkCode::terbitkanUntuk($this->pengguna);
    $baru = TelegramLinkCode::terbitkanUntuk($this->pengguna);

    expect(TelegramLinkCode::query()->find($lama->code))->toBeNull()
        ->and($baru->code)->not->toBe($lama->code)
        ->and(TelegramLinkCode::query()->count())->toBe(1);
});

it('mencatat penautan sebagai peristiwa keamanan', function (): void {
    $tiket = TelegramLinkCode::terbitkanUntuk($this->pengguna);
    $this->router->tangani(pesanDari(777_005, "/link {$tiket->code}"));

    expect(SecurityEvent::query()->where('event', SecurityEventType::TelegramLinked)->count())->toBe(1);
});

it('mencatat transaksi dari pesan Telegram', function (): void {
    TelegramLink::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'telegram_user_id' => 777_010,
        'chat_id' => 777_001,
        'active_workspace_id' => $this->workspace->getKey(),
    ]);

    $this->router->tangani(pesanDari(777_010, '50k bensin'));

    actingInWorkspace($this->workspace, $this->pengguna);
    $transaksi = Transaction::query()->bukanSaldoAwal()->sole();

    expect($transaksi->source)->toBe(TransactionSource::Telegram)
        ->and($transaksi->raw_input)->toBe('50k bensin')
        ->and($transaksi->category_id)->toBe($this->transportasi->getKey())
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('menyimpan pesan yang tidak terbaca ke inbox, bukan menolaknya', function (): void {
    TelegramLink::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'telegram_user_id' => 777_011,
        'chat_id' => 777_001,
        'active_workspace_id' => $this->workspace->getKey(),
    ]);

    $this->router->tangani(pesanDari(777_011, 'entah apa ini'));

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(0)
        ->and(InboxItem::query()->count())->toBe(1)
        ->and(InboxItem::query()->sole()->parse_status)->toBe(ParseStatus::Failed)
        ->and(ParseFailure::query()->count())->toBe(1);
});

it('menyimpan foto tanpa teks ke inbox', function (): void {
    TelegramLink::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'telegram_user_id' => 777_012,
        'chat_id' => 777_001,
        'active_workspace_id' => $this->workspace->getKey(),
    ]);

    $this->router->tangani([
        'message' => [
            'message_id' => 5,
            'from' => ['id' => 777_012, 'is_bot' => false],
            'chat' => ['id' => 777_001, 'type' => 'private'],
            'date' => 1_700_000_000,
            'photo' => [['file_id' => 'abc', 'width' => 800, 'height' => 600]],
        ],
    ]);

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(InboxItem::query()->sole()->raw_payload)->toHaveKey('photo');
});

it('membatalkan catatan terakhir lewat /undo', function (): void {
    TelegramLink::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'telegram_user_id' => 777_013,
        'chat_id' => 777_001,
        'active_workspace_id' => $this->workspace->getKey(),
    ]);

    $this->router->tangani(pesanDari(777_013, '50k bensin'));
    $this->router->tangani(pesanDari(777_013, '/undo'));

    actingInWorkspace($this->workspace, $this->pengguna);

    // Dibalik, bukan dihapus: yang lama tetap ada, yang baru meniadakannya.
    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(2)
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 1.000.000');
});

it('tidak melayani perintah dari akun yang belum terhubung', function (): void {
    $this->router->tangani(pesanDari(777_099, '/saldo'));

    Http::assertSent(fn ($request): bool => str_contains($request->body(), 'belum+terhubung')
        || str_contains(urldecode($request->body()), 'belum terhubung'));
});

it('memperbarui pesan Telegram lama saat item inbox diselesaikan dari web', function (): void {
    $item = InboxItem::factory()->dariTelegram(chatId: 777_001, messageId: 4321)->create([
        'raw_text' => 'bensin',
        'created_by' => $this->pengguna->getKey(),
    ]);

    Livewire::actingAs($this->pengguna)
        ->test(Inbox::class)
        ->call('buka', $item->getKey())
        ->set('nominal', '50000')
        ->set('akunId', $this->kas->getKey())
        ->set('kategoriId', $this->transportasi->getKey())
        ->call('selesaikan')
        ->assertHasNoErrors();

    // Tombol basi membuat sistem terasa rusak: pesan lama harus ikut berubah.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'editMessageText'));

    expect($item->fresh()->parse_status)->toBe(ParseStatus::Parsed)
        ->and($item->fresh()->transaction_id)->not->toBeNull()
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('menandai parse_failure selesai saat itemnya dilengkapi', function (): void {
    $item = InboxItem::factory()->create(['raw_text' => 'bensin', 'created_by' => $this->pengguna->getKey()]);
    ParseFailure::factory()->create(['raw_text' => 'bensin', 'inbox_item_id' => $item->getKey()]);

    Livewire::actingAs($this->pengguna)
        ->test(Inbox::class)
        ->call('buka', $item->getKey())
        ->set('nominal', '50000')
        ->set('akunId', $this->kas->getKey())
        ->call('selesaikan');

    expect(ParseFailure::query()->sole()->resolved_at)->not->toBeNull();
});

it('mengabaikan item inbox tanpa membuat transaksi', function (): void {
    $item = InboxItem::factory()->create(['raw_text' => 'bukan transaksi']);

    Livewire::actingAs($this->pengguna)
        ->test(Inbox::class)
        ->call('abaikan', $item->getKey());

    expect($item->fresh()->parse_status)->toBe(ParseStatus::Dismissed)
        ->and(Transaction::query()->bukanSaldoAwal()->count())->toBe(0);
});

it('menerbitkan kode penautan dari halaman pengaturan', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(HubungkanTelegram::class)
        ->call('terbitkanKode')
        ->assertSet('kode', fn (?string $kode): bool => $kode !== null && preg_match('/^\d{6}$/', $kode) === 1);

    expect(TelegramLinkCode::query()->where('user_id', $this->pengguna->getKey())->count())->toBe(1);
});

it('memutuskan Telegram dan mencatatnya sebagai peristiwa keamanan', function (): void {
    TelegramLink::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'telegram_user_id' => 777_020,
    ]);

    Livewire::actingAs($this->pengguna)
        ->test(HubungkanTelegram::class)
        ->call('putuskan');

    expect(TelegramLink::query()->aktif()->count())->toBe(0)
        ->and(SecurityEvent::query()->where('event', SecurityEventType::TelegramUnlinked)->count())->toBe(1);
});

it('tidak membiarkan satu akun Telegram terhubung ke dua pengguna', function (): void {
    $orangLain = User::factory()->create();

    TelegramLink::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'telegram_user_id' => 777_030,
    ]);

    $tiket = TelegramLinkCode::terbitkanUntuk($orangLain);
    $this->router->tangani(pesanDari(777_030, "/link {$tiket->code}"));

    // Kolom unique memaksa updateOrCreate, jadi hubungannya berpindah — bukan
    // menggandakan. Yang penting: tidak pernah ada dua pemilik sekaligus.
    expect(TelegramLink::query()->count())->toBe(1);
});
