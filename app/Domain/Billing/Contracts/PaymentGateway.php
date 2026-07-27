<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\Subscription;
use App\Support\Money;

/**
 * Antarmuka penyedia pembayaran.
 *
 * Sengaja hanya antarmuka: integrasi sungguhan belum dikerjakan, dan seluruh
 * plan berharga nol. Yang ada di sini adalah bentuk lubangnya, supaya saat
 * Midtrans atau Xendit dipasang nanti, keputusan desainnya sudah diambil hari
 * ini dan bukan di bawah tekanan tenggat.
 *
 * Dua hal yang sudah ditetapkan dan tidak akan berubah:
 *
 *   - Nominal selalu berupa Money, tidak pernah float (aturan A1).
 *   - Webhook penyedia harus idempoten lewat provider_ref, dengan alasan yang
 *     sama seperti update_id Telegram (aturan A9).
 */
interface PaymentGateway
{
    public function nama(): string;

    /**
     * Membuat tagihan dan mengembalikan URL tempat pengguna membayar.
     */
    public function buatTagihan(Subscription $langganan, Money $jumlah): string;

    /**
     * Memeriksa keaslian webhook penyedia.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifikasiWebhook(array $payload, string $tandaTangan): bool;

    /**
     * Rujukan pembayaran dari payload webhook. Dipakai untuk dedup.
     *
     * @param  array<string, mixed>  $payload
     */
    public function rujukan(array $payload): ?string;
}
