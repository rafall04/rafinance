<x-layouts.tamu title="Lupa kata sandi">
    <h1 class="judul mb-1">Lupa kata sandi</h1>
    <p class="text-ink-soft mb-6">Kami kirimkan tautan untuk membuat yang baru.</p>

    @if (session('status'))
        <p class="kartu text-hijau mb-4 px-4 py-3">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="label mb-1.5 block">Surel</label>
            <input
                id="email" name="email" type="email" inputmode="email" autocomplete="username"
                required autofocus value="{{ old('email') }}" class="isian"
                @if ($errors->has('email')) aria-invalid="true" aria-describedby="galat-email" @endif
            >
            <x-galat untuk="email" />
        </div>

        <button type="submit" class="tombol-utama w-full">Kirim tautan</button>
    </form>

    <p class="rule-t mt-6 pt-6">
        <a href="{{ route('login') }}" class="text-biru">Kembali ke halaman masuk</a>
    </p>
</x-layouts.tamu>
