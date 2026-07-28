import './bootstrap';
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { antrekan, jumlahTertunda, mintaSinkron, ulid, kompresGambar } from './antrean';

/*
 * ---------------------------------------------------------------------------
 * Service worker
 * ---------------------------------------------------------------------------
 */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/build/sw.js', { scope: '/' }).catch(() => {
            // Pemasangan gagal (mode privat, HTTP, atau kebijakan perangkat).
            // Aplikasi tetap berjalan; hanya kemampuan offline yang hilang.
        });
    });

    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'ANTREAN_BERUBAH') {
            perbaruiLencana(event.data.sisa);

            if (event.data.terkirim > 0) {
                window.Livewire?.dispatch('antrean-tersinkron');
            }
        }
    });

    // Safari tidak punya Background Sync sama sekali, jadi dua pemicu manual
    // ini bukan cadangan — di iOS merekalah satu-satunya jalan.
    window.addEventListener('online', () => mintaSinkron());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') mintaSinkron();
    });
}

/*
 * ---------------------------------------------------------------------------
 * Lencana "menunggu sinkron"
 * ---------------------------------------------------------------------------
 */

async function perbaruiLencana(sisa = null) {
    const jumlah = sisa ?? (await jumlahTertunda());
    const lencana = document.querySelector('[data-lencana-antrean]');

    if (!lencana) return;

    lencana.hidden = jumlah === 0;
    lencana.textContent = jumlah === 0 ? '' : `Menunggu sinkron · ${jumlah}`;
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
