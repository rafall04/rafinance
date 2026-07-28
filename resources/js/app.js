import './bootstrap';
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import {
    antreanDitolak,
    antrekan,
    jumlahTertunda,
    mintaSinkron,
    ulid,
    kompresGambar,
} from './antrean';

/*
 * ---------------------------------------------------------------------------
 * Service worker
 * ---------------------------------------------------------------------------
 */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => daftarkanServiceWorker());

    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'ANTREAN_BERUBAH') {
            perbaruiLencana(event.data.sisa, event.data.ditolak);

            if (event.data.terkirim > 0) {
                window.Livewire?.dispatch('antrean-tersinkron');
            }
        }
    });
}

/**
 * Mendaftarkan service worker, dengan satu percobaan kedua yang disengaja.
 *
 * Pendaftaran dengan scope '/' dari skrip di /build/ hanya diizinkan kalau
 * server mengirim header Service-Worker-Allowed. Header itu sekarang dikirim —
 * tapi ada satu keadaan yang tetap bisa menggagalkannya, dan ia sudah pernah
 * terjadi: proksi di depan aplikasi masih menyimpan salinan LAMA berkas ini
 * dari masa sebelum headernya ada.
 *
 * Rafin duduk di belakang Cloudflare, dan salinan lama itu tersimpan dengan
 * `immutable, max-age=1 tahun`. Artinya tanpa pembersihan manual ia akan
 * disajikan sampai tahun depan, dan seluruh kemampuan offline mati selama itu
 * — persis kegagalan sunyi yang seharusnya sudah kita tinggalkan.
 *
 * Percobaan kedua memakai query yang berbeda. Bagi proksi mana pun itu kunci
 * cache yang berbeda, jadi berkasnya diambil dari asalnya, lengkap dengan
 * headernya. Bagi peramban, URL yang berbeda berarti pendaftaran yang berbeda;
 * scope-nya sama, jadi yang lama tergantikan dan hanya ada satu yang aktif.
 *
 * Setelah cache proksinya bersih, percobaan pertama yang berhasil dan jalur
 * ini tidak pernah terpakai lagi.
 */
async function daftarkanServiceWorker() {
    try {
        await navigator.serviceWorker.register('/build/sw.js', { scope: '/' });

        return;
    } catch (galat) {
        console.warn('[rafin] pendaftaran service worker ditolak, mencoba melewati cache proksi:', galat);
    }

    try {
        await navigator.serviceWorker.register('/build/sw.js?lewati-cache=1', { scope: '/' });

        console.info('[rafin] service worker terdaftar lewat jalur cadangan. '
            + 'Bersihkan cache proksi untuk /build/sw.js supaya jalur utamanya kembali dipakai.');
    } catch (galat) {
        // Benar-benar tidak bisa: mode privat, kebijakan perangkat, atau
        // sesuatu yang belum kita ketahui. Aplikasi tetap berjalan dan antrean
        // tetap terkuras dari halaman (lihat mintaSinkron) — yang hilang hanya
        // pengiriman saat tab tertutup. Kegagalannya ditinggalkan di tempat
        // yang bisa ditemukan orang berikutnya, bukan ditelan diam-diam.
        window.rafin.serviceWorkerGagal = String(galat?.message ?? galat);
        console.error('[rafin] service worker gagal terdaftar:', galat);
    }
}

// Hasil pengiriman dari halaman sendiri, saat service worker tidak ada.
window.addEventListener('rafin:antrean', (event) => {
    perbaruiLencana(event.detail?.sisa, event.detail?.ditolak);

    if (event.detail?.terkirim > 0) {
        window.Livewire?.dispatch('antrean-tersinkron');
    }
});

// Safari tidak punya Background Sync sama sekali, jadi dua pemicu manual ini
// bukan cadangan — di iOS merekalah satu-satunya jalan. Dipasang di luar
// pemeriksaan 'serviceWorker' karena mintaSinkron() sekarang tetap berguna
// tanpa service worker: ia mengirim langsung dari halaman.
window.addEventListener('online', () => mintaSinkron());
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') mintaSinkron();
});

/*
 * ---------------------------------------------------------------------------
 * Lencana "menunggu sinkron"
 * ---------------------------------------------------------------------------
 */

async function perbaruiLencana(sisa = null, ditolak = null) {
    const jumlah = sisa ?? (await jumlahTertunda());
    const gagal = ditolak ?? (await antreanDitolak()).length;
    const lencana = document.querySelector('[data-lencana-antrean]');

    if (!lencana) return;

    // Yang ditolak server disebut terpisah dan didahulukan. Ia tidak akan
    // terkirim betapapun lama menunggu, jadi menyebutnya "menunggu sinkron"
    // adalah kebohongan yang membuat orang berhenti memeriksanya.
    const bagian = [];
    if (jumlah > 0) bagian.push(`Menunggu sinkron · ${jumlah}`);
    if (gagal > 0) bagian.push(`Ditolak · ${gagal}`);

    lencana.hidden = bagian.length === 0;
    lencana.textContent = bagian.join('  ·  ');
}

document.addEventListener('DOMContentLoaded', () => perbaruiLencana());

/*
 * ---------------------------------------------------------------------------
 * Tema dan mode privasi — preferensi per perangkat, bukan per akun
 * ---------------------------------------------------------------------------
 *
 * Mode privasi dipakai saat mencatat di depan orang lain, dan itu keadaan yang
 * melekat pada perangkat dan situasinya, bukan pada akun. Menyimpannya di
 * server justru akan menyalakannya di ponsel yang sedang sendirian.
 */

window.rafin = {
    ulid,
    antrekan,
    kompresGambar,
    jumlahTertunda,
    mintaSinkron,

    gantiTema(tema) {
        const akar = document.documentElement;

        if (tema === 'sistem') {
            delete akar.dataset.theme;
            localStorage.removeItem('rafin.tema');
            return;
        }

        akar.dataset.theme = tema === 'gelap' ? 'dark' : 'light';
        localStorage.setItem('rafin.tema', tema);
    },

    togglePrivasi() {
        const akar = document.documentElement;
        const aktif = akar.dataset.privacy === 'on';

        if (aktif) {
            delete akar.dataset.privacy;
            localStorage.removeItem('rafin.privasi');
        } else {
            akar.dataset.privacy = 'on';
            localStorage.setItem('rafin.privasi', 'on');
        }

        return !aktif;
    },

    /**
     * Menyimpan transaksi. Selalu berhasil dari sudut pandang pengguna:
     * kalau jaringan gagal, ia mendarat di antrean dan terkirim sendiri nanti.
     */
    async simpanTransaksi(payload) {
        const id = payload.id || ulid();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        try {
            const balasan = await fetch('/app/transaksi', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Idempotency-Key': id,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ...payload, id }),
                credentials: 'same-origin',
            });

            if (balasan.ok) return { id, tersinkron: true };

            if (balasan.status >= 400 && balasan.status < 500 && balasan.status !== 419) {
                return { id, tersinkron: false, galat: await balasan.json().catch(() => null) };
            }
        } catch {
            // Jaringan tidak tersedia. Bukan kegagalan — inilah yang antrean
            // ada untuk menanganinya.
        }

        await antrekan(id, '/app/transaksi', { ...payload, id }, csrf);
        await perbaruiLencana();

        return { id, tersinkron: false, diantrekan: true };
    },
};

/*
 * ---------------------------------------------------------------------------
 * Kunci aplikasi
 * ---------------------------------------------------------------------------
 *
 * Status terkunci disimpan di sessionStorage, bukan localStorage: menutup tab
 * harus mengunci kembali. localStorage akan membuat "terbuka" bertahan sampai
 * berhari-hari kemudian, yang justru meniadakan gunanya.
 */

const IDLE_MS = 5 * 60 * 1000;
let pewaktuIdle = null;

function mulaiHitungIdle() {
    if (!document.body?.dataset.appLock) return;

    clearTimeout(pewaktuIdle);
    pewaktuIdle = setTimeout(() => {
        sessionStorage.setItem('rafin.terkunci', '1');
        window.location.href = '/app/kunci';
    }, IDLE_MS);
}

['pointerdown', 'keydown', 'scroll', 'visibilitychange'].forEach((peristiwa) =>
    document.addEventListener(peristiwa, mulaiHitungIdle, { passive: true }),
);

document.addEventListener('DOMContentLoaded', () => {
    if (document.body?.dataset.appLock && sessionStorage.getItem('rafin.terkunci') === '1') {
        if (!window.location.pathname.startsWith('/app/kunci')) {
            window.location.href = '/app/kunci';
            return;
        }
    }

    mulaiHitungIdle();
});

/*
 * ---------------------------------------------------------------------------
 * Keluar: membersihkan jejak di perangkat
 * ---------------------------------------------------------------------------
 *
 * Sesi berakhir di server, tapi tiga hal tetap tertinggal di ponsel dan tidak
 * satu pun ikut mati bersamanya:
 *
 *   Cache Storage   halaman /app yang sudah dirender, lengkap dengan nominal
 *   IndexedDB       antrean transaksi yang belum terkirim
 *   sessionStorage  status kunci aplikasi
 *
 * Yang pertama paling berbahaya: service worker menyajikan halaman tersimpan
 * LEBIH DULU sebelum menyegarkan, jadi orang kedua yang masuk di ponsel yang
 * sama akan melihat buku kas orang pertama sekejap sebelum layarnya berganti.
 * Ponsel berbagi adalah hal biasa di warung dan usaha keluarga — pasar sasaran
 * aplikasi ini.
 *
 * Antreannya sengaja TIDAK dikosongkan lebih dulu: isinya catatan pengeluaran
 * yang belum sampai, dan membuangnya berarti menghilangkan uang orang dari
 * pembukuannya. Ia dicoba kirim dulu; yang tersisa tetap disimpan, dan token
 * CSRF-nya yang sudah mati membuatnya mustahil terkirim ke sesi orang lain.
 */
async function bersihkanJejakPerangkat() {
    try {
        await mintaSinkron();
    } catch {
        // Offline atau ditolak. Antreannya tetap tersimpan.
    }

    try {
        sessionStorage.removeItem('rafin.terkunci');
    } catch {
        // Penyimpanan diblokir.
    }

    // Dibuang dari halaman ini, bukan lewat pesan ke service worker: kalau
    // pendaftarannya gagal, pesan itu tidak akan sampai ke siapa pun.
    if ('caches' in window) {
        try {
            const nama = await caches.keys();
            await Promise.all(
                nama.filter((n) => n.startsWith('rafin-')).map((n) => caches.delete(n)),
            );
        } catch {
            // Tidak bisa diakses; tidak ada yang bisa dilakukan dari sini.
        }
    }

    try {
        const registrasi = await navigator.serviceWorker?.getRegistration();
        registrasi?.active?.postMessage({ type: 'LUPAKAN_SEMUA' });
    } catch {
        // Tidak ada service worker. Cache sudah dibuang di atas.
    }
}

// Ditangkap di tingkat dokumen supaya berlaku untuk setiap formulir keluar —
// ada tiga sekarang (Lainnya, kunci aplikasi, verifikasi surel), dan yang
// ditambahkan besok ikut tercakup tanpa diingat siapa pun.
document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;
        if (!/\/logout$/.test(new URL(form.action, location.origin).pathname)) return;
        if (form.dataset.rafinBersih === '1') return;

        event.preventDefault();

        bersihkanJejakPerangkat().finally(() => {
            form.dataset.rafinBersih = '1';
            // submit() tidak memicu event ini lagi, jadi tidak ada gelung.
            form.submit();
        });
    },
    true,
);

/*
 * ---------------------------------------------------------------------------
 * Livewire
 * ---------------------------------------------------------------------------
 *
 * Dipanggil terakhir, dan pemanggilannya wajib. Livewire hanya menyalakan
 * dirinya sendiri kalau `window.livewireScriptConfig` TIDAK ada — dan tata
 * letak kita memasang config itu lewat @livewireScriptConfig. Itulah kontrak
 * "bundel sendiri": begitu config-nya ada, Livewire menyerahkan waktu mulai
 * kepada kita.
 *
 * Tanpa baris ini komponen tetap dirender di server dan terlihat normal, tapi
 * tidak ada satu pun wire:click, wire:model, atau $wire yang hidup — halaman
 * yang tampak utuh dan diam saja saat ditekan.
 *
 * Terakhir, bukan pertama: pengendali di atas memasang window.rafin, dan
 * penanganan x-on:click di halaman Tambah memanggilnya.
 */
Livewire.start();
