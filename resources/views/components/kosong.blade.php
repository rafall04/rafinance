@props(['judul', 'ikon' => 'kotak'])

{{--
    Keadaan kosong.

    Layar kosong tanpa penjelasan adalah kegagalan yang paling sering terlewat,
    karena ia hanya terlihat oleh pengguna baru — dan pengembang hampir tidak
    pernah jadi pengguna baru lagi.

    Sebelumnya bentuk ini ditulis ulang di sembilan halaman, dan sembilan-duanya
    sudah mulai berbeda tipis satu sama lain. Sekarang satu tempat.

    Slot `aksi` opsional: kalau ada yang bisa langsung dilakukan pengguna dari
    sini, tombolnya ditaruh di situ.
--}}

@php
    $garis = match ($ikon) {
        // Dompet — belum ada akun.
        'dompet' => '<path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5.5A2.5 2.5 0 0 1 3 16.5v-8Z"/><path d="M3 8.5h13"/><circle cx="17" cy="12.5" r="1.2"/>',
        // Kotak masuk.
        'inbox' => '<path d="M3 13h5l1.5 3h5l1.5-3h5"/><path d="M4.5 5.5h15l1.5 7.5v5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18v-5l1.5-7.5Z"/>',
        // Batang laporan.
        'batang' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        // Dokumen — tagihan, aktivitas.
        'lembar' => '<path d="M14 3v5h5"/><path d="M19 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h9l6 6v11a1 1 0 0 1-1 1Z"/><path d="M8 13h8"/><path d="M8 17h5"/>',
        // Map — proyek.
        'map' => '<path d="M3 7a1 1 0 0 1 1-1h5l2 2.5h8a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z"/>',
        // Target — anggaran.
        'target' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
        // Perisai — keamanan.
        'perisai' => '<path d="M12 3 4.5 6v6c0 4.5 3 7.8 7.5 9 4.5-1.2 7.5-4.5 7.5-9V6L12 3Z"/>',
        default => '<rect x="4" y="6" width="16" height="13" rx="2"/><path d="M4 10h16"/>',
    };
@endphp

<section {{ $attributes->merge(['class' => 'kosong']) }}>
    <span class="kosong-ikon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
             stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
            {!! $garis !!}
        </svg>
    </span>

    <p class="kosong-judul">{{ $judul }}</p>
    <p class="kosong-teks">{{ $slot }}</p>

    @isset($aksi)
        <div class="mt-4">{{ $aksi }}</div>
    @endisset
</section>
