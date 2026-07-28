<?php

declare(strict_types=1);

use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Models\AuditLog;
use App\Models\User;
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
        hash: AuditLog::computeHash(
            str_repeat('b', 64),
            AuditAction::TransactionVoided->value,
            null,
            null,
            null,
            null,
            null,
            null,
            $stempel,
        ),
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

/*
 * Isi perubahannya ikut disegel, bukan hanya jenis dan waktunya.
 *
 * Sampai Juli 2026 hash hanya menghitung prev_hash, action, auditable_id, dan
 * created_at. Empat ruas yang paling berharga bagi orang yang ingin merapikan
 * jejaknya — nominal sebelum-sesudah, siapa pelakunya, dan dari alamat mana —
 * berada di luar rantai dan bisa diubah tanpa satu pun hash meleset.
 *
 * Penyuntingannya ditiru DI MEMORI, bukan lewat UPDATE, karena dua jalan lain
 * memang sudah tertutup dan keduanya seharusnya begitu: rafin_app tidak punya
 * hak UPDATE di tabel ini, dan koneksi pemilik skema adalah koneksi terpisah
 * yang tidak bisa melihat baris di dalam transaksi test yang belum di-commit.
 *
 * Yang tersisa justru pertanyaan yang benar: kalau isi sebuah baris berbeda
 * dari saat hash-nya dibuat, apakah hash-nya ikut berbeda? Itulah satu-satunya
 * sifat yang membuat penyuntingan bisa ketahuan, dari mana pun datangnya.
 */

it('memasukkan isi perubahan ke dalam hash', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $baris = AuditLog::query()->whereNotNull('after')->orderByDesc('created_at')->first();
    expect($baris)->not->toBeNull()
        ->and($baris->hash)->toBe($baris->expectedHash());

    $baris->setAttribute('after', ['status' => 'posted', 'nominal_palsu' => 1]);

    expect($baris->expectedHash())->not->toBe($baris->hash);
});

it('memasukkan pelaku dan alamat pemanggil ke dalam hash', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $baris = AuditLog::query()->orderByDesc('created_at')->first();
    $asli = $baris->hash;

    $baris->setAttribute('ip', '203.0.113.9');
    expect($baris->expectedHash())->not->toBe($asli);

    $baris->setAttribute('ip', null);
    $baris->setAttribute('actor_user_id', (string) User::factory()->create()->getKey());
    expect($baris->expectedHash())->not->toBe($asli);
});

it('memasukkan jenis objek yang diaudit ke dalam hash', function (): void {
    catatPengeluaran(50_000, $this->kas);

    $baris = AuditLog::query()->whereNotNull('auditable_type')->orderByDesc('created_at')->first();
    expect($baris)->not->toBeNull();

    $asli = $baris->hash;
    $baris->setAttribute('auditable_type', 'App\\Models\\SesuatuYangLain');

    expect($baris->expectedHash())->not->toBe($asli);
});

it('tidak terpengaruh urutan kunci JSON, yang memang diatur ulang PostgreSQL', function (): void {
    // jsonb menyimpan kunci dalam urutannya sendiri. Kalau hash dihitung dari
    // json_encode apa adanya, baris yang tidak disentuh siapa pun bisa gagal
    // verifikasi hanya karena dibaca ulang — rantai yang menuduh dirinya
    // sendiri, dan alarm palsu yang membuat orang berhenti percaya alarmnya.
    $satu = AuditLog::computeHash(null, 'x', null, null, null, null, ['b' => 2, 'a' => 1], null, '2026-01-01 00:00:00.000000');
    $dua = AuditLog::computeHash(null, 'x', null, null, null, null, ['a' => 1, 'b' => 2], null, '2026-01-01 00:00:00.000000');

    expect($satu)->toBe($dua);
});

it('membedakan ruas yang bergeser, bukan sekadar menyambungnya', function (): void {
    // Penggabungan polos membuat ("ab","c") dan ("a","bc") menghasilkan
    // masukan yang sama. Dengan pemisah, keduanya berbeda.
    $satu = AuditLog::computeHash(null, 'ab', 'c', null, null, null, null, null, '2026-01-01 00:00:00.000000');
    $dua = AuditLog::computeHash(null, 'a', 'bc', null, null, null, null, null, '2026-01-01 00:00:00.000000');

    expect($satu)->not->toBe($dua);
});

it('tetap menyatakan rantai utuh untuk baris yang tidak disentuh siapa pun', function (): void {
    catatPengeluaran(50_000, $this->kas);
    catatPemasukan(20_000, $this->kas);

    $hasil = app(AuditLogger::class)->verify($this->workspace->getKey());

    expect($hasil['ok'])->toBeTrue()
        ->and($hasil['broken'])->toBe([]);
});
