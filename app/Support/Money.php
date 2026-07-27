<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Nominal uang sebagai bilangan bulat minor unit (aturan A1).
 *
 * Kelas ini sengaja TIDAK punya satu pun jalur konstruksi dari float. Bukan
 * karena float "kurang presisi" secara abstrak, tapi karena 0,1 + 0,2 tidak
 * sama dengan 0,3 di IEEE-754, dan selisih satu sen yang muncul entah dari
 * mana adalah cara tercepat membuat pengguna berhenti mempercayai pembukuan.
 *
 * Semua berkas di app/ memakai declare(strict_types=1) — ditegakkan oleh arch
 * test — sehingga Money::ofMinor(1.5) melempar TypeError, bukan diam-diam
 * memotong jadi 1.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $minor,
        public string $currency,
    ) {}

    /**
     * Membuat Money dari minor unit. Rp 50.000 => ofMinor(5_000_000).
     */
    public static function ofMinor(int $minor, string $currency): self
    {
        return new self($minor, Currency::normalize($currency));
    }

    /**
     * Membuat Money dari satuan utuh. Rp 50.000 => ofMajor(50_000).
     *
     * Hanya menerima int. Untuk nilai berdesimal pakai parse().
     */
    public static function ofMajor(int $major, string $currency): self
    {
        $currency = Currency::normalize($currency);

        return new self($major * Currency::factor($currency), $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, Currency::normalize($currency));
    }

    /**
     * Membaca nominal dari string, tanpa pernah menyentuh float.
     *
     * Menerima "50000", "50.000", "50000,50", "-1.234.567,89", "+2000".
     * Titik dan koma keduanya diterima: pengguna Indonesia menulis titik
     * sebagai pemisah ribuan, tapi papan ketik ponsel sering menghasilkan
     * yang sebaliknya. Pemisah terakhir yang diikuti 1-2 digit dianggap
     * desimal; selebihnya dianggap pemisah ribuan.
     */
    public static function parse(string $value, string $currency): self
    {
        $currency = Currency::normalize($currency);
        $minorUnit = Currency::minorUnit($currency);

        $raw = trim($value);
        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');
        $raw = preg_replace('/\s+/u', '', $raw) ?? '';

        if ($raw === '' || preg_match('/^[0-9.,]+$/', $raw) !== 1) {
            throw new InvalidArgumentException("Bukan nominal yang bisa dibaca: {$value}");
        }

        // Pemisah ribuan Indonesia selalu diikuti tepat tiga digit. Jadi
        // pemisah terakhir yang diikuti satu atau dua digit pasti desimal,
        // titik maupun koma. "50.000" lima puluh ribu; "50000,50" lima puluh
        // ribu lima puluh sen; "1.234.567,89" keduanya sekaligus.
        $decimals = '';
        if ($minorUnit > 0 && preg_match('/^(.*)[.,]([0-9]{1,2})$/', $raw, $m) === 1) {
            $raw = $m[1];
            $decimals = $m[2];
        }

        $whole = str_replace(['.', ','], '', $raw);

        if ($whole === '') {
            $whole = '0';
        }

        if (preg_match('/^[0-9]+$/', $whole) !== 1) {
            throw new InvalidArgumentException("Bukan nominal yang bisa dibaca: {$value}");
        }

        $decimals = str_pad($decimals, $minorUnit, '0');
        if (strlen($decimals) > $minorUnit) {
            $decimals = substr($decimals, 0, $minorUnit);
        }

        $minor = (int) ($whole.$decimals);

        return new self($negative ? -$minor : $minor, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function multipliedBy(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    /**
     * Membagi nominal ke beberapa bagian tanpa kehilangan satu sen pun.
     *
     * Sisa pembagian dibagikan satu per satu ke bagian paling depan, sehingga
     * jumlah hasilnya selalu persis sama dengan nominal asal — syarat mutlak
     * kalau hasilnya akan jadi entries dalam satu transaksi (aturan A2).
     *
     * @return array<int, self>
     */
    public function allocate(int ...$ratios): array
    {
        $total = array_sum($ratios);

        if ($total <= 0) {
            throw new InvalidArgumentException('Rasio pembagian harus berjumlah lebih dari nol.');
        }

        $shares = [];
        $remainder = $this->minor;

        foreach ($ratios as $ratio) {
            $share = intdiv($this->minor * $ratio, $total);
            $shares[] = $share;
            $remainder -= $share;
        }

        for ($i = 0; $remainder !== 0; $i++) {
            $step = $remainder > 0 ? 1 : -1;
            $shares[$i % count($shares)] += $step;
            $remainder -= $step;
        }

        return array_map(fn (int $share): self => new self($share, $this->currency), $shares);
    }

    /**
     * "Rp 1.240.000"
     */
    public function format(): string
    {
        return Currency::symbol($this->currency).' '.$this->formatPlain();
    }

    /**
     * "1.240.000" — tanpa simbol, untuk kolom saldo rail yang simbolnya sudah
     * jelas dari kepala kolom.
     */
    public function formatPlain(): string
    {
        $decimals = Currency::displayDecimals($this->currency);
        $factor = Currency::factor($this->currency);

        $minor = abs($this->minor);
        $whole = intdiv($minor, $factor);
        $sign = $this->minor < 0 ? '-' : '';

        $text = number_format((float) $whole, 0, ',', '.');

        if ($decimals > 0) {
            $fraction = $minor % $factor;
            $text .= ','.substr(str_pad((string) $fraction, Currency::minorUnit($this->currency), '0', STR_PAD_LEFT), 0, $decimals);
        }

        return $sign.$text;
    }

    /**
     * "+Rp 20.000" / "−Rp 50.000" — dipakai hanya di tempat yang butuh tanda
     * eksplisit. Di daftar transaksi, arah uang disampaikan lewat warna.
     */
    public function formatSigned(): string
    {
        $prefix = $this->minor < 0 ? '−' : '+';

        return $prefix.Currency::symbol($this->currency).' '.$this->abs()->formatPlain();
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * @return array{minor: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Tidak bisa menggabungkan {$this->currency} dengan {$other->currency}."
            );
        }
    }
}
