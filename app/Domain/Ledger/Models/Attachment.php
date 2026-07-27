<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Models\User;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $disk_path
 */
class Attachment extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    use HasUlids;

    public const DISK = 'lampiran';

    protected $fillable = [
        'workspace_id',
        'transaction_id',
        'disk_path',
        'mime',
        'size_bytes',
        'sha256',
        'uploaded_by',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Disk privat. Tidak ada URL publik sama sekali — akses hanya lewat rute
     * bertanda tangan yang berumur pendek, setelah kepemilikan diverifikasi
     * (aturan A11).
     */
    public function temporaryUrl(int $minutes = 5): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($this->disk_path, now()->addMinutes($minutes));
    }

    public function exists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->disk_path);
    }
}
