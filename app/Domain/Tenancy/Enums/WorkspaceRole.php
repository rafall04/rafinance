<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

/**
 * Peran di DALAM sebuah workspace.
 *
 * Perlu ditegaskan karena mudah tertukar: `manager` di sini adalah admin
 * workspace — orang yang mengelola pembukuan sebuah usaha. Ia tidak punya
 * hubungan apa pun dengan admin platform Rafin, yang justru sama sekali tidak
 * boleh melihat nominal transaksi siapa pun (aturan A5).
 *
 * `input_only` adalah peran yang paling menentukan bentuk produk ini: orang
 * lapangan yang mencatat pengeluaran tapi tidak boleh melihat pembukuan.
 * Operator RT-RW net punya teknisi; tim produksi event punya kru. Mereka perlu
 * mencatat, bukan melihat margin.
 */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Editor = 'editor';
    case Viewer = 'viewer';
    case InputOnly = 'input_only';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Manager => 'Pengelola',
            self::Editor => 'Pencatat',
            self::Viewer => 'Pengamat',
            self::InputOnly => 'Input saja',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Akses penuh, termasuk penagihan, anggota, dan penutupan workspace.',
            self::Manager => 'Mengelola pembukuan dan anggota, tanpa akses penagihan.',
            self::Editor => 'Mencatat dan mengubah transaksi, tanpa mengelola anggota.',
            self::Viewer => 'Hanya melihat pembukuan dan laporan.',
            self::InputOnly => 'Hanya mencatat. Tidak bisa melihat pembukuan maupun laporan.',
        };
    }

    /**
     * Boleh melihat buku besar, saldo, dan laporan.
     */
    public function canRead(): bool
    {
        return $this !== self::InputOnly;
    }

    /**
     * Boleh membuat transaksi baru — termasuk lewat Telegram.
     */
    public function canCapture(): bool
    {
        return $this !== self::Viewer;
    }

    /**
     * Boleh membatalkan transaksi, mengubah kategori, mengunci periode.
     */
    public function canManageLedger(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor], true);
    }

    /**
     * Boleh mengundang, mengubah peran, dan mencabut akses anggota.
     */
    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /**
     * Boleh mengunci dan membuka periode pembukuan.
     */
    public function canLockPeriod(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /**
     * Boleh mengekspor seluruh data workspace.
     *
     * Sengaja disempitkan: ekspor adalah satu-satunya jalur data keluar utuh,
     * jadi ia diperlakukan sebagai peristiwa keamanan, bukan fitur biasa.
     */
    public function canExport(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /**
     * Boleh mengurus langganan, memindahkan kepemilikan, menghapus workspace.
     */
    public function canManageWorkspace(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Peran yang boleh diberikan lewat undangan — pemilik hanya berpindah
     * lewat alur pemindahan kepemilikan yang terpisah dan tercatat.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Manager, self::Editor, self::Viewer, self::InputOnly];
    }
}
