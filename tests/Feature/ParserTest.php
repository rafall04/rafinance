<?php

declare(strict_types=1);

use App\Domain\Capture\Models\InputAlias;
use App\Domain\Capture\RuleBasedParser;
use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Models\Category;
use App\Domain\Projects\Models\Project;

/*
|--------------------------------------------------------------------------
| Parser rule-based, tanpa satu pun panggilan LLM (aturan A12)
|--------------------------------------------------------------------------
|
| Semua contoh di bagian 7 dokumen rancangan diuji apa adanya di sini. Kalau
| salah satunya berhenti bekerja, jalur input utama Rafin rusak — dan itu jalur
| yang dipakai orang sepuluh kali sehari sambil berdiri di SPBU.
|
*/

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();

    $this->kas = buatAkun('Kas', 1_000_000);
    $this->bca = buatAkun('BCA', 5_000_000, AccountSubtype::Bank);

    $this->transportasi = Category::query()->create(['name' => 'Transportasi', 'kind' => CategoryKind::Expense]);
    Category::query()->create(['name' => 'Makan & minum', 'kind' => CategoryKind::Expense]);
    Category::query()->create(['name' => 'Jasa', 'kind' => CategoryKind::Income]);

    $this->parse = fn (string $teks) => app(RuleBasedParser::class)($teks);
});

it('membaca "50k bensin"', function (): void {
    $draft = ($this->parse)('50k bensin');

    expect($draft->kind)->toBe(TransactionKind::Expense)
        ->and($draft->amount->format())->toBe('Rp 50.000')
        ->and($draft->categoryId)->toBe($this->transportasi->getKey())
        ->and($draft->description)->toBe('Bensin');
});

it('membaca "50rb bensin bca" beserta akunnya', function (): void {
    $draft = ($this->parse)('50rb bensin bca');

    expect($draft->amount->format())->toBe('Rp 50.000')
        ->and($draft->accountId)->toBe($this->bca->getKey())
        ->and($draft->categoryId)->toBe($this->transportasi->getKey())
        ->and($draft->isComplete())->toBeTrue();
});

it('membaca "+2jt dp event pak budi" sebagai pemasukan dengan kontak', function (): void {
    $draft = ($this->parse)('+2jt dp event pak budi');

    expect($draft->kind)->toBe(TransactionKind::Income)
        ->and($draft->amount->format())->toBe('Rp 2.000.000')
        ->and($draft->contactName)->toBe('Pak Budi')
        ->and($draft->description)->toContain('Dp event');
});

it('membaca "150.000 solar genset #eventA" beserta proyeknya', function (): void {
    $proyek = Project::query()->create(['name' => 'eventA', 'status' => 'active']);

    $draft = ($this->parse)('150.000 solar genset #eventA');

    expect($draft->amount->format())->toBe('Rp 150.000')
        ->and($draft->projectTag)->toBe('eventA')
        ->and($draft->projectId)->toBe($proyek->getKey())
        ->and($draft->kind)->toBe(TransactionKind::Expense);
});

it('membaca "pindah 500k kas ke bca" sebagai transfer', function (): void {
    $draft = ($this->parse)('pindah 500k kas ke bca');

    expect($draft->kind)->toBe(TransactionKind::Transfer)
        ->and($draft->amount->format())->toBe('Rp 500.000')
        ->and($draft->accountId)->toBe($this->kas->getKey())
        ->and($draft->toAccountId)->toBe($this->bca->getKey())
        ->and($draft->isComplete())->toBeTrue();
});

it('membaca "bbm" lewat alias buatan pengguna', function (): void {
    InputAlias::query()->create([
        'keyword' => 'bbm',
        'category_id' => $this->transportasi->getKey(),
        'account_id' => $this->kas->getKey(),
    ]);

    $draft = ($this->parse)('bbm 30k');

    expect($draft->categoryId)->toBe($this->transportasi->getKey())
        ->and($draft->accountId)->toBe($this->kas->getKey())
        ->and($draft->amount->format())->toBe('Rp 30.000');
});

it('memahami seluruh akhiran nominal', function (string $teks, string $harapan): void {
    expect(($this->parse)($teks)->amount->format())->toBe($harapan);
})->with([
    ['50k kopi', 'Rp 50.000'],
    ['50rb kopi', 'Rp 50.000'],
    ['50 ribu kopi', 'Rp 50.000'],
    ['2jt sewa', 'Rp 2.000.000'],
    ['2 juta sewa', 'Rp 2.000.000'],
    ['5m modal', 'Rp 5.000.000'],
    ['1,5jt sewa', 'Rp 1.500.000'],
    ['150.000 solar', 'Rp 150.000'],
    ['50000 kopi', 'Rp 50.000'],
]);

it('mengenali kata yang menandakan uang masuk tanpa tanda plus', function (): void {
    expect(($this->parse)('terima 500k bayaran jasa')->kind)->toBe(TransactionKind::Income);
});

it('memilih akun otomatis kalau memang hanya ada satu', function (): void {
    [$lain, $workspaceLain] = makeWorkspaceFor();
    $satunya = buatAkun('Kas');

    expect(($this->parse)('50k bensin')->accountId)->toBe($satunya->getKey())
        ->and($workspaceLain->getKey())->not->toBeNull();
});

it('tidak menebak akun kalau ada lebih dari satu pilihan', function (): void {
    // Menebak akun yang salah lebih merepotkan daripada bertanya sekali.
    $draft = ($this->parse)('50k bensin');

    expect($draft->accountId)->toBeNull()
        ->and($draft->catatan)->toContain('Akun belum disebut.')
        ->and($draft->isComplete())->toBeFalse();
});

it('tidak pernah menolak teks yang tidak dimengerti', function (): void {
    $draft = ($this->parse)('entah apa ini');

    expect($draft->amount)->toBeNull()
        ->and($draft->isComplete())->toBeFalse()
        ->and($draft->rawText)->toBe('entah apa ini')
        ->and($draft->catatan)->toContain('Nominal tidak terbaca.');
});

it('menyimpan teks asli apa adanya untuk memperbaiki parser nanti', function (): void {
    $asli = '  50K   BeNsiN   BCA  ';

    expect(($this->parse)($asli)->rawText)->toBe(trim($asli));
});

it('memilih nama akun terpanjang saat ada yang tumpang tindih', function (): void {
    $bisnis = buatAkun('BCA Bisnis', 0, AccountSubtype::Bank);

    expect(($this->parse)('50k bensin bca bisnis')->accountId)->toBe($bisnis->getKey());
});

it('tidak mengarang kontak dari kata biasa', function (): void {
    // "beli solar genset" tidak boleh melahirkan kontak bernama "Genset".
    expect(($this->parse)('150k solar genset')->contactName)->toBeNull();
});

it('memberi skor keyakinan yang lebih tinggi untuk masukan yang lengkap', function (): void {
    $lengkap = ($this->parse)('50k bensin bca');
    $sepotong = ($this->parse)('bensin');

    expect($lengkap->confidence())->toBeGreaterThan($sepotong->confidence())
        ->and($lengkap->confidence())->toBeGreaterThanOrEqual(0.9);
});
