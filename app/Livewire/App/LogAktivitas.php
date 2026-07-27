<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Models\AuditLog;
use App\Domain\Tenancy\TenantContext;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat lengkap perubahan di dalam buku ini.
 *
 * Bukan hanya daftar — rantai hash-nya bisa diperiksa dari halaman ini juga.
 * Buku yang bilang "percaya saja" tidak berbeda dari catatan di kertas; yang
 * membedakan adalah kemampuan membuktikan bahwa isinya belum disentuh.
 *
 * audit_logs adalah milik workspace dan memuat nominal. Admin platform tidak
 * pernah bisa melihat halaman ini maupun tabelnya (aturan A5).
 */
class LogAktivitas extends Component
{
    use WithPagination;

    #[Url(as: 'aksi', except: '')]
    public string $saringAksi = '';

    /** @var array{ok: bool, total: int, broken: array<int, array{id: string, alasan: string}>}|null */
    public ?array $hasilVerifikasi = null;

    public function periksaRantai(): void
    {
        $workspace = app(TenantContext::class)->workspace();
        abort_if($workspace === null, 404);

        $this->hasilVerifikasi = app(AuditLogger::class)->verify((string) $workspace->getKey());
    }

    public function saring(string $aksi = ''): void
    {
        $this->saringAksi = $this->saringAksi === $aksi ? '' : $aksi;
        $this->resetPage();
    }

    public function render()
    {
        $query = AuditLog::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($this->saringAksi !== '') {
            $query->where('action', $this->saringAksi);
        }

        return view('livewire.app.log-aktivitas', [
            'baris' => $query->paginate(30),
            'aksiTersedia' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->pluck('action')
                ->map(fn (mixed $a): ?AuditAction => $a instanceof AuditAction ? $a : AuditAction::tryFrom((string) $a))
                ->filter()
                ->values(),
        ])->layout('components.layouts.app', ['title' => 'Log aktivitas']);
    }
}
