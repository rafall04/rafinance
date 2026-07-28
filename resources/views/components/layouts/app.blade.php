@props(['title' => null])

<!DOCTYPE html>
<html lang="id" class="antialiased">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover supaya konten bisa memanfaatkan area di sekitar
         takik layar, dipasangkan dengan padding safe-area di bawah. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FBFBF9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#12181F" media="(prefers-color-scheme: dark)">

    <title>{{ $title ? $title.' · Rafin' : 'Rafin' }}</title>

    <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">

    {{-- ?v= menandai versi ikon — lihat catatan di vite.config.js. --}}
    <link rel="icon" href="{{ asset('ikon/192.png') }}?v=2" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('ikon/apple-touch-icon.png') }}?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Rafin">

    {{-- Dijalankan sebelum lukisan pertama. Kalau preferensi dibaca setelah
         halaman tampil, pengguna mode gelap akan disambut kilatan putih, dan
         mode privasi akan memperlihatkan nominal untuk sepersekian detik —
         justru pada saat ia paling tidak menginginkannya. --}}
    <script>
        (function () {
            try {
                var tema = localStorage.getItem('rafin.tema');
                if (tema === 'terang' || tema === 'gelap') {
                    document.documentElement.dataset.theme = tema === 'gelap' ? 'dark' : 'light';
                }
                if (localStorage.getItem('rafin.privasi') === 'on') {
                    document.documentElement.dataset.privacy = 'on';
                }
            } catch (e) {
                // Penyimpanan lokal diblokir: pakai bawaan sistem, jangan gagal.
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Menyembunyikan wire:loading dan x-cloak sebelum Livewire sempat
         menyalakan diri. Tanpa ini, tombol Simpan di halaman Tambah
         memperlihatkan "Simpan" dan "Menyimpan…" berbarengan. --}}
    @livewireStyles
</head>
<body class="bg-paper text-ink" @if (auth()->user()?->punyaKunciAplikasi()) data-app-lock="1" @endif>
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 tombol-utama">
        Lompat ke konten
    </a>

    <div class="shell flex min-h-dvh flex-col">
        {{-- Lencana antrean offline. Disembunyikan saat kosong, bukan dihapus:
             kehadirannya di DOM membuat pembaruan dari service worker tidak
             perlu menyusun ulang tata letak. --}}
        {{-- Teks memakai --kuning-teks, bukan --kuning-1: kuning uang kertas di
             atas tint kuning hanya 2,08:1, dan lencana ini justru muncul saat
             orang sedang cemas apakah catatannya tersimpan. --}}
        <p
            data-lencana-antrean
            hidden
            role="status"
            aria-live="polite"
            class="bg-kuning/15 text-kuning-teks rule-b px-5 py-1.5 text-center text-[13px] font-medium"
        ></p>

        <main id="konten" class="flex-1 pb-24">
            {{ $slot }}
        </main>

        <x-tab-bar />
    </div>

    @livewireScriptConfig
</body>
</html>
