/*
 * Service worker Rafin.
 *
 * Ditulis tangan, bukan dihasilkan Workbox, karena antrean offline butuh
 * perilaku yang tidak bisa dinyatakan sebagai strategi caching: menahan
 * transaksi yang gagal terkirim, lalu mengirimkannya lagi tanpa pernah
 * menggandakannya.
 *
 * Yang membuat itu aman bukan kode di sini, melainkan ULID: client membuat ID
 * transaksi di ponsel dan memakainya sekaligus sebagai Idempotency-Key, jadi
 * server yang menerima kiriman kedua mengembalikan transaksi yang sudah ada
 * alih-alih membuat yang baru (aturan A7 dan A9).
 */

import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { kirimAntrean } from './kirim-antrean';

const VERSI = 'rafin-v1';
const CACHE_HALAMAN = `${VERSI}-halaman`;
const CACHE_LAMPIRAN = `${VERSI}-lampiran`;
const BATAS_LAMPIRAN = 50 * 1024 * 1024; // 50 MB

precacheAndRoute(self.__WB_MANIFEST || []);
cleanupOutdatedCaches();

self.addEventListener('install', () => {
    // Tidak skipWaiting otomatis: pembaruan yang memaksa muat ulang di tengah
    // pengetikan akan membuang transaksi yang belum tersimpan.
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'PERBARUI_SEKARANG') {
        self.skipWaiting();
    }
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const nama = await caches.keys();
            await Promise.all(
                nama
                    .filter((n) => n.startsWith('rafin-') && !n.startsWith(VERSI))
                    .map((n) => caches.delete(n)),
            );
            await self.clients.claim();
        })(),
    );
});

// ---------------------------------------------------------------------------
// Antrean offline
// ---------------------------------------------------------------------------

/**
 * Mengirim ulang seluruh antrean, lalu mengabarkan hasilnya ke semua tab.
 *
 * Aturannya sendiri ada di kirim-antrean.js, dipakai bersama dengan halaman.
 * Dulu ia ditulis dua kali, dan dua salinan aturan tentang apa arti 422 adalah
 * cara yang rapi untuk membuat keduanya berbeda pendapat enam bulan lagi.
 */
async function kuraskanAntrean(csrfSegar = null) {
    const hasil = await kirimAntrean(csrfSegar);

    const semuaClient = await self.clients.matchAll({ includeUncontrolled: true });
    semuaClient.forEach((client) =>
        client.postMessage({ type: 'ANTREAN_BERUBAH', ...hasil }),
    );

    return hasil.sisa;
}

self.addEventListener('sync', (event) => {
    if (event.tag === 'rafin-antrean') {
        event.waitUntil(kuraskanAntrean());
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'KIRIM_ANTREAN') {
        // Token dari halaman yang sedang terbuka. Background Sync memanggil
        // tanpa token karena memang tidak ada halaman yang bisa ditanya; di
        // jalur itu token simpanan yang dipakai.
        event.waitUntil(kuraskanAntrean(event.data.csrf ?? null));
    }

    // Keluar: buang seluruh cache halaman dan lampiran. Lihat catatan panjang
    // di app.js — cache ini berisi buku kas orang yang tadi masuk.
    if (event.data?.type === 'LUPAKAN_SEMUA') {
        event.waitUntil(lupakanSemua());
    }
});

/**
 * Membuang seluruh cache milik Rafin.
 *
 * Cache Storage tidak terikat sesi: ia bertahan melewati logout, melewati
 * penggantian akun, dan melewati penutupan aplikasi. Halaman /app yang sudah
 * dirender berisi nominal dan keterangan transaksi pemiliknya, dan halaman()
 * di bawah menyajikan yang tersimpan LEBIH DULU sebelum menyegarkan. Tanpa
 * pembersihan ini, orang kedua yang masuk di ponsel yang sama — hal biasa di
 * warung dan usaha keluarga — akan melihat buku kas orang pertama lebih dulu.
 */
async function lupakanSemua() {
    const nama = await caches.keys();

    await Promise.all(nama.filter((n) => n.startsWith('rafin-')).map((n) => caches.delete(n)));
}

// ---------------------------------------------------------------------------
// Strategi pengambilan
// ---------------------------------------------------------------------------

self.addEventListener('fetch', (event) => {
    const permintaan = event.request;
    const url = new URL(permintaan.url);

    if (url.origin !== self.location.origin) return;

    // Mutasi tidak pernah di-cache dan tidak pernah dilayani dari cache.
    if (permintaan.method !== 'GET') return;

    // Lampiran: cache-first dengan batas ukuran.
    if (url.pathname.startsWith('/lampiran/')) {
        event.respondWith(lampiran(permintaan));
        return;
    }

    // Halaman aplikasi: tampilkan yang tersimpan lebih dulu, perbarui di
    // belakang. Buku kas yang terbuka seketika dengan data semenit lalu jauh
    // lebih berguna daripada layar kosong yang menunggu jaringan.
    if (permintaan.mode === 'navigate' || url.pathname.startsWith('/app')) {
        event.respondWith(halaman(permintaan));
    }
});

async function halaman(permintaan) {
    const cache = await caches.open(CACHE_HALAMAN);
    const tersimpan = await cache.match(permintaan);

    const jaringan = fetch(permintaan)
        .then((balasan) => {
            if (balasan.ok) cache.put(permintaan, balasan.clone());
            return balasan;
        })
        .catch(() => null);

    if (tersimpan) {
        jaringan.catch(() => {});
        return tersimpan;
    }

    const balasan = await jaringan;

    return (
        balasan ||
        new Response(
            '<!doctype html><html lang="id"><meta charset="utf-8">' +
                '<meta name="viewport" content="width=device-width,initial-scale=1">' +
                '<title>Offline · Rafin</title>' +
                '<body style="font-family:system-ui;padding:2rem;max-width:30rem;margin:auto">' +
                '<h1 style="font-size:1.25rem">Sedang offline</h1>' +
                '<p>Halaman ini belum pernah dibuka sejak terakhir online, jadi belum tersimpan.</p>' +
                '<p>Catatan yang Anda buat tetap tersimpan dan akan terkirim sendiri begitu ada sinyal.</p>' +
                '</body></html>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 200 },
        )
    );
}

async function lampiran(permintaan) {
    const cache = await caches.open(CACHE_LAMPIRAN);
    const tersimpan = await cache.match(permintaan);
    if (tersimpan) return tersimpan;

    const balasan = await fetch(permintaan);

    if (balasan.ok) {
        await cache.put(permintaan, balasan.clone());
        await pangkasLampiran(cache);
    }

    return balasan;
}

async function pangkasLampiran(cache) {
    const kunci = await cache.keys();
    let total = 0;

    // Dihitung dari yang terbaru ke terlama; yang melewati batas dibuang.
    for (let i = kunci.length - 1; i >= 0; i--) {
        const balasan = await cache.match(kunci[i]);
        const ukuran = Number(balasan?.headers.get('content-length') || 0);
        total += ukuran;

        if (total > BATAS_LAMPIRAN) {
            await cache.delete(kunci[i]);
        }
    }
}
