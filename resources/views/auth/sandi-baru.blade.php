<x-layouts.tamu title="Kata sandi baru">
    <h1 class="judul mb-1">Kata sandi baru</h1>
    <p class="text-ink-soft mb-6">Setelah disimpan, semua perangkat lain akan keluar.</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="label mb-1.5 block">Surel</label>
            <input
                id="email" name="email" type="email" inputmode="email" autocomplete="username" required
                value="{{ old('email', $request->email) }}" class="isian"
                @if ($errors->has('email')) aria-invalid="true" aria-describedby="galat-email" @endif
            >
            <x-galat untuk="email" />
        </div>

        <div>
            <label for="password" class="label mb-1.5 block">Kata sandi baru</label>
            <input
                id="password" name="password" type="password" autocomplete="new-password" required autofocus
                class="isian"
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="galat-password" @endif
            >
            <x-galat untuk="password" />
        </div>

        <div>
            <label for="password_confirmation" class="label mb-1.5 block">Ulangi kata sandi baru</label>
            <input
                id="password_confirmation" name="password_confirmation" type="password"
                autocomplete="new-password" required class="isian"
            >
        </div>

        <button type="submit" class="tombol-utama w-full">Simpan kata sandi</button>
    </form>
</x-layouts.tamu>
