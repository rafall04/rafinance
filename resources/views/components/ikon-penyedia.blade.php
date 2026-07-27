@props(['provider'])

{{--
    Lambang penyedia, digambar langsung sebagai SVG.

    Bukan dari pustaka ikon: satu paket berarti puluhan kilobyte untuk tiga
    gambar, dan anggaran JavaScript di sini dibelanjakan untuk antrean offline.

    Lambang Google memakai empat warna resminya — pedoman merek Google memang
    menuntut itu, dan lambang satu warna terlihat seperti tiruan.
--}}
@switch($provider->value)
    @case('google')
        <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" aria-hidden="true">
            <path fill="#4285F4" d="M23.5 12.3c0-.9-.1-1.7-.2-2.5H12v4.8h6.5a5.6 5.6 0 0 1-2.4 3.7v3h3.9c2.3-2.1 3.5-5.2 3.5-9z"/>
            <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1.1.7-2.4 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24z"/>
            <path fill="#FBBC05" d="M5.4 14.4a7.2 7.2 0 0 1 0-4.6V6.7H1.4a12 12 0 0 0 0 10.8l4-3.1z"/>
            <path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.4 6.7l4 3.1C6.3 6.9 8.9 4.8 12 4.8z"/>
        </svg>
        @break

    @case('apple')
        <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="currentColor" aria-hidden="true">
            <path d="M17.6 12.7c0-2.4 2-3.6 2.1-3.7-1.1-1.7-2.9-1.9-3.5-1.9-1.5-.2-2.9.9-3.6.9s-1.9-.9-3.1-.8c-1.6 0-3.1.9-3.9 2.4-1.7 2.9-.4 7.2 1.2 9.5.8 1.2 1.7 2.4 3 2.4 1.2 0 1.6-.8 3.1-.8s1.9.8 3.1.8c1.3 0 2.1-1.2 2.9-2.3.9-1.3 1.3-2.6 1.3-2.7-.1 0-2.6-1-2.6-3.8zM15.1 5.1c.7-.8 1.1-1.9 1-3.1-1 0-2.2.7-2.9 1.5-.6.7-1.2 1.9-1 3 1.1.1 2.2-.6 2.9-1.4z"/>
        </svg>
        @break

    @case('facebook')
        <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" aria-hidden="true">
            <path fill="#1877F2" d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.3 2.7.3v2.9h-1.5c-1.5 0-1.9.9-1.9 1.8V12h3.3l-.5 3.5h-2.8v8.4A12 12 0 0 0 24 12z"/>
            <path fill="#FFF" d="M16.7 15.5l.5-3.5h-3.3V9.7c0-.9.4-1.8 1.9-1.8h1.5V5s-1.4-.3-2.7-.3c-2.7 0-4.5 1.7-4.5 4.7V12h-3v3.5h3v8.4a12 12 0 0 0 3.8 0v-8.4h2.8z"/>
        </svg>
        @break
@endswitch
