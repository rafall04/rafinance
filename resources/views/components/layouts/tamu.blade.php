@props(['title' => null])

<!DOCTYPE html>
<html lang="id" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FBFBF9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#12181F" media="(prefers-color-scheme: dark)">

    <title>{{ $title ? $title.' · Rafin' : 'Rafin' }}</title>

    <script>
        (function () {
            try {
                var tema = localStorage.getItem('rafin.tema');
                if (tema === 'terang' || tema === 'gelap') {
                    document.documentElement.dataset.theme = tema === 'gelap' ? 'dark' : 'light';
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink">
    {{-- Cahaya latar yang sama dengan halaman depan. Halaman masuk adalah layar
         kedua yang dilihat orang setelah beranda publik, dan perpindahan dari
         halaman yang digarap ke formulir polos terasa seperti berpindah
         produk. --}}
    <div class="aurora shell flex min-h-dvh flex-col justify-center px-5 py-10">
        <header class="mb-8">
            <a href="{{ route('beranda') }}" class="tap inline-flex items-center">
                <span class="display">Rafin</span>
            </a>
            <p class="text-ink-soft mt-1">Buku kas untuk pribadi dan usaha kecil.</p>
        </header>

        <main>
            {{ $slot }}
        </main>

        {{-- Jalan keluar. Tanpa ini, orang yang sampai ke /login dari tautan
             langsung tidak punya cara mengetahui Rafin itu apa selain menekan
             tombol kembali. --}}
        <footer class="rule-t text-ink-soft mt-8 pt-5 text-[13px]">
            <a href="{{ route('transparansi') }}" class="tap inline-flex items-center underline underline-offset-4">
                Apa yang kami bisa lihat dari catatan Anda
            </a>
        </footer>
    </div>

    @livewireScriptConfig
</body>
</html>
