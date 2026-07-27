<?php

declare(strict_types=1);

namespace App\Livewire\App\Onboarding;

use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Services\SeedWorkspaceDefaults;
use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Enums\WorkspaceType;
use App\Domain\Tenancy\Http\Middleware\SetTenantContext;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Langkah pertama sesudah mendaftar: satu buku untuk mulai mencatat.
 *
 * Sengaja hanya empat isian, dan tiga di antaranya sudah terisi. Orang datang
 * ke sini untuk mencatat pengeluaran, bukan untuk mengonfigurasi perangkat
 * lunak akuntansi. Pilihan akun awal dan penautan Telegram menyusul di FASE 1
 * dan FASE 2, di tempat yang lebih pas.
 */
class BuatWorkspace extends Component
{
    public string $nama = '';

    public string $tipe = 'personal';

    public string $timezone = 'Asia/Jakarta';

    public int $awalPeriode = 1;

    /** @var array<int, string> */
    public array $akunAwal = ['cash'];

    public function mount(): void
    {
        $user = auth()->user();

        // Kalau sudah punya workspace, tidak ada yang perlu dikerjakan di sini.
        if ($user !== null && $user->memberships()->exists()) {
            $this->redirectRoute('app.beranda', navigate: true);

            return;
        }

        $this->timezone = $user?->effectiveTimezone() ?? (string) config('rafin.default_timezone');
        $this->nama = $user !== null ? 'Buku '.strtok($user->name, ' ') : '';
    }

    public function simpan(): void
    {
        $data = $this->validate();

        $user = auth()->user();
        abort_if($user === null, 404);

        $tenant = app(TenantContext::class);
        $tenant->setUserId((string) $user->getKey());

        $workspace = DB::connection('pgsql')->transaction(function () use ($data, $user): Workspace {
            $workspace = Workspace::query()->create([
                'name' => $data['nama'],
                'type' => WorkspaceType::from($data['tipe']),
                'owner_id' => $user->getKey(),
                'currency' => (string) config('rafin.default_currency', 'IDR'),
                'period_start_day' => $data['awalPeriode'],
                'timezone' => $data['timezone'],
            ]);

            WorkspaceMember::query()->create([
                'workspace_id' => $workspace->getKey(),
                'user_id' => $user->getKey(),
                'role' => WorkspaceRole::Owner,
                'joined_at' => now(),
            ]);

            // Konteks tenant harus menunjuk workspace baru sebelum akun dan
            // kategori dibuat, kalau tidak policy RLS akan menolaknya.
            $tenant = app(TenantContext::class);
            $tenant->setWorkspace($workspace);

            app(SeedWorkspaceDefaults::class)($workspace, $data['akunAwal']);

            return $workspace;
        });

        $tenant->setWorkspace($workspace);
        session()->put(SetTenantContext::SESSION_KEY, $workspace->getKey());

        $this->redirectRoute('app.beranda', navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:2', 'max:80'],
            'tipe' => ['required', Rule::enum(WorkspaceType::class)],
            'timezone' => ['required', 'timezone'],
            'awalPeriode' => ['required', 'integer', 'min:1', 'max:28'],
            'akunAwal' => ['required', 'array', 'min:1'],
            'akunAwal.*' => ['string', 'in:cash,bank,ewallet'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nama' => 'nama buku',
            'tipe' => 'jenis buku',
            'timezone' => 'zona waktu',
            'awalPeriode' => 'awal periode',
            'akunAwal' => 'akun awal',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'awalPeriode.max' => 'Awal periode maksimal tanggal 28, supaya setiap bulan punya tanggal itu.',
            'akunAwal.min' => 'Pilih setidaknya satu tempat uang Anda berada.',
        ];
    }

    public function render()
    {
        return view('livewire.onboarding.buat-workspace', [
            'jenisBuku' => WorkspaceType::cases(),
            'jenisAkun' => [
                AccountSubtype::Cash,
                AccountSubtype::Bank,
                AccountSubtype::Ewallet,
            ],
        ])->layout('components.layouts.tamu', ['title' => 'Buat buku']);
    }
}
