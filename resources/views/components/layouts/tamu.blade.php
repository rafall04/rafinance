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
    <div class="shell flex min-h-dvh flex-col justify-center px-5 py-10">
        <header class="mb-8">
            <p class="display">Rafin</p>
            <p class="text-ink-soft mt-1">Buku kas untuk pribadi dan usaha kecil.</p>
        </header>

        <main>
            {{ $slot }}
        </main>
    </div>

    @livewireScriptConfig
</body>
</html>
