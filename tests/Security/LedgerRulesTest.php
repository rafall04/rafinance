<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Entry;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Reconciliation\Models\PeriodLock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Aturan A2 dan A3 — ditegakkan database, bukan aplikasi
|--------------------------------------------------------------------------
|
| Setiap test di berkas ini sengaja MELANGKAHI service dan menulis lewat SQL
| mentah. Kalau aturannya hanya hidup di PostTransaction, maka satu job yang
| ditulis terburu-buru, satu perintah artisan, atau satu perbaikan data lewat
| tinker sudah cukup untuk merusak pembukuan seseorang tanpa jejak.
|
*/

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

it('menolak entries yang tidak seimbang', function (): void {
    $transaksi = Transaction::factory()->create();

    Entry::query()->create([
        'transaction_id' => $transaksi->getKey(),
        'account_id' => $this->kas->getKey(),
        'amount_minor' => 5_000_000,
    ]);

    // Constraint trigger bersifat DEFERRED, jadi baru meledak saat diperiksa.
    DB::connection('pgsql')->statement('SET CONSTRAINTS entries_must_balance IMMEDIATE');
})->throws(QueryException::class, 'tidak seimbang');

it('menerima entries yang seimbang', function (): void {
    $lain = buatAkun('BCA');
    $transaksi = Transaction::factory()->create();

    Entry::query()->create([
        'transaction_id' => $transaksi->getKey(),
        'account_id' => $this->kas->getKey(),
        'amount_minor' => 5_000_000,
    ]);
    Entry::query()->create([
        'transaction_id' => $transaksi->getKey(),
        'account_id' => $lain->getKey(),
        'amount_minor' => -5_000_000,
    ]);

    DB::connection('pgsql')->statement('SET CONSTRAINTS entries_must_balance IMMEDIATE');
    DB::connection('pgsql')->statement('SET CONSTRAINTS entries_must_balance DEFERRED');

    expect($transaksi->entries()->count())->toBe(2);
});

it('menolak selisih satu sen sekalipun', function (): void {
    $lain = buatAkun('BCA');
    $transaksi = Transaction::factory()->create();

    Entry::query()->create([
        'transaction_id' => $transaksi->getKey(),
        'account_id' => $this->kas->getKey(),
        'amount_minor' => 5_000_000,
    ]);
    Entry::query()->create([
        'transaction_id' => $transaksi->getKey(),
        'account_id' => $lain->getKey(),
        'amount_minor' => -4_999_999,
    ]);

    DB::connection('pgsql')->statement('SET CONSTRAINTS entries_must_balance IMMEDIATE');
})->throws(QueryException::class);

it('menolak UPDATE pada transaksi yang sudah tercatat', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas, 'Bensin');

    DB::connection('pgsql')->update(
        'UPDATE transactions SET description = ? WHERE id = ?',
        ['Diubah diam-diam', $transaksi->getKey()],
    );
})->throws(QueryException::class, 'tidak boleh diubah');

it('menolak DELETE pada transaksi yang sudah tercatat', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);

    DB::connection('pgsql')->delete('DELETE FROM transactions WHERE id = ?', [$transaksi->getKey()]);
})->throws(QueryException::class, 'tidak boleh dihapus');

it('mengizinkan satu-satunya perubahan yang sah: posted menjadi void', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);

    DB::connection('pgsql')->update(
        'UPDATE transactions SET status = ?, voided_at = now() WHERE id = ?',
        [TransactionStatus::Void->value, $transaksi->getKey()],
    );

    expect($transaksi->fresh()->status)->toBe(TransactionStatus::Void);
});

it('menolak penyamaran void yang sekaligus mengubah isinya', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas, 'Bensin');

    DB::connection('pgsql')->update(
        'UPDATE transactions SET status = ?, description = ? WHERE id = ?',
        [TransactionStatus::Void->value, 'Bukan bensin', $transaksi->getKey()],
    );
})->throws(QueryException::class, 'hanya boleh berubah status');

it('menolak perubahan entries milik transaksi tercatat', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);
    $entry = $transaksi->entries()->first();

    DB::connection('pgsql')->update(
        'UPDATE entries SET amount_minor = ? WHERE id = ?',
        [999_999, $entry->getKey()],
    );
})->throws(QueryException::class, 'transaksi posted tidak boleh diubah');

it('menolak penghapusan entries milik transaksi tercatat', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);

    DB::connection('pgsql')->delete(
        'DELETE FROM entries WHERE id = ?',
        [$transaksi->entries()->first()->getKey()],
    );
})->throws(QueryException::class);

it('menolak penambahan entries ke transaksi tercatat', function (): void {
    $transaksi = catatPengeluaran(50_000, $this->kas);

    DB::connection('pgsql')->insert(
        'INSERT INTO entries (id, workspace_id, transaction_id, account_id, amount_minor, sort_order, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 9, now(), now())',
        [(string) Str::ulid(), $this->workspace->getKey(), $transaksi->getKey(), $this->kas->getKey(), 0],
    );
})->throws(QueryException::class);

it('menolak transaksi baru di dalam periode yang sudah dikunci', function (): void {
    PeriodLock::factory()->create([
        'locked_through' => '2026-06-30',
        'locked_by' => $this->pengguna->getKey(),
    ]);

    catatPengeluaran(50_000, $this->kas, 'Terlambat', '2026-06-15');
})->throws(QueryException::class, 'sudah dikunci');

it('menerima transaksi setelah tanggal kunci', function (): void {
    PeriodLock::factory()->create([
        'locked_through' => '2026-06-30',
        'locked_by' => $this->pengguna->getKey(),
    ]);

    $transaksi = catatPengeluaran(50_000, $this->kas, 'Tepat waktu', '2026-07-01');

    expect($transaksi->isPosted())->toBeTrue();
});

it('membiarkan periode yang sudah dibuka kembali menerima tulisan', function (): void {
    $kunci = PeriodLock::factory()->create([
        'locked_through' => '2026-06-30',
        'locked_by' => $this->pengguna->getKey(),
    ]);

    $kunci->forceFill(['reopened_at' => now(), 'reopened_by' => $this->pengguna->getKey()])->save();

    $transaksi = catatPengeluaran(50_000, $this->kas, 'Perbaikan', '2026-06-15');

    expect($transaksi->isPosted())->toBeTrue();
});

it('mengunci periode per workspace, bukan seluruh sistem', function (): void {
    PeriodLock::factory()->create([
        'locked_through' => '2026-06-30',
        'locked_by' => $this->pengguna->getKey(),
    ]);

    [$lain, $workspaceLain] = makeWorkspaceFor();
    $kasLain = buatAkun('Kas');

    $transaksi = catatPengeluaran(50_000, $kasLain, 'Bebas', '2026-06-15');

    expect($transaksi->isPosted())->toBeTrue()
        ->and($transaksi->workspace_id)->toBe($workspaceLain->getKey())
        ->and($lain->getKey())->not->toBeNull();
});

it('tidak bisa menghapus baris audit_logs', function (): void {
    catatPengeluaran(50_000, $this->kas);

    DB::connection('pgsql')->delete('DELETE FROM audit_logs WHERE workspace_id = ?', [$this->workspace->getKey()]);
})->throws(QueryException::class);

it('tidak bisa mengubah baris audit_logs', function (): void {
    catatPengeluaran(50_000, $this->kas);

    DB::connection('pgsql')->update('UPDATE audit_logs SET action = ? WHERE workspace_id = ?', ['palsu', $this->workspace->getKey()]);
})->throws(QueryException::class);
