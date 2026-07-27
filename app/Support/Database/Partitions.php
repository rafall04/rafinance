<?php

declare(strict_types=1);

namespace App\Support\Database;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Partisi bulanan untuk tabel jejak (security_events, nanti audit_logs).
 *
 * Dibuat jauh di depan dengan sengaja. Partisi yang terlambat dibuat membuat
 * baris jatuh ke partisi DEFAULT, dan begitu ada baris di sana, PostgreSQL
 * menolak melampirkan partisi baru yang rentangnya bertabrakan — persoalan
 * kecil yang berubah jadi operasi manual di jam yang salah.
 */
final class Partitions
{
    /**
     * Memastikan partisi bulanan tersedia mulai bulan $from sejumlah $months.
     *
     * Aman dipanggil berulang: partisi yang sudah ada dilewati.
     *
     * @return array<int, string> nama partisi yang baru dibuat
     */
    public static function ensureMonthly(string $table, DateTimeInterface $from, int $months = 6, bool $appendOnly = false): array
    {
        self::assertIdentifier($table);

        if ($months < 1) {
            throw new InvalidArgumentException('Jumlah bulan harus setidaknya satu.');
        }

        $connection = Schema::getConnection();
        $start = CarbonImmutable::instance($from)->startOfMonth();
        $created = [];

        for ($i = 0; $i < $months; $i++) {
            $periodStart = $start->addMonths($i);
            $periodEnd = $periodStart->addMonth();
            $name = sprintf('%s_p%s', $table, $periodStart->format('Y_m'));

            if (self::exists($name)) {
                continue;
            }

            $connection->statement(sprintf(
                'CREATE TABLE %s PARTITION OF %s FOR VALUES FROM (%s) TO (%s)',
                $name,
                $table,
                "'".$periodStart->format('Y-m-d 00:00:00')."'",
                "'".$periodEnd->format('Y-m-d 00:00:00')."'",
            ));

            if ($appendOnly) {
                self::revokeMutation($name);
            }

            $created[] = $name;
        }

        return $created;
    }

    /**
     * Mencabut hak UPDATE dan DELETE dari role aplikasi.
     *
     * Perlu dinyatakan terang-terangan karena ALTER DEFAULT PRIVILEGES di level
     * database memberikan DML penuh pada setiap tabel baru. Policy RLS saja
     * tidak cukup: tanpa policy, PostgreSQL menyaring barisnya diam-diam, jadi
     * `DELETE FROM audit_logs` akan berhasil dengan nol baris terhapus dan
     * tidak ada yang tahu bahwa upaya itu pernah terjadi.
     */
    public static function revokeMutation(string $table): void
    {
        self::assertIdentifier($table);

        $role = config('database.connections.pgsql.username');

        if (! is_string($role) || preg_match('/^[a-z_][a-z0-9_]*$/i', $role) !== 1) {
            return;
        }

        Schema::getConnection()->statement("REVOKE UPDATE, DELETE, TRUNCATE ON {$table} FROM {$role}");
    }

    /**
     * Seluruh nama partisi anak dari sebuah tabel terpartisi.
     *
     * @return array<int, string>
     */
    public static function childrenOf(string $table): array
    {
        self::assertIdentifier($table);

        $rows = Schema::getConnection()->select(
            <<<'SQL'
                SELECT c.relname AS name
                FROM pg_inherits i
                JOIN pg_class c ON c.oid = i.inhrelid
                JOIN pg_class p ON p.oid = i.inhparent
                WHERE p.relname = ?
            SQL,
            [$table],
        );

        return array_map(static fn (object $row): string => (string) $row->name, $rows);
    }

    /**
     * Melepas partisi yang seluruhnya lebih tua dari $before, lalu membuangnya.
     *
     * @return array<int, string> nama partisi yang dihapus
     */
    public static function dropMonthlyBefore(string $table, DateTimeInterface $before): array
    {
        self::assertIdentifier($table);

        $connection = Schema::getConnection();
        $cutoff = CarbonImmutable::instance($before)->startOfMonth();
        $dropped = [];

        $partitions = $connection->select(
            <<<'SQL'
                SELECT c.relname AS name
                FROM pg_inherits i
                JOIN pg_class c ON c.oid = i.inhrelid
                JOIN pg_class p ON p.oid = i.inhparent
                WHERE p.relname = ?
            SQL,
            [$table],
        );

        foreach ($partitions as $partition) {
            $name = (string) $partition->name;

            if (preg_match('/_p(\d{4})_(\d{2})$/', $name, $m) !== 1) {
                continue;
            }

            $periodStart = CarbonImmutable::create((int) $m[1], (int) $m[2], 1);

            if ($periodStart !== null && $periodStart->addMonth()->lessThanOrEqualTo($cutoff)) {
                $connection->statement("DROP TABLE IF EXISTS {$name}");
                $dropped[] = $name;
            }
        }

        return $dropped;
    }

    /**
     * Berapa baris yang tersesat di partisi DEFAULT. Selalu nol kalau partisi
     * dibuat tepat waktu; kalau tidak nol, ada jadwal yang tidak berjalan.
     */
    public static function defaultPartitionCount(string $table): int
    {
        self::assertIdentifier($table);

        $name = "{$table}_default";

        if (! self::exists($name)) {
            return 0;
        }

        $row = Schema::getConnection()->selectOne("SELECT count(*) AS total FROM {$name}");

        return (int) ($row->total ?? 0);
    }

    private static function exists(string $name): bool
    {
        // relkind 'r' tabel biasa (partisi anak), 'p' tabel terpartisi (induk).
        return Schema::getConnection()->selectOne(
            "SELECT 1 FROM pg_class WHERE relname = ? AND relkind IN ('r', 'p')",
            [$name],
        ) !== null;
    }

    private static function assertIdentifier(string $name): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nama tabel tidak sah: {$name}");
        }
    }
}
