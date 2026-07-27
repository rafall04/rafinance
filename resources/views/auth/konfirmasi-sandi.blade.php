<x-layouts.tamu title="Konfirmasi kata sandi">
    <h1 class="judul mb-1">Konfirmasi kata sandi</h1>
    <p class="text-ink-soft mb-6">Langkah ini melindungi bagian yang menyangkut keamanan akun.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <label for="password" class="label mb-1.5 block">Kata sandi</label>
            <input
                id="password" name="password" type="password" autocomplete="current-password"
                required autofocus class="isian"
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="galat-password" @endif
            >
            <x-galat untuk="password" />
        </div>

        <button type="submit" class="tombol-utama w-full">Lanjutkan</button>
    </form>
</x-layouts.tamu>
