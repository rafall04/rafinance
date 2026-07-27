<x-layouts.tamu title="Verifikasi dua langkah">
    <h1 class="judul mb-1">Verifikasi dua langkah</h1>
    <p class="text-ink-soft mb-6">Masukkan kode dari aplikasi autentikator Anda.</p>

    @if ($errors->any())
        <p class="pesan-galat mb-4" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="label mb-1.5 block">Kode enam digit</label>
            <input
                id="code" name="code" type="text" inputmode="numeric" maxlength="6"
                autocomplete="one-time-code" autofocus
                class="isian nominal text-center tracking-[0.3em]"
            >
        </div>

        <button type="submit" class="tombol-utama w-full">Lanjutkan</button>
    </form>

    {{-- Kode pemulihan diletakkan di balik details, bukan disembunyikan sama
         sekali: orang yang kehilangan ponselnya sedang panik, dan tautan yang
         harus dicari-cari adalah hal terakhir yang ia butuhkan. --}}
    <details class="rule-t mt-6 pt-6">
        <summary class="text-biru tap inline-flex cursor-pointer items-center">
            Ponsel hilang? Pakai kode pemulihan
        </summary>

        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="recovery_code" class="label mb-1.5 block">Kode pemulihan</label>
                <input id="recovery_code" name="recovery_code" type="text"
                       autocomplete="one-time-code" class="isian nominal">
            </div>
            <button type="submit" class="tombol-halus w-full">Masuk dengan kode pemulihan</button>
        </form>
    </details>
</x-layouts.tamu>
