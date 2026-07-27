<?php

declare(strict_types=1);

namespace App\Domain\Logging;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Satu-satunya pintu menulis ke security_events.
 *
 * Aturan A6 ditegakkan di sini, saat penulisan — bukan hanya lewat test.
 * Test menangkap pelanggaran yang sudah ditulis; penjaga ini menangkap yang
 * datang dari data dinamis, misalnya payload webhook yang diteruskan mentah
 * ke metadata. Di lokal dan test ia melempar supaya ketahuan langsung; di
 * produksi ia membuang kuncinya dan melapor, karena mencatat peristiwa masuk
 * yang tersensor jauh lebih baik daripada menggagalkan proses masuk.
 */
final class SecurityLogger
{
    /**
     * Kata yang tidak boleh muncul sebagai kunci metadata. Daftar ini sengaja
     * dicocokkan sebagai potongan kata, bukan kata utuh: `total_amount`,
     * `saldo_akhir`, dan `amount_minor` semuanya harus tertangkap.
     */
    public const FORBIDDEN_KEY_PATTERNS = [
        'amount',
        'total',
        'balance',
        'saldo',
        'nominal',
        'price',
        'harga',
        'minor',
        'debit',
        'credit',
        'kredit',
        'rupiah',
    ];

    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        SecurityEventType $event,
        ?User $user = null,
        ?Request $request = null,
        array $metadata = [],
        ?string $workspaceId = null,
    ): SecurityEvent {
        $metadata = $this->sanitize($metadata, $event);

        return SecurityEvent::query()->create([
            'user_id' => $user?->getKey(),
            'workspace_id' => $workspaceId ?? $this->tenant->id(),
            'event' => $event,
            'ip' => $request?->ip(),
            'user_agent' => $this->truncate($request?->userAgent(), 512),
            // hasSession() diperiksa lebih dulu: peristiwa keamanan juga
            // ditulis dari jalur tanpa sesi — job antrean, perintah artisan,
            // dan webhook Telegram — dan pencatatan tidak boleh gagal di sana.
            'device_id' => $request?->hasSession() === true ? $request->session()->getId() : null,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<array-key, mixed>
     */
    public function sanitize(array $metadata, ?SecurityEventType $event = null): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && self::isForbiddenKey($key)) {
                $this->refuse($key, $event);

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value, $event) : $value;
        }

        return $clean;
    }

    public static function isForbiddenKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach (self::FORBIDDEN_KEY_PATTERNS as $pattern) {
            if (str_contains($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function refuse(string $key, ?SecurityEventType $event): void
    {
        $message = sprintf(
            'security_events tidak boleh memuat nominal (aturan A6). Kunci "%s" ditolak%s. '
            .'Peristiwa yang mengandung angka uang tempatnya di audit_logs.',
            $key,
            $event !== null ? " pada peristiwa {$event->value}" : '',
        );

        if (app()->runningUnitTests() || app()->environment('local')) {
            throw new RuntimeException($message);
        }

        Log::warning($message);
    }

    private function truncate(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
