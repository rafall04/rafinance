<?php

declare(strict_types=1);

namespace App\Domain\Capture\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\ParseFailureFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teks yang gagal dibaca parser.
 *
 * Sengaja tidak dibersihkan otomatis (retensi 90 hari SETELAH resolved_at).
 * Tabel ini adalah satu-satunya sumber jujur tentang bagaimana orang sungguhan
 * menulis, dan parser yang tidak pernah membaik akan pelan-pelan mengusir
 * penggunanya.
 *
 * @property string $id
 */
class ParseFailure extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<ParseFailureFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'raw_text',
        'reason',
        'inbox_item_id',
        'resolved_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }

    public function inboxItem(): BelongsTo
    {
        return $this->belongsTo(InboxItem::class);
    }
}
