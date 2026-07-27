<div>
    <h1 class="judul mb-1">Terkunci</h1>
    <p class="text-ink-soft mb-6">Masukkan PIN untuk melanjutkan.</p>

    <form wire:submit="buka" class="space-y-4">
        <div>
            <label for="pin" class="label mb-1.5 block">PIN</label>
            <input
                id="pin" type="password" inputmode="numeric" autocomplete="off"
                maxlength="6" pattern="[0-9]*" required autofocus
                wire:model="pin"
                class="isian nominal text-center tracking-[0.4em]"
                @error('pin') aria-invalid="true" aria-describedby="galat-pin" @enderror
            >
            <x-galat untuk="pin" />
        </div>

        <button type="submit" class="tombol-utama w-full">Buka</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="rule-t mt-6 pt-6">
        @csrf
        <p class="text-ink-soft mb-2 text-[13px] leading-[18px]">
            Lupa PIN? Keluar lalu masuk lagi dengan kata sandi — PIN bisa disetel ulang dari Keamanan.
        </p>
        <button type="submit" class="tombol-halus w-full">Keluar</button>
    </form>
</div>
