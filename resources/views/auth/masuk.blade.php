<x-layouts.tamu title="Masuk">
    <h1 class="judul mb-1">Masuk</h1>
    <p class="text-ink-soft mb-6">Lanjutkan ke buku kas Anda.</p>

    @if (session('status'))
        <p class="kartu text-hijau mb-4 px-4 py-3">{{ session('status') }}</p>
    @endif

    @if ($errors->has('oauth'))
        <p class="pesan-galat mb-4" role="alert">{{ $errors->first('oauth') }}</p>
    @endif

    @if ($errors->has('email') && ! $errors->has('password'))
        <p class="pesan-galat mb-4" role="alert">{{ $errors->first('email') }}</p>
    @endif

    {{-- Penyedia diletakkan di ATAS formulir: bagi orang yang memakainya, ini
         jalur satu ketukan, dan menaruhnya di bawah berarti memaksa membaca
         seluruh formulir dulu untuk menemukan jalan tercepat. --}}
    <x-tombol-penyedia label="Masuk dengan" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="label mb-1.5 block">Surel</label>
            <input
                id="email"
                name="email"
                type="email"
                inputmode="email"
                autocomplete="username"
                required
                autofocus
                value="{{ old('email') }}"
                class="isian"
                @if ($errors->has('email')) aria-invalid="true" aria-describedby="galat-email" @endif
            >
            <x-galat untuk="email" />
        </div>

        <div>
            <label for="password" class="label mb-1.5 block">Kata sandi</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="isian"
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="galat-password" @endif
            >
            <x-galat untuk="password" />
        </div>

        <label class="tap flex items-center gap-2.5">
            <input type="checkbox" name="remember" class="h-4 w-4 accent-[var(--biru-50)]">
            <span>Biarkan saya tetap masuk</span>
        </label>

        <button type="submit" class="tombol-utama w-full">Masuk</button>
    </form>

    {{-- Tautan diberi baris sendiri, bukan disisipkan di tengah kalimat.
         Tautan sepanjang satu kata di dalam paragraf hanya setinggi 17px —
         di bawah target sentuh 44px, dan meleset terus di ponsel. --}}
    <div class="rule-t mt-6 flex flex-col items-start pt-6">
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" class="text-biru tap inline-flex items-center">Lupa kata sandi</a>
        @endif

        @if (Route::has('register'))
            <p class="text-ink-soft mt-2">Belum punya akun?</p>
            <a href="{{ route('register') }}" class="text-biru tap inline-flex items-center">Daftar akun baru</a>
        @endif
    </div>
</x-layouts.tamu>
