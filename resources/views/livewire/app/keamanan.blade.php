<div>
    <header class="layar-kepala">
        <div class="min-w-0">
        <h1 class="judul">Keamanan</h1>
        <p class="text-ink-soft text-[13px]">Siapa yang bisa membuka buku Anda.</p>
        </div>
    </header>

    @if (session('kabar'))
        <p class="kartu text-hijau mx-5 mt-4 px-4 py-3" role="status">{{ session('kabar') }}</p>
    @endif

    {{-- Perangkat aktif. Pertanyaan yang benar-benar ditanyakan orang saat
         curiga: siapa yang sedang masuk, dan bisakah saya mengusirnya. --}}
    <section class="rule-b px-5 py-5">
        <h2 class="label mb-3">Perangkat aktif</h2>

        @forelse ($perangkat as $satu)
            <div class="rule-b flex items-start justify-between gap-3 py-2.5 last:border-0">
                <div class="min-w-0">
                    <p class="truncate">{{ $satu->label }}</p>
                    <p class="text-ink-soft text-[13px] leading-[18px]">
                        {{ $satu->last_ip }} · {{ $satu->last_seen_at?->diffForHumans() }}
                        @if ($satu->session_id === session()->getId())
                            · <span class="text-hijau">perangkat ini</span>
                        @endif
                    </p>
                </div>

                @if ($satu->session_id !== session()->getId())
                    <button type="button" wire:click="cabutPerangkat('{{ $satu->id }}')"
                            class="tap text-ink-soft shrink-0 px-2 text-[13px]">Cabut</button>
                @endif
            </div>
        @empty
            <p class="text-ink-soft">Belum ada perangkat tercatat.</p>
        @endforelse

        <button type="button" wire:click="keluarkanSemua"
                wire:confirm="Keluarkan semua perangkat lain? Anda tetap masuk di perangkat ini."
                class="tombol-halus mt-4 w-full">Keluarkan semua perangkat lain</button>
    </section>

    @if ($errors->has('oauth'))
        <p class="pesan-galat mx-5 mt-4" role="alert">{{ $errors->first('oauth') }}</p>
    @endif

    {{-- Akun pihak ketiga. Ditempatkan sebelum dua langkah karena inilah yang
         paling sering ditengok: orang datang ke sini untuk menyambungkan Google,
         bukan untuk mengaudit perangkat. --}}
    @if ($penyediaTersedia !== [])
        <section class="rule-b px-5 py-5">
            <h2 class="label mb-2">Cara masuk</h2>
            <p class="text-ink-soft mb-3 text-[13px] leading-[18px]">
                Surel dan kata sandi selalu bisa dipakai. Menyambungkan akun lain hanya menambah jalan
                masuk, tidak menggantikannya.
            </p>

            @foreach ($penyediaTersedia as $penyedia)
                @php($akun = $akunTersambung->get($penyedia->value))

                <div class="rule-b flex items-center justify-between gap-3 py-2.5 last:border-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-ikon-penyedia :provider="$penyedia" />
                        <div class="min-w-0">
                            <p class="truncate">{{ $penyedia->label() }}</p>
                            @if ($akun !== null)
                                <p class="text-ink-soft truncate text-[13px] leading-[18px]">
                                    {{ $akun->provider_email ?? 'tersambung' }}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if ($akun !== null)
                        <form method="POST" action="{{ route('oauth.destroy', $penyedia->value) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    onclick="return confirm('Putuskan {{ $penyedia->label() }}?')"
                                    class="tap text-ink-soft px-2 text-[13px]">Putuskan</button>
                        </form>
                    @else
                        <a href="{{ route('oauth.redirect', $penyedia->value) }}"
                           class="tombol-halus shrink-0 px-3 text-[13px]">Sambungkan</a>
                    @endif
                </div>
            @endforeach

            @unless ($punyaKataSandi)
                {{-- Peringatan yang benar-benar berguna: orang yang mendaftar
                     lewat Google sering tidak sadar ia tidak punya kata sandi,
                     sampai hari akun Google-nya bermasalah. --}}
                <p class="kartu text-kuning-teks border-kuning mt-3 border-l-2 px-4 py-3 text-[13px] leading-[18px]">
                    Anda belum punya kata sandi. Kalau akses ke akun di atas hilang, satu-satunya jalan
                    masuk adalah tautan pemulihan lewat surel.
                    <a href="{{ route('password.request') }}" class="text-biru underline">Pasang kata sandi</a>
                </p>
            @endunless
        </section>
    @endif

    <section class="rule-b px-5 py-5">
        <h2 class="label mb-2">Verifikasi dua langkah</h2>

        @if ($duaLangkahAktif)
            <p class="text-hijau mb-3">Aktif.</p>

            @if (! empty($kodePemulihan))
                <details class="mb-3">
                    <summary class="tap text-ink-soft inline-flex cursor-pointer items-center text-[13px]">
                        Lihat kode pemulihan
                    </summary>
                    <ul class="kartu nominal mt-2 space-y-1 px-4 py-3 text-left text-[13px]">
                        @foreach ($kodePemulihan as $kode)
                            <li><span class="angka">{{ $kode }}</span></li>
                        @endforeach
                    </ul>
                    <p class="text-ink-soft mt-2 text-[13px] leading-[18px]">
                        Simpan di tempat yang tidak ikut hilang bersama ponsel Anda.
                    </p>
                </details>
            @endif

            <button type="button" wire:click="matikanDuaLangkah"
                    wire:confirm="Matikan verifikasi dua langkah? Akun jadi lebih mudah ditembus."
                    class="tombol-halus w-full">Matikan</button>
        @elseif ($qrDuaLangkah !== null)
            <p class="text-ink-soft mb-3 text-[13px] leading-[18px]">
                Pindai dengan aplikasi autentikator, lalu masukkan kodenya untuk memastikan
                pemindaiannya berhasil.
            </p>

            <div class="kartu mb-3 flex justify-center p-4">{!! $qrDuaLangkah !!}</div>

            <form wire:submit="konfirmasiDuaLangkah" class="space-y-3">
                <div>
                    <label for="kodeDuaLangkah" class="label mb-1.5 block">Kode dari aplikasi</label>
                    <input id="kodeDuaLangkah" type="text" inputmode="numeric" maxlength="6"
                           wire:model="kodeDuaLangkah" required autocomplete="one-time-code"
                           class="isian nominal text-center tracking-[0.3em]">
                    <x-galat untuk="kodeDuaLangkah" />
                </div>
                <button type="submit" class="tombol-utama w-full">Aktifkan</button>
            </form>
        @else
            <p class="text-ink-soft mb-3 text-[13px] leading-[18px]">
                Belum aktif. Dengan ini, kata sandi yang bocor saja tidak cukup untuk masuk.
            </p>
            <button type="button" wire:click="siapkanDuaLangkah" class="tombol-halus w-full">
                Aktifkan verifikasi dua langkah
            </button>
        @endif
    </section>

    <section class="rule-b px-5 py-5">
        <h2 class="label mb-2">Kunci aplikasi</h2>
        <p class="text-ink-soft mb-3 text-[13px] leading-[18px]">
            PIN enam digit yang diminta setelah aplikasi menganggur lima menit. Melindungi dari orang
            yang sedang memegang ponsel Anda, bukan dari penyerang jarak jauh.
        </p>

        @if ($punyaPin)
            <button type="button" wire:click="hapusPin"
                    wire:confirm="Hapus PIN? Aplikasi tidak akan mengunci sendiri lagi."
                    class="tombol-halus w-full">Hapus PIN</button>
        @else
            <form wire:submit="pasangPin" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="pinBaru" class="label mb-1.5 block">PIN</label>
                        <input id="pinBaru" type="password" inputmode="numeric" maxlength="6"
                               wire:model="pinBaru" required autocomplete="new-password"
                               class="isian nominal text-center tracking-[0.3em]">
                        <x-galat untuk="pinBaru" />
                    </div>
                    <div>
                        <label for="pinUlang" class="label mb-1.5 block">Ulangi</label>
                        <input id="pinUlang" type="password" inputmode="numeric" maxlength="6"
                               wire:model="pinUlang" required autocomplete="new-password"
                               class="isian nominal text-center tracking-[0.3em]">
                        <x-galat untuk="pinUlang" />
                    </div>
                </div>
                <button type="submit" class="tombol-utama w-full">Pasang PIN</button>
            </form>
        @endif
    </section>

    {{-- Akses dukungan. Diterbitkan pengguna, bukan diminta admin. --}}
    <section class="rule-b px-5 py-5">
        <h2 class="label mb-2">Akses dukungan</h2>
        <p class="text-ink-soft mb-3 text-[13px] leading-[18px]">
            Admin Rafin tidak bisa membuka buku Anda. Kalau Anda butuh bantuan yang menuntut mereka
            melihat sesuatu, Andalah yang memberi izin — paling lama {{ $maksJam }} jam, tercatat,
            dan Anda diberi tahu saat izin itu dipakai.
        </p>

        <form wire:submit="terbitkanIzin" class="space-y-3">
            <div class="grid grid-cols-[6rem_1fr] gap-3">
                <div>
                    <label for="jamIzin" class="label mb-1.5 block">Jam</label>
                    <input id="jamIzin" type="number" min="1" max="{{ $maksJam }}"
                           wire:model="jamIzin" required class="isian">
                    <x-galat untuk="jamIzin" />
                </div>
                <div>
                    <label for="alasanIzin" class="label mb-1.5 block">Alasan</label>
                    <input id="alasanIzin" type="text" wire:model="alasanIzin" maxlength="255"
                           placeholder="Saldo tidak cocok" class="isian">
                </div>
            </div>
            <button type="submit" class="tombol-halus w-full">Terbitkan izin</button>
        </form>

        @if ($izin->isNotEmpty())
            <ul class="mt-4 space-y-2">
                @foreach ($izin as $satu)
                    <li class="flex items-start justify-between gap-3">
                        <span class="text-[13px]">
                            {{ $satu->statusLabel() }} · sampai {{ $satu->expires_at->diffForHumans() }}
                            @if ($satu->reason)
                                <span class="text-ink-soft block">{{ $satu->reason }}</span>
                            @endif
                        </span>
                        @if ($satu->masihBerlaku())
                            <button type="button" wire:click="cabutIzin('{{ $satu->id }}')"
                                    class="tap text-ink-soft shrink-0 px-2 text-[13px]">Cabut</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="px-5 py-5">
        <h2 class="label mb-3">Riwayat</h2>

        @forelse ($riwayat as $peristiwa)
            <div class="rule-b flex items-baseline justify-between gap-3 py-2 last:border-0">
                <span class="truncate">{{ $peristiwa->event->label() }}</span>
                <span class="text-ink-soft shrink-0 text-[13px]">
                    {{ $peristiwa->created_at->diffForHumans() }}
                </span>
            </div>
        @empty
            <p class="text-ink-soft">Belum ada riwayat.</p>
        @endforelse

        <a href="{{ route('transparansi') }}" class="text-biru tap mt-4 inline-flex items-center text-[13px]">
            Apa yang admin Rafin bisa dan tidak bisa lihat
        </a>
    </section>
</div>
