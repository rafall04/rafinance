<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/**
 * Pesannya menjelaskan apa yang terjadi dan apa yang bisa dilakukan, bukan
 * sekadar menyatakan penolakan. Orang yang menabrak batas sedang berusaha
 * mencatat sesuatu, dan pantas tahu jalan keluarnya.
 */
final class QuotaTerlampaui extends RuntimeException
{
    public function __construct(
        public readonly string $metric,
        public readonly int $terpakai,
        public readonly int $batas,
    ) {
        parent::__construct($this->pesan());
    }

    private function pesan(): string
    {
        return match ($this->metric) {
            'transactions' => sprintf(
                'Batas %s transaksi bulan ini sudah tercapai. Naikkan plan, atau tunggu bulan berikutnya — '
                .'catatan lama tetap bisa dibuka dan diekspor.',
                number_format($this->batas, 0, ',', '.'),
            ),
            'members' => sprintf(
                'Plan ini memuat %d anggota. Naikkan plan untuk menambah orang.',
                $this->batas,
            ),
            'attachments_bytes' => 'Ruang lampiran habis. Hapus lampiran lama atau naikkan plan.',
            default => 'Batas plan tercapai untuk '.$this->metric.'.',
        };
    }
}
