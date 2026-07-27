<?php

declare(strict_types=1);

namespace App\Domain\Capture\Services;

use App\Domain\Capture\Models\InboxItem;
use App\Domain\Capture\NormalizedDraft;
use App\Domain\Ledger\Models\Transaction;

final readonly class CaptureResult
{
    public function __construct(
        public NormalizedDraft $draft,
        public ?Transaction $transaction,
        public InboxItem $inboxItem,
    ) {}

    public function berhasil(): bool
    {
        return $this->transaction !== null;
    }

    /**
     * Alasan singkat kenapa belum jadi transaksi, dalam bahasa yang bisa
     * dibaca pengguna — bukan nama field yang kosong.
     */
    public function alasan(): string
    {
        if ($this->berhasil()) {
            return '';
        }

        if ($this->draft->amount === null) {
            return 'Nominalnya belum terbaca.';
        }

        if ($this->draft->accountId === null) {
            return 'Belum jelas dari akun mana.';
        }

        return 'Masih ada yang perlu dilengkapi.';
    }
}
