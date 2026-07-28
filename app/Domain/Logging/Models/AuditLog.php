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
     * Sidik jari satu baris audit, beserta seluruh isinya.
     *
     * Versi sebelumnya hanya menghitung prev_hash, action, auditable_id, dan
     * created_at. Artinya `before`, `after`, `actor_user_id`, dan `ip` berada
     * DI LUAR rantai: seseorang yang bisa menulis ke tabel ini dapat mengubah
     * angka sebelum-sesudah sebuah perubahan, atau menukar siapa pelakunya,
     * tanpa satu pun hash meleset. Yang terbukti utuh hanyalah "ada tindakan
     * berjenis ini atas objek ini pada saat itu" — bukan apa yang terjadi dan
     * bukan oleh siapa. Untuk jejak audit pembukuan, dua hal terakhir itulah
     * yang paling ingin diubah orang.
     *
     * Dua hal yang membuat perhitungannya bisa diandalkan:
     *
     * 1. **Pemisah baris antar ruas.** Penggabungan polos membuat pasangan
     *    ruas yang berbeda bisa menghasilkan masukan yang sama persis —
     *    ("ab", "c") dan ("a", "bc") tidak terbedakan.
     *
     * 2. **Bentuk kanonik untuk JSON.** Kolomnya bertipe jsonb, dan
     *    PostgreSQL menyimpan kunci jsonb dalam urutannya sendiri, bukan
     *    urutan saat ditulis. Meng-encode apa adanya berarti hash saat
     *    menulis dan saat memverifikasi bisa berbeda untuk baris yang sama
     *    dan tidak tersentuh — rantai yang menuduh dirinya sendiri.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function computeHash(
        ?string $prevHash,
        string $action,
        ?string $auditableType,
        ?string $auditableId,
        ?string $actorId,
        ?string $ip,
        ?array $before,
        ?array $after,
        string $createdAt,
    ): string {
        return hash('sha256', implode("\n", [
            $prevHash ?? '',
            $action,
            $auditableType ?? '',
            $auditableId ?? '',
            $actorId ?? '',
            $ip ?? '',
            self::kanonik($before),
            self::kanonik($after),
            $createdAt,
        ]));
    }

    public function expectedHash(): string
    {
        return self::computeHash(
            $this->prev_hash,
            $this->action->value,
            $this->auditable_type,
            $this->auditable_id,
            $this->actor_user_id,
            $this->ip,
            $this->before,
            $this->after,
            $this->created_at->format('Y-m-d H:i:s.u'),
        );
    }

    /**
     * Bentuk teks yang sama untuk isi yang sama, apa pun urutan kuncinya.
     *
     * @param  array<string, mixed>|null  $data
     */
    private static function kanonik(?array $data): string
    {
        if ($data === null) {
            return '';
        }

        $urutkan = static function (array $isi) use (&$urutkan): array {
            ksort($isi);

            foreach ($isi as $kunci => $nilai) {
                if (is_array($nilai)) {
                    $isi[$kunci] = $urutkan($nilai);
                }
            }

            return $isi;
        };

        return json_encode($urutkan($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
