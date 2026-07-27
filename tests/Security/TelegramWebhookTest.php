<?php

declare(strict_types=1);

use App\Channels\Telegram\Jobs\ProcessTelegramUpdate;
use App\Channels\Telegram\Models\TelegramLink;
use App\Channels\Telegram\Models\TelegramUpdate;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const RAHASIA_UJI = 'rahasia-webhook-untuk-test-saja';

function kirimWebhook(array $payload, ?string $rahasia = RAHASIA_UJI): TestResponse
{
    $header = $rahasia === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $rahasia];

    return test()->postJson('/webhooks/telegram', $payload, $header);
}

function updatePesan(int $updateId, string $teks, int $telegramUserId = 555_001, int $chatId = 555_001): array
{
    return [
        'update_id' => $updateId,
        'message' => [
            'message_id' => $updateId * 10,
            'from' => ['id' => $telegramUserId, 'is_bot' => false, 'first_name' => 'Sri', 'username' => 'sri'],
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'date' => 1_700_000_000,
            'text' => $teks,
        ],
    ];
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);
});

it('menjawab 404 untuk permintaan tanpa header rahasia', function (): void {
    // 404, bukan 401 maupun 403. Endpoint yang menjawab "token salah" sudah
    // memberi tahu penebak bahwa ia menemukan URL yang benar.
    kirimWebhook(updatePesan(1, '50k bensin'), rahasia: null)->assertNotFound();

    expect(TelegramUpdate::query()->count())->toBe(0);
});

it('menjawab 404 untuk header rahasia yang salah', function (): void {
    kirimWebhook(updatePesan(2, '50k bensin'), rahasia: 'tebakan-yang-salah')->assertNotFound();

    expect(TelegramUpdate::query()->count())->toBe(0);
});

it('menerima permintaan dengan header rahasia yang benar', function (): void {
    Queue::fake();

    kirimWebhook(updatePesan(3, '50k bensin'))->assertOk();

    expect(TelegramUpdate::query()->find(3))->not->toBeNull();
    Queue::assertPushed(ProcessTelegramUpdate::class);
});

it('tidak menggandakan transaksi saat update yang sama dikirim ulang', function (): void {
    [$pengguna, $workspace] = makeWorkspaceFor();
    $kas = buatAkun('Kas', 1_000_000);

    TelegramLink::factory()->create([
        'user_id' => $pengguna->getKey(),
        'telegram_user_id' => 555_001,
        'chat_id' => 555_001,
        'active_workspace_id' => $workspace->getKey(),
    ]);

    // Telegram mengirim ulang update yang belum di-ack. Tanpa dedup, satu
    // gangguan jaringan berarti pengeluaran tercatat dua kali (aturan A9).
    kirimWebhook(updatePesan(42, '50k bensin'))->assertOk();
    kirimWebhook(updatePesan(42, '50k bensin'))->assertOk();
    kirimWebhook(updatePesan(42, '50k bensin'))->assertOk();

    actingInWorkspace($workspace, $pengguna);

    expect(TelegramUpdate::query()->count())->toBe(1)
        ->and(Transaction::query()->bukanSaldoAwal()->count())->toBe(1)
        ->and($kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('memproses update yang berbeda sebagai transaksi yang berbeda', function (): void {
    [$pengguna, $workspace] = makeWorkspaceFor();
    buatAkun('Kas', 1_000_000);

    TelegramLink::factory()->create([
        'user_id' => $pengguna->getKey(),
        'telegram_user_id' => 555_001,
        'chat_id' => 555_001,
        'active_workspace_id' => $workspace->getKey(),
    ]);

    kirimWebhook(updatePesan(50, '50k bensin'))->assertOk();
    kirimWebhook(updatePesan(51, '30k kopi'))->assertOk();

    actingInWorkspace($workspace, $pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(2);
});

it('tidak mempercayai telegram_user_id mentah untuk membuat hubungan', function (): void {
    [$pengguna, $workspace] = makeWorkspaceFor();
    buatAkun('Kas', 1_000_000);

    // Tidak ada TelegramLink. Pengirim mengaku siapa pun tidak ada gunanya —
    // hubungan hanya lahir dari kode enam digit yang diterbitkan di web.
    kirimWebhook(updatePesan(60, '50k bensin', telegramUserId: 999_999))->assertOk();

    actingInWorkspace($workspace, $pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(0)
        ->and(TelegramLink::query()->count())->toBe(0);
});

it('mengabaikan payload yang bukan bentuk update Telegram', function (): void {
    kirimWebhook(['halo' => 'dunia'])->assertOk();

    expect(TelegramUpdate::query()->count())->toBe(0);
});

it('menandai update yang gagal diproses tanpa kehilangan payload-nya', function (): void {
    kirimWebhook(updatePesan(70, '/start', telegramUserId: 888_888))->assertOk();

    $update = TelegramUpdate::query()->find(70);

    expect($update)->not->toBeNull()
        ->and($update->status)->toBe('processed')
        ->and($update->payload['message']['text'])->toBe('/start');
});
