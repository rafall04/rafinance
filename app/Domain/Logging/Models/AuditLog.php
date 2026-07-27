<?php

declare(strict_types=1);

namespace App\Domain\Logging\Models;

use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Jejak audit workspace — boleh memuat nominal, dan tidak pernah bisa dibaca
 * admin platform (aturan A5).
 *
 * Hanya bisa ditambah. Database tidak punya policy UPDATE maupun DELETE untuk
 * tabel ini, dan model ini menolaknya lebih awal supaya pesannya bisa dibaca
 * manusia alih-alih berupa galat izin PostgreSQL.
 *
 * @property string $id
 * @property AuditAction $action
 * @property string $hash
 * @property string|null $prev_hash
 */
class AuditLog extends Model
{
    use BelongsToWorkspace;
    use HasUlids;

    public $timestamps = false;

    /**
     * Mikrodetik wajib disimpan.
     *
     * Format tanggal bawaan Laravel adalah 'Y-m-d H:i:s' — tanpa pecahan detik.
     * Dengan format itu, created_at yang dipakai menghitung hash berbeda dari
     * yang akhirnya tersimpan, dan setiap baris akan dilaporkan putus oleh
     * rafin:audit:verify meski tidak ada yang menyentuhnya. Mikrodetik juga
     * yang membuat urutan dua baris di detik yang sama tidak ambigu.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id',
        'workspace_id',
        'actor_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'ip',
        'prev_hash',
        'hash',
        'created_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException(
                'audit_logs hanya bisa ditambah. Mengubah satu baris akan memutus rantai hash, '
                .'dan rantai yang putus adalah satu-satunya bukti bahwa jejak ini utuh.'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException('audit_logs tidak bisa dihapus baris per baris.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * hash = sha256(prev_hash || action || auditable_id || created_at)
     */
    public static function computeHash(?string $prevHash, string $action, ?string $auditableId, string $createdAt): string
    {
        return hash('sha256', ($prevHash ?? '').$action.($auditableId ?? '').$createdAt);
    }

    public function expectedHash(): string
    {
        return self::computeHash(
            $this->prev_hash,
            $this->action->value,
            $this->auditable_id,
            $this->created_at->format('Y-m-d H:i:s.u'),
        );
    }
}
