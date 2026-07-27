<?php

declare(strict_types=1);

namespace App\Domain\Logging\Enums;

/**
 * Katalog peristiwa keamanan (bagian 6 dokumen rancangan).
 *
 * Tabel security_events boleh dilihat admin platform. Karena itu isinya HANYA
 * metadata: siapa, dari mana, kapan, dengan perangkat apa. Tidak pernah nominal
 * (aturan A6). Peristiwa yang mengandung angka uang — transaksi dibuat, periode
 * dikunci, tagihan dibayar — tempatnya di audit_logs, yang milik workspace dan
 * tidak pernah bisa dibaca admin platform.
 */
enum SecurityEventType: string
{
    case LoginSuccess = 'login.success';
    case LoginFailed = 'login.failed';
    case Logout = 'logout';
    case SessionNewDevice = 'session.new_device';

    case PasswordChanged = 'password.changed';
    case PasswordReset = 'password.reset';
    case TwoFactorEnabled = '2fa.enabled';
    case TwoFactorDisabled = '2fa.disabled';

    case TelegramLinked = 'telegram.linked';
    case TelegramUnlinked = 'telegram.unlinked';

    case OauthLinked = 'oauth.linked';
    case OauthUnlinked = 'oauth.unlinked';
    case OauthRejected = 'oauth.rejected';

    case MemberInvited = 'member.invited';
    case MemberJoined = 'member.joined';
    case MemberRemoved = 'member.removed';
    case MemberRoleChanged = 'member.role_changed';
    case WorkspaceOwnershipTransferred = 'workspace.ownership_transferred';

    case SupportAccessGranted = 'support_access.granted';
    case SupportAccessUsed = 'support_access.used';
    case SupportAccessExpired = 'support_access.expired';

    case DataExported = 'data.exported';
    case AccountDeletionRequested = 'account.deletion_requested';
    case AdminAction = 'admin.action';

    public function label(): string
    {
        return match ($this) {
            self::LoginSuccess => 'Masuk berhasil',
            self::LoginFailed => 'Percobaan masuk gagal',
            self::Logout => 'Keluar',
            self::SessionNewDevice => 'Perangkat baru',
            self::PasswordChanged => 'Kata sandi diubah',
            self::PasswordReset => 'Kata sandi disetel ulang',
            self::TwoFactorEnabled => 'Verifikasi dua langkah dinyalakan',
            self::TwoFactorDisabled => 'Verifikasi dua langkah dimatikan',
            self::TelegramLinked => 'Telegram dihubungkan',
            self::TelegramUnlinked => 'Telegram diputuskan',
            self::OauthLinked => 'Akun pihak ketiga disambungkan',
            self::OauthUnlinked => 'Akun pihak ketiga diputuskan',
            self::OauthRejected => 'Penyambungan akun ditolak',
            self::MemberInvited => 'Anggota diundang',
            self::MemberJoined => 'Anggota bergabung',
            self::MemberRemoved => 'Anggota dikeluarkan',
            self::MemberRoleChanged => 'Peran anggota diubah',
            self::WorkspaceOwnershipTransferred => 'Kepemilikan workspace dipindahkan',
            self::SupportAccessGranted => 'Akses dukungan diberikan',
            self::SupportAccessUsed => 'Akses dukungan dipakai',
            self::SupportAccessExpired => 'Akses dukungan kedaluwarsa',
            self::DataExported => 'Data diekspor',
            self::AccountDeletionRequested => 'Penghapusan akun diminta',
            self::AdminAction => 'Tindakan admin platform',
        };
    }

    /**
     * Peristiwa yang layak mengganggu pengguna lewat notifikasi Telegram.
     */
    public function shouldNotifyUser(): bool
    {
        return in_array($this, [
            self::SessionNewDevice,
            self::PasswordChanged,
            self::TwoFactorDisabled,
            self::TelegramUnlinked,
            // Penyambungan akun pihak ketiga adalah penambahan cara masuk baru.
            // Pemiliknya harus tahu saat itu terjadi, bukan sesudahnya.
            self::OauthLinked,
            self::OauthUnlinked,
            self::WorkspaceOwnershipTransferred,
            self::SupportAccessUsed,
            self::DataExported,
            self::AccountDeletionRequested,
        ], true);
    }
}
