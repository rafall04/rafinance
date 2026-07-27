@props(['label' => 'Lanjutkan dengan'])

@php
    $penyedia = \App\Domain\Tenancy\Enums\SocialProvider::tersedia();
@endphp

{{-- Tidak merender apa pun kalau tidak ada penyedia yang dikonfigurasi. Tombol
     yang membawa ke halaman galat penyedia lebih buruk daripada tombol yang
     tidak ada. --}}
@if ($penyedia !== [])
    <div class="space-y-2">
        @foreach ($penyedia as $satu)
            <a
                href="{{ route('oauth.redirect', $satu->value) }}"
                class="tombol-halus w-full justify-center gap-3"
            >
                <x-ikon-penyedia :provider="$satu" />
                <span>{{ $label }} {{ $satu->label() }}</span>
            </a>
        @endforeach
    </div>

    {{-- Pemisah "atau". aria-hidden karena ia hiasan struktural: pembaca layar
         sudah mendapat urutannya dari susunan elemen. --}}
    <div class="my-5 flex items-center gap-3" aria-hidden="true">
        <span class="bg-rule h-px flex-1"></span>
        <span class="text-ink-soft text-[12px]">atau</span>
        <span class="bg-rule h-px flex-1"></span>
    </div>
@endif
