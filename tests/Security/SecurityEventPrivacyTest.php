<?php

declare(strict_types=1);

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Aturan A6 — security_events tidak boleh memuat nominal
|--------------------------------------------------------------------------
|
| Tabel ini satu-satunya jejak yang boleh dibaca admin platform. Kalau satu
| nominal saja bocor ke sini, janji "panel admin tidak bisa melihat uang Anda"
| berhenti jadi janji. Karena itu dijaga tiga kali: penjaga saat penulisan,
| pemindai isi tabel, dan arch test terhadap kode.
|
*/

it('mengenali kunci yang berbau nominal', function (string $key): void {
    expect(SecurityLogger::isForbiddenKey($key))->toBeTrue();
})->with([
    'amount',
    'amount_minor',
    'total',
    'total_amount',
    'balance',
    'saldo',
    'saldo_akhir',
    'nominal',
    'price',
    'harga',
    'Amount',
    'TOTAL',
    'debit',
    'kredit',
]);

it('membiarkan kunci metadata yang wajar', function (string $key): void {
    expect(SecurityLogger::isForbiddenKey($key))->toBeFalse();
})->with([
    'guard',
    'ip',
    'device_id',
    'attempted_email',
    'member_count',
    'role',
    'workspace_id',
    'exported_rows',
]);

it('menolak menulis metadata bernominal', function (): void {
    [$user] = makeWorkspaceFor();

    app(SecurityLogger::class)->log(
        SecurityEventType::DataExported,
        user: $user,
        metadata: ['rows' => 120, 'total_amount' => 5_000_000],
    );
})->throws(RuntimeException::class, 'aturan A6');

it('menolak nominal yang bersarang di dalam metadata', function (): void {
    [$user] = makeWorkspaceFor();

    app(SecurityLogger::class)->log(
        SecurityEventType::AdminAction,
        user: $user,
        metadata: ['detail' => ['nested' => ['saldo' => 1_000]]],
    );
})->throws(RuntimeException::class);

it('tetap menulis peristiwa dengan metadata yang bersih', function (): void {
    [$user, $workspace] = makeWorkspaceFor();

    $event = app(SecurityLogger::class)->log(
        SecurityEventType::LoginSuccess,
        user: $user,
        metadata: ['guard' => 'web', 'remember' => false],
    );

    expect($event->event)->toBe(SecurityEventType::LoginSuccess)
        ->and($event->user_id)->toBe($user->getKey())
        ->and($event->workspace_id)->toBe($workspace->getKey())
        ->and($event->metadata)->toBe(['guard' => 'web', 'remember' => false]);
});

it('tidak menyimpan satu pun kunci nominal di seluruh isi tabel', function (): void {
    [$user] = makeWorkspaceFor();
    $logger = app(SecurityLogger::class);

    // Tulis satu peristiwa untuk setiap jenis yang ada di katalog, supaya
    // pemindaian ini menyentuh seluruh jalur, bukan hanya yang kebetulan
    // dipakai test lain.
    foreach (SecurityEventType::cases() as $type) {
        $logger->log($type, user: $user, metadata: ['guard' => 'web', 'note' => 'uji']);
    }

    $rows = DB::connection('pgsql')->select('SELECT metadata FROM security_events WHERE metadata IS NOT NULL');

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        $payload = json_decode((string) $row->metadata, true, 512, JSON_THROW_ON_ERROR);

        foreach (flattenKeys($payload) as $key) {
            expect(SecurityLogger::isForbiddenKey($key))->toBeFalse(
                "security_events memuat kunci bernominal: {$key}"
            );
        }
    }
});

it('tidak punya satu pun kolom bertipe angka nominal', function (): void {
    $columns = DB::connection('pgsql')->select(
        "SELECT column_name, data_type
         FROM information_schema.columns
         WHERE table_name = 'security_events'"
    );

    foreach ($columns as $column) {
        expect(SecurityLogger::isForbiddenKey((string) $column->column_name))->toBeFalse(
            "Kolom {$column->column_name} berbau nominal"
        );

        // Tidak ada alasan sebuah tabel metadata punya kolom numerik presisi.
        expect((string) $column->data_type)->not->toBeIn(['numeric', 'money', 'double precision', 'real']);
    }
});

it('tidak menyimpan nominal meski peristiwanya soal ekspor data', function (): void {
    [$user] = makeWorkspaceFor();

    $event = app(SecurityLogger::class)->log(
        SecurityEventType::DataExported,
        user: $user,
        metadata: ['format' => 'csv', 'row_count' => 1_204, 'scope' => 'transactions'],
    );

    expect($event->metadata)->toHaveKey('row_count')
        ->and($event->metadata)->not->toHaveKey('total');
});

it('menyimpan peristiwa ekspor sebagai peristiwa keamanan', function (): void {
    // Ekspor adalah satu-satunya jalur data keluar utuh, jadi ia memang
    // diperlakukan setara dengan perubahan kata sandi.
    expect(SecurityEventType::DataExported->shouldNotifyUser())->toBeTrue();
});

/**
 * @param  array<array-key, mixed>  $payload
 * @return array<int, string>
 */
function flattenKeys(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = array_merge($keys, flattenKeys($value));
        }
    }

    return $keys;
}
