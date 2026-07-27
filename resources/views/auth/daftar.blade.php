<x-layouts.tamu title="Daftar">
    <h1 class="judul mb-1">Buat akun</h1>
    <p class="text-ink-soft mb-6">Gratis selama masa beta.</p>

    @if ($errors->has('oauth'))
        <p class="pesan-galat mb-4" role="alert">{{ $errors->first('oauth') }}</p>
    @endif

    <x-tombol-penyedia label="Daftar dengan" />

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="label mb-1.5 block">Nama</label>
            <input
                id="name" name="name" type="text" autocomplete="name" required autofocus
                value="{{ old('name') }}" class="isian"
                @if ($errors->has('name')) aria-invalid="true" aria-describedby="galat-name" @endif
            >
            <x-galat untuk="name" />
        </div>

        <div>
            <label for="email" class="label mb-1.5 block">Surel</label>
            <input
                id="email" name="email" type="email" inputmode="email" autocomplete="username" required
                value="{{ old('email') }}" class="isian"
                @if ($errors->has('email')) aria-invalid="true" aria-describedby="galat-email" @endif
            >
            {{-- Alasannya disampaikan sekali, di tempat yang tepat. Orang yang
                 hanya berniat memakai bot Telegram perlu tahu kenapa diminta. --}}
            <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">
                Dipakai untuk memulihkan akun kalau akses ke Telegram hilang.
            </p>
            <x-galat untuk="email" />
        </div>

        <div>
            <label for="password" class="label mb-1.5 block">Kata sandi</label>
            <input
                id="password" name="password" type="password" autocomplete="new-password" required class="isian"
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="galat-password" @endif
            >
            <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">Setidaknya 8 karakter.</p>
            <x-galat untuk="password" />
        </div>

        <div>
            <label for="password_confirmation" class="label mb-1.5 block">Ulangi kata sandi</label>
            <input
                id="password_confirmation" name="password_confirmation" type="password"
                autocomplete="new-password" required class="isian"
            >
        </div>

        <button type="submit" class="tombol-utama w-full">Daftar</button>
    </form>

    <div class="rule-t mt-6 flex flex-col items-start pt-6">
        <p class="text-ink-soft">Sudah punya akun?</p>
        <a href="{{ route('login') }}" class="text-biru tap inline-flex items-center">Masuk</a>
    </div>
</x-layouts.tamu>
