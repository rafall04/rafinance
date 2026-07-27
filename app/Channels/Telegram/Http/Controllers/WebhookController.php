<?php

declare(strict_types=1);

namespace App\Channels\Telegram\Http\Controllers;

use App\Channels\Telegram\Jobs\ProcessTelegramUpdate;
use App\Channels\Telegram\Models\TelegramUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pintu masuk webhook Telegram.
 *
 * Tiga hal terjadi di sini, dan tidak lebih:
 *
 *   1. Verifikasi header rahasia. Tanpa header yang cocok, jawabannya 404 —
 *      bukan 401, bukan 403. URL webhook yang menjawab "salah token" sudah
 *      memberi tahu penebak bahwa ia menemukan endpoint yang benar.
 *   2. Simpan update_id. Primary key-nya yang melakukan dedup (aturan A9).
 *   3. Lempar ke antrean, jawab 200.
 *
 * Semua pekerjaan sungguhan ada di job. Telegram menganggap webhook gagal kalau
 * jawabannya lambat, lalu mengirim ulang — dan pengiriman ulang yang menumpuk
 * di jalur yang sudah lambat adalah cara sistem ini bisa roboh sendiri.
 */
final class WebhookController
{
    public function __invoke(Request $request): Response
    {
        $this->pastikanRahasiaCocok($request);

        $payload = $request->all();
        $updateId = $payload['update_id'] ?? null;

        if (! is_int($updateId)) {
            // Bukan bentuk update Telegram. Jawab 200 supaya tidak dikirim
            // ulang selamanya, tapi jangan kerjakan apa pun.
            return response()->noContent(Response::HTTP_OK);
        }

        // insertOrIgnore, bukan create() lalu menangkap exception: di
        // PostgreSQL, pelanggaran unique membatalkan seluruh transaksi yang
        // sedang berjalan, dan menangkapnya setelah itu terlambat. ON CONFLICT
        // DO NOTHING menyelesaikannya secara atomik, tanpa pernah melempar.
        $tersimpan = TelegramUpdate::query()->insertOrIgnore([
            'update_id' => $updateId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($tersimpan === 0) {
            // Sudah pernah diterima. Bukan kesalahan — justru dedup bekerja
            // sebagaimana mestinya (aturan A9).
            return response()->noContent(Response::HTTP_OK);
        }

        ProcessTelegramUpdate::dispatch($updateId);

        return response()->noContent(Response::HTTP_OK);
    }

    private function pastikanRahasiaCocok(Request $request): void
    {
        $diharapkan = config('rafin.telegram.webhook_secret');
        $diterima = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($diharapkan) || $diharapkan === '') {
            abort(404);
        }

        if (! is_string($diterima) || ! hash_equals($diharapkan, $diterima)) {
            abort(404);
        }
    }
}
