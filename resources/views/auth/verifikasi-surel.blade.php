<x-layouts.tamu title="Verifikasi surel">
    <h1 class="judul mb-1">Periksa surel Anda</h1>
    <p class="text-ink-soft mb-6">
        Kami mengirim tautan verifikasi ke <strong class="text-ink">{{ auth()->user()?->email }}</strong>.
        Buka tautan itu untuk mulai memakai Rafin.
    </p>

    @if (session('status') === 'verification-link-sent')
        <p class="kartu text-hijau mb-4 px-4 py-3">Tautan baru sudah dikirim.</p>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="tombol-utama w-full">Kirim ulang tautan</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="tombol-halus w-full">Keluar</button>
        </form>
    </div>

    <p class="text-ink-soft rule-t mt-6 pt-6 text-[13px] leading-[18px]">
        Surelnya tidak sampai? Periksa folder spam, atau daftar ulang dengan alamat lain.
    </p>
</x-layouts.tamu>
