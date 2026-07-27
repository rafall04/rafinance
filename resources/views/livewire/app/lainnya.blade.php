<div>
    <header class="layar-kepala">
        <div class="min-w-0">
        <h1 class="judul">Lainnya</h1>
        <p class="text-ink-soft text-[13px]">{{ $workspace?->name }}</p>
        </div>
    </header>

    @foreach ($kelompok as $bagian)
        <section>
            <h2 class="label bg-paper-sunk rule-b px-5 py-1.5">{{ $bagian['judul'] }}</h2>

            @foreach ($bagian['isi'] as $tautan)
                @continue (! Route::has($tautan['rute']))
                <a href="{{ route($tautan['rute']) }}" wire:navigate
                   class="rule-b tap flex items-center justify-between gap-3 px-5 py-3">
                    <span>
                        <span class="block">{{ $tautan['label'] }}</span>
                        <span class="text-ink-soft block text-[13px] leading-[18px]">{{ $tautan['ket'] }}</span>
                    </span>
                    <span class="text-ink-soft" aria-hidden="true">→</span>
                </a>
            @endforeach
        </section>
    @endforeach

    <section class="px-5 py-5">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="tombol-halus w-full">Keluar</button>
        </form>
    </section>
</div>
