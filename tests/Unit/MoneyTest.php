<?php

declare(strict_types=1);

use App\Support\Currency;
use App\Support\Money;

it('menyimpan rupiah sebagai minor unit', function (): void {
    expect(Money::ofMajor(50_000, 'IDR')->minor)->toBe(5_000_000)
        ->and(Currency::minorUnit('IDR'))->toBe(2);
});

it('tidak punya jalur konstruksi dari float', function (): void {
    // Aturan A1 ditegakkan oleh tipe, bukan oleh niat baik pemanggil.
    $constructors = ['ofMinor', 'ofMajor', 'zero', 'parse'];

    foreach ($constructors as $name) {
        $method = new ReflectionMethod(Money::class, $name);

        foreach ($method->getParameters() as $parameter) {
            $type = (string) $parameter->getType();

            expect($type)->not->toContain('float', "Money::{$name}() menerima float lewat \${$parameter->getName()}");
        }
    }

    expect((new ReflectionClass(Money::class))->getConstructor()->isPrivate())->toBeTrue();
});

it('menolak float saat dijalankan', function (): void {
    Money::ofMinor(1.5, 'IDR'); // @phpstan-ignore-line
})->throws(TypeError::class);

it('membaca angka bertitik sebagai pemisah ribuan', function (string $input, int $expected): void {
    expect(Money::parse($input, 'IDR')->minor)->toBe($expected);
})->with([
    ['50000', 5_000_000],
    ['50.000', 5_000_000],
    ['1.234.567', 123_456_700],
    ['50.000.000', 5_000_000_000],
    ['0', 0],
]);

it('membaca koma sebagai desimal', function (string $input, int $expected): void {
    expect(Money::parse($input, 'IDR')->minor)->toBe($expected);
})->with([
    ['50000,50', 5_000_050],
    ['1.234.567,89', 123_456_789],
    ['50,5', 5_050],
    ['-2.000,25', -200_025],
    ['+2000', 200_000],
]);

it('menolak teks yang bukan nominal', function (string $input): void {
    Money::parse($input, 'IDR');
})->with(['', 'bensin', '50rb', 'Rp50.000'])->throws(InvalidArgumentException::class);

it('menjumlah dan mengurangi', function (): void {
    $a = Money::ofMajor(50_000, 'IDR');
    $b = Money::ofMajor(20_000, 'IDR');

    expect($a->plus($b)->minor)->toBe(7_000_000)
        ->and($a->minus($b)->minor)->toBe(3_000_000)
        ->and($a->negate()->minor)->toBe(-5_000_000)
        ->and($a->negate()->abs()->minor)->toBe(5_000_000)
        ->and(Money::zero('IDR')->isZero())->toBeTrue()
        ->and($a->isPositive())->toBeTrue()
        ->and($a->negate()->isNegative())->toBeTrue();
});

it('menolak mencampur mata uang', function (): void {
    Money::ofMajor(1, 'IDR')->plus(Money::ofMajor(1, 'USD'));
})->throws(InvalidArgumentException::class);

it('membagi tanpa kehilangan satu sen pun', function (): void {
    // Syarat mutlak untuk entries: jumlah pecahan wajib persis sama dengan
    // nominal asal, kalau tidak trigger keseimbangan akan menolaknya (A2).
    $total = Money::ofMinor(100, 'IDR');
    $parts = $total->allocate(1, 1, 1);

    $sum = array_reduce(
        $parts,
        fn (Money $carry, Money $part): Money => $carry->plus($part),
        Money::zero('IDR'),
    );

    expect($sum->minor)->toBe(100)
        ->and(array_map(fn (Money $p): int => $p->minor, $parts))->toBe([34, 33, 33]);
});

it('membagi nominal negatif tanpa kehilangan sen', function (): void {
    $parts = Money::ofMinor(-100, 'IDR')->allocate(1, 1, 1);

    $sum = array_reduce(
        $parts,
        fn (Money $carry, Money $part): Money => $carry->plus($part),
        Money::zero('IDR'),
    );

    expect($sum->minor)->toBe(-100);
});

it('menulis rupiah dengan pemisah titik dan tanpa desimal', function (): void {
    expect(Money::ofMajor(1_240_000, 'IDR')->format())->toBe('Rp 1.240.000')
        ->and(Money::ofMajor(1_240_000, 'IDR')->formatPlain())->toBe('1.240.000')
        ->and(Money::ofMajor(0, 'IDR')->format())->toBe('Rp 0')
        ->and(Money::ofMajor(-50_000, 'IDR')->formatPlain())->toBe('-50.000');
});

it('menulis mata uang berdesimal apa adanya', function (): void {
    expect(Money::ofMinor(123_456, 'USD')->format())->toBe('$ 1.234,56');
});

it('menolak mata uang yang tidak dikenal', function (): void {
    Money::ofMajor(1, 'XYZ');
})->throws(InvalidArgumentException::class);

/*
 * Pengelompokan ribuan tanpa melewati float.
 *
 * number_format() menerima float, dan seluruh kelas Money ada justru untuk
 * memastikan nominal tidak pernah menyentuhnya. Di atas 2^53 minor unit,
 * pembulatan IEEE-754 mulai mengarang digit.
 */

it('mengelompokkan ribuan dengan tepat sampai batas presisi float', function (): void {
    // 2^53 - 1 minor unit. Di sinilah float mulai kehilangan digit terakhirnya.
    $nominal = Money::ofMinor(900719925474099100, 'IDR');

    expect($nominal->formatPlain())->toBe('9.007.199.254.740.991');
});

it('mengelompokkan ribuan dengan benar di setiap panjang angka', function (int $minor, string $harapan): void {
    expect(Money::ofMinor($minor, 'IDR')->formatPlain())->toBe($harapan);
})->with([
    [0, '0'],
    [100, '1'],
    [99_900, '999'],
    [100_000, '1.000'],
    [5_000_000, '50.000'],
    [123_456_789_012, '1.234.567.890'],
]);

it('menaruh tanda minus di depan, bukan di tengah kelompok', function (): void {
    expect(Money::ofMinor(-123_456_700, 'IDR')->formatPlain())->toBe('-1.234.567');
});
