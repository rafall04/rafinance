@props([
    'uang' => null,
    'arah' => null,
    'besar' => false,
    'simbol' => true,
])

{{--
    Satu-satunya cara menampilkan nominal di Rafin.

    Angkanya dibungkus <span class="angka"> supaya mode privasi bisa menutupinya
    lewat CSS saja, tanpa memuat ulang dan tanpa nominalnya pernah singgah di
    DOM dalam keadaan terlihat setelah tombol ditekan.

    <data value> membawa nilai minor unit yang tepat untuk pembaca layar dan
    untuk salin-tempel, sementara yang terlihat tetap format Indonesia.
--}}
@php
    $kelasArah = match ($arah) {
        'masuk' => 'nominal-masuk',
        'keluar' => 'nominal-keluar',
        'transfer' => 'nominal-transfer',
        default => '',
    };

    $teks = $uang === null
        ? '—'
        : ($simbol ? $uang->format() : $uang->formatPlain());
@endphp

<data
    {{ $attributes->class(['nominal', 'nominal-lg' => $besar, $kelasArah]) }}
    @if ($uang !== null) value="{{ $uang->minor }}" @endif
>
    <span class="angka">{{ $teks }}</span>
</data>
