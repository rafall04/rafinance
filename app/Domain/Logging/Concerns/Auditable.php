<?php

declare(strict_types=1);

namespace App\Domain\Logging\Concerns;

use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use UnitEnum;

/**
 * Mencatat perubahan lewat event model, bukan dari controller.
 *
 * Bagian 6 dokumen rancangan menuntut ini, dan alasannya sama dengan alasan
 * SecurityLogger memakai event listener: jalur menulis transaksi ada banyak —
 * web, Telegram, antrean offline, impor CSV, aturan berulang. Pencatatan yang
 * menempel di controller akan bocor di jalur yang ditambahkan besok.
 *
 * Model yang memakai trait ini wajib menyediakan auditActionFor(), dan boleh
 * mengembalikan null untuk peristiwa yang tidak perlu dicatat.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created', null, $model->auditSnapshot());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            // getRawOriginal(), bukan getOriginal(): nilai mentah seperti
            // tersimpan di kolom, sehingga bentuk "sebelum" dan "sesudah"
            // bisa dibandingkan langsung tanpa terpengaruh cast yang mungkin
            // berubah di kemudian hari.
            $before = array_intersect_key($model->getRawOriginal(), $changes);

            $model->writeAuditLog('updated', $before, $changes);
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditLog('deleted', $model->auditSnapshot(), null);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    abstract protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function writeAuditLog(string $event, ?array $before, ?array $after): void
    {
        $action = $this->auditActionFor($event, $before, $after);

        if ($action === null) {
            return;
        }

        app(AuditLogger::class)->record(
            action: $action,
            auditable: $this,
            before: $before === null ? null : $this->auditCastable($before),
            after: $after === null ? null : $this->auditCastable($after),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditSnapshot(): array
    {
        return $this->auditCastable($this->getAttributes());
    }

    /**
     * Menyiapkan nilai atribut supaya bisa disimpan sebagai JSONB.
     *
     * getOriginal() mengembalikan nilai yang SUDAH melewati cast, jadi isinya
     * bisa berupa enum, Money, atau tanggal — bukan hanya skalar. Money sengaja
     * disimpan sebagai {minor, currency} dan bukan sebagai teks terformat:
     * riwayat harus bisa dibandingkan angka-per-angka bertahun-tahun kemudian,
     * bahkan kalau format tampilannya berubah.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function auditCastable(array $data): array
    {
        unset($data['created_at'], $data['updated_at']);

        return array_map(static function (mixed $value): mixed {
            return match (true) {
                $value === null, is_scalar($value), is_array($value) => $value,
                $value instanceof BackedEnum => $value->value,
                $value instanceof UnitEnum => $value->name,
                $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
                $value instanceof JsonSerializable => $value->jsonSerialize(),
                is_object($value) && method_exists($value, '__toString') => (string) $value,
                default => json_decode(json_encode($value) ?: 'null', true),
            };
        }, $data);
    }
}
