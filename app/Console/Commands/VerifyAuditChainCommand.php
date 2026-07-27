<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Logging\AuditLogger;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Memeriksa keutuhan rantai hash audit_logs.
 *
 * Perintah ini tidak mencegah apa pun. Ia menjawab satu pertanyaan yang tidak
 * bisa dijawab dengan cara lain: apakah jejak yang saya lihat hari ini sama
 * dengan yang ditulis dulu.
 */
final class VerifyAuditChainCommand extends Command
{
    protected $signature = 'rafin:audit:verify
        {workspace? : ID workspace tertentu; kosongkan untuk memeriksa semuanya}';

    protected $description = 'Memeriksa rantai hash audit_logs';

    public function handle(AuditLogger $audit, TenantContext $tenant): int
    {
        $ids = $this->argument('workspace') !== null
            ? [(string) $this->argument('workspace')]
            : $this->semuaWorkspaceId();

        if ($ids === []) {
            $this->components->info('Belum ada workspace untuk diperiksa.');

            return self::SUCCESS;
        }

        $adaYangPutus = false;

        foreach ($ids as $id) {
            $hasil = $audit->verify($id);

            if ($hasil['total'] === 0) {
                $this->components->info("{$id}: belum ada jejak audit.");

                continue;
            }

            if ($hasil['ok']) {
                $this->components->info("{$id}: {$hasil['total']} baris, rantai utuh.");

                continue;
            }

            $adaYangPutus = true;
            $this->components->error("{$id}: rantai PUTUS pada ".count($hasil['broken']).' baris.');

            foreach ($hasil['broken'] as $baris) {
                $this->line("  {$baris['id']} — {$baris['alasan']}");
            }
        }

        if ($adaYangPutus) {
            $this->newLine();
            $this->components->warn(
                'Rantai yang putus berarti audit_logs pernah disunting di luar aplikasi, '
                .'atau baris pertama setelah titik putus ditulis bersamaan tanpa kunci. '
                .'Periksa akses langsung ke database sebelum menyimpulkan yang pertama.'
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function semuaWorkspaceId(): array
    {
        // Daftar workspace dibaca lewat koneksi pemilik skema: perintah ini
        // berjalan tanpa konteks tenant, dan RLS memang menyembunyikan semuanya
        // dalam keadaan itu.
        return Workspace::on(MigrateCommand::CONNECTION)
            ->withoutGlobalScopes()
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }
}
