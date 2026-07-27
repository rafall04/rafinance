<?php

declare(strict_types=1);

use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

it('menyambungkan setiap baris ke hash baris sebelumnya', function (): void {
    catatPengeluaran(50_000, $this->kas);
    catatPengeluaran(30_000, $this->kas);

    $baris = AuditLog::query()->orderBy('created_at')->orderBy('id')->get();

    // Akun dibuat, saldo awalnya dicatat (dibuat + dicatat), lalu dua
    // pengeluaran yang masing-masing juga dibuat lalu dicatat.
    expect($baris)->toHaveCount(7);
    expect($baris->first()->prev_hash)->toBeNull();

    $sebelumnya = null;
    foreach ($baris as $satu) {
        expect($satu->prev_hash)->toBe($sebelumnya);
        $sebelumnya = $satu->hash;
    }
});

it('menyimpan mikrodetik supaya hash bisa dihitung ulang', function (): void {
    // Tanpa $dateFormat yang memuat mikrodetik, created_at yang dipakai
    // menghitung hash berbeda dari yang tersimpan, dan setiap baris akan
    // dilaporkan putus meski tidak ada yang menyentuhnya.
    catatPengeluaran(50_000, $this->kas);

    $baris = AuditLog::query()->first();

    expect($baris->hash)->toBe($baris->expectedHash())
        ->and($baris->created_at->format('u'))->not->toBe('000000');
});

it('menyatakan rantai utuh saat tidak ada yang disentuh', function (): void {
    catatPengeluaran(50_000, $this->kas);
    catatPemasukan(200_000, $this->kas);

    $hasil = app(AuditLogger::class)->verify($this->workspace->getKey());

    expect($hasil['ok'])->toBeTrue()
        ->and($hasil['total'])->toBeGreaterThan(0)
        ->and($hasil['broken'])->toBeEmpty();
});

/*
 * Dua test berikut memalsukan baris lewat INSERT langsung, bukan lewat UPDATE.
 *
 * Itu bukan jalan pintas — justru itulah ancaman yang sebenarnya. Role aplikasi
 * memang tidak punya hak UPDATE maupun DELETE di audit_logs (sudah diuji di
 * LedgerRulesTest), tapi ia HARUS punya hak INSERT untuk bisa mencatat apa pun.
 * Pertanyaannya jadi: bisakah seseorang yang hanya bisa menambah, menambahkan
 * sesuatu yang terlihat sah?
 */

it('menunjuk baris palsu yang hash-nya tidak cocok dengan isinya', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $terakhir = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id')->first();
    $idPalsu = (string) Str::ulid();

    // prev_hash disambungkan dengan benar, tapi hash-nya dikarang.
    sisipkanBarisAudit(
        id: $idPalsu,
        workspaceId: $this->workspace->getKey(),
        action: AuditAction::TransactionVoided->value,
        prevHash: $terakhir->hash,
        hash: str_repeat('a', 64),
    );

    $hasil = app(AuditLogger::class)->verify($this->workspace->getKey());

    expect($hasil['ok'])->toBeFalse()
        ->and($hasil['broken'])->toHaveCount(1)
        ->and($hasil['broken'][0]['id'])->toBe($idPalsu)
        ->and($hasil['broken'][0]['alasan'])->toContain('tidak cocok dengan hash');
});

it('menunjuk baris yang tidak menyambung ke rantai', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $idPalsu = (string) Str::ulid();
    $stempel = now()->addSecond()->format('Y-m-d H:i:s.u');

    // Hash-nya dihitung benar untuk isinya sendiri, tapi prev_hash-nya menunjuk
    // ke sesuatu yang bukan baris terakhir — persis yang terjadi kalau ada baris
    // di tengah yang dihilangkan.
    sisipkanBarisAudit(
        id: $idPalsu,
        workspaceId: $this->workspace->getKey(),
        action: AuditAction::TransactionVoided->value,
        prevHash: str_repeat('b', 64),
        hash: AuditLog::computeHash(str_repeat('b', 64), AuditAction::TransactionVoided->value, null, $stempel),
        createdAt: $stempel,
    );

    $hasil = app(AuditLogger::class)->verify($this->workspace->getKey());

    expect($hasil['ok'])->toBeFalse()
        ->and($hasil['broken'][0]['id'])->toBe($idPalsu)
        ->and($hasil['broken'][0]['alasan'])->toContain('tidak menyambung');
});

function sisipkanBarisAudit(
    string $id,
    string $workspaceId,
    string $action,
    ?string $prevHash,
    string $hash,
    ?string $createdAt = null,
): void {
    DB::connection('pgsql')->insert(
        'INSERT INTO audit_logs (id, workspace_id, action, prev_hash, hash, created_at)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $workspaceId, $action, $prevHash, $hash, $createdAt ?? now()->addSecond()->format('Y-m-d H:i:s.u')],
    );
}

it('memisahkan rantai antar workspace', function (): void {
    catatPengeluaran(50_000, $this->kas);
    $hashSatu = AuditLog::query()->orderByDesc('created_at')->first()->hash;

    [, $workspaceDua] = makeWorkspaceFor();
    $kasDua = buatAkun('Kas');
    catatPengeluaran(10_000, $kasDua);

    $pertamaDiWorkspaceDua = AuditLog::query()->orderBy('created_at')->orderBy('id')->first();

    // Rantai workspace kedua dimulai dari nol, bukan menyambung ke yang pertama.
    expect($pertamaDiWorkspaceDua->prev_hash)->toBeNull()
        ->and($pertamaDiWorkspaceDua->hash)->not->toBe($hashSatu);

    expect(app(AuditLogger::class)->verify($workspaceDua->getKey())['ok'])->toBeTrue()
        ->and(app(AuditLogger::class)->verify($this->workspace->getKey())['ok'])->toBeTrue();
});

it('menolak mengubah baris audit lewat Eloquent dengan pesan yang jelas', function (): void {
    catatPengeluaran(50_000, $this->kas);

    AuditLog::query()->first()->update(['action' => AuditAction::TransactionVoided]);
})->throws(RuntimeException::class, 'hanya bisa ditambah');

it('menolak menghapus baris audit lewat Eloquent', function (): void {
    catatPengeluaran(50_000, $this->kas);

    AuditLog::query()->first()->delete();
})->throws(RuntimeException::class, 'tidak bisa dihapus');

it('menyembunyikan jejak audit dari workspace lain', function (): void {
    catatPengeluaran(50_000, $this->kas);
    $jumlahSatu = AuditLog::query()->count();

    [$dua, $workspaceDua] = makeWorkspaceFor();
    actingInWorkspace($workspaceDua, $dua);

    // audit_logs memuat nominal, jadi RLS-nya sama ketatnya dengan transaksi.
    expect(AuditLog::query()->count())->toBe(0)
        ->and($jumlahSatu)->toBeGreaterThan(0);
});
