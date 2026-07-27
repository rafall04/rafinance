@props(['title' => null, 'deskripsi' => 'Buku kas untuk pribadi dan usaha kecil. Catat dulu, rapikan nanti — dari web atau Telegram, online atau tidak.'])

{{--
    Layout halaman publik.

    Terpisah dari layout tamu karena keperluannya berbeda: halaman masuk adalah
    satu kolom sempit yang dipusatkan, sementara halaman ini dibaca dari atas ke
    bawah dan butuh ruang untuk bernapas.

    Sengaja tanpa JavaScript sama sekali selain skrip tema yang tiga baris itu.
    Ini halaman pertama yang dibuka orang, sering di jaringan yang buruk, dan
    setiap kilobyte di sini dibayar oleh orang yang belum tentu jadi memakai
    Rafin.
--}}

<!DOCTYPE html>
<html lang="id" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FBFBF9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#12181F" media="(prefers-color-scheme: dark)">

    <title>{{ $title ? $title.' · Rafin' : 'Rafin — buku kas untuk pribadi dan usaha kecil' }}</title>
    <meta name="description" content="{{ $deskripsi }}">

    {{-- Tautan Rafin paling sering dibagikan lewat WhatsApp. Tanpa tag ini
         yang muncul di sana hanya URL telanjang. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Rafin">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $title ? $title.' · Rafin' : 'Rafin — buku kas untuk pribadi dan usaha kecil' }}">
    <meta property="og:description" content="{{ $deskripsi }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/ikon/512.png') }}">
    <meta name="twitter:card" content="summary">

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="/ikon/192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/ikon/apple-touch-icon.png">

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

    @vite(['resources/css/app.css'])
</head>
<body class="bg-paper text-ink">
    {{-- Tersembunyi sampai difokus keyboard. Tanpa ini, pemakai keyboard harus
         menelusuri seluruh navigasi di setiap kunjungan. --}}
    <a href="#utama" class="lewati-ke-isi">Lewati ke isi</a>

    <div class="publik">
        <header class="flex items-center justify-between gap-4 py-4">
            <a href="{{ route('beranda') }}" class="tap inline-flex items-center font-bold tracking-tight"
               style="font-size: 20px" aria-label="Rafin, ke beranda">Rafin</a>

            <nav aria-label="Utama">
                <a href="{{ route('login') }}" class="tombol-halus tap">Masuk</a>
            </nav>
        </header>

        <main id="utama">
            {{ $slot }}
        </main>

        <footer class="rule-t text-ink-soft mt-16 py-8">
            <nav class="flex flex-wrap gap-x-5 gap-y-1" aria-label="Tautan kaki">
                <a href="{{ route('transparansi') }}" class="tap inline-flex items-center underline underline-offset-4">Transparansi</a>
                <a href="{{ route('login') }}" class="tap inline-flex items-center underline underline-offset-4">Masuk</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="tap inline-flex items-center underline underline-offset-4">Daftar</a>
                @endif
            </nav>

            <p class="mt-4 text-[13px]">
                Rafin · {{ now()->year }} · Buku kas untuk pribadi dan usaha kecil.
            </p>
        </footer>
    </div>
</body>
</html>
