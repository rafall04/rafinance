import { TOKO, antreanDitolak, antreanTertunda, bukaDb, kirimAntrean } from './kirim-antrean';

/*
 * Antrean transaksi di sisi client.
 *
 * Aturan yang tidak boleh dilanggar: formulir tambah transaksi SELALU bisa
 * disubmit, online maupun tidak. Aplikasi keuangan yang menolak mencatat saat
 * sinyal hilang akan ditinggalkan, karena sinyal paling sering hilang justru di
 * tempat orang mengeluarkan uang — pasar, parkiran basement, jalan antar kota.
 *
 * Aturan penyimpanan dan pengirimannya ada di kirim-antrean.js, dipakai
 * bersama dengan service worker supaya keduanya tidak pernah berbeda pendapat
 * tentang apa arti 422.
 */

export { antreanDitolak };

/**
 * ULID sisi client.
 *
 * Dipakai sebagai id transaksi DAN sebagai Idempotency-Key, sehingga transaksi
 * yang sama tidak pernah tercatat dua kali betapapun sering pengirimannya
 * diulang (aturan A7 dan A9).
 */
export function ulid() {
    const HURUF = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    const waktu = Date.now();

    let bagianWaktu = '';
    let sisa = waktu;
    for (let i = 0; i < 10; i++) {
        bagianWaktu = HURUF[sisa % 32] + bagianWaktu;
        sisa = Math.floor(sisa / 32);
    }

    const acak = new Uint8Array(16);
    crypto.getRandomValues(acak);

    let bagianAcak = '';
    for (let i = 0; i < 16; i++) {
        bagianAcak += HURUF[acak[i] % 32];
    }

    return bagianWaktu + bagianAcak;
}

export async function antrekan(id, url, payload, csrf) {
    const koneksi = await bukaDb();

    await koneksi.put(TOKO, {
        id,
        url,
        payload,
        csrf,
        percobaan: 0,
        dibuatPada: Date.now(),
    });

    await mintaSinkron();

    return id;
}

export async function jumlahTertunda() {
    return (await antreanTertunda()).length;
}

export async function daftarTertunda() {
    return antreanTertunda();
}

/**
 * Membuang seluruh antrean beserta basis datanya.
 *
 * Dipanggil saat keluar. Antrean menyimpan transaksi milik orang yang tadi
 * masuk, lengkap dengan nominalnya; membiarkannya menetap berarti transaksi
 * itu akan terkirim ke sesi orang berikutnya yang masuk di ponsel yang sama.
 */
export async function kosongkanAntrean() {
    const koneksi = await bukaDb();

    await koneksi.clear(TOKO);
}

/**
 * Meminta service worker mengirim antrean.
 *
 * Background Sync dipakai kalau ada. Kalau tidak — dan di Safari memang tidak
 * ada sama sekali — permintaan dikirim langsung lewat postMessage, lalu diulang
 * saat aplikasi dibuka dan saat event 'online'. Itu sebabnya PWA tidak boleh
 * jadi satu-satunya jalur notifikasi: sebagian perangkat memang tidak
 * menyediakan mekanismenya.
 */
export async function mintaSinkron() {
    const registrasi = await pendaftaranAktif();

    // Tanpa service worker, halaman ini sendiri yang mengirim. Inilah yang
    // membuat antrean tetap terkuras di mode privat, di perangkat yang
    // kebijakannya melarang service worker, dan — seperti yang terjadi di
    // produksi — saat pendaftarannya gagal karena salah header.
    if (!registrasi || !registrasi.active) {
        return kirimDariHalaman();
    }

    if ('sync' in registrasi) {
        try {
            await registrasi.sync.register('rafin-antrean');
            return;
        } catch {
            // Izin ditolak atau tidak didukung; jatuh ke cara manual.
        }
    }

    registrasi.active.postMessage({ type: 'KIRIM_ANTREAN', csrf: tokenCsrf() });
}

function tokenCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? null;
}

/**
 * Pendaftaran service worker yang benar-benar ada, atau null.
 *
 * BUKAN navigator.serviceWorker.ready. Promise itu tidak pernah selesai kalau
 * tidak ada satu pun pendaftaran — ia menunggu selamanya alih-alih menjawab
 * null. Menunggunya di jalur ini berarti mintaSinkron() menggantung tanpa
 * suara, antrean tidak pernah terkirim, dan tidak ada galat apa pun yang bisa
 * ditelusuri. getRegistration() menjawab undefined, dan itu yang kita perlu.
 */
async function pendaftaranAktif() {
    if (!('serviceWorker' in navigator)) return null;

    try {
        return (await navigator.serviceWorker.getRegistration()) ?? null;
    } catch {
        return null;
    }
}

/**
 * Mengirim antrean langsung dari halaman, lalu mengabarkan hasilnya dengan
 * event yang sama bentuknya dengan pesan dari service worker.
 */
export async function kirimDariHalaman() {
    const hasil = await kirimAntrean(tokenCsrf());

    window.dispatchEvent(new CustomEvent('rafin:antrean', { detail: hasil }));

    return hasil;
}

/**
 * Mengompres gambar di ponsel sebelum diunggah.
 *
 * Foto kamera modern 4-8 MB. Mengunggahnya apa adanya lewat jaringan seluler
 * berkuota adalah biaya nyata bagi pengguna, dan struk tidak butuh resolusi
 * sebesar itu untuk terbaca.
 */
export async function kompresGambar(berkas, sisiMaks = 1600, mutu = 0.8) {
    if (!berkas.type.startsWith('image/')) return berkas;

    const bitmap = await createImageBitmap(berkas);
    const skala = Math.min(1, sisiMaks / Math.max(bitmap.width, bitmap.height));

    if (skala === 1 && berkas.size < 400 * 1024) return berkas;

    const kanvas = document.createElement('canvas');
    kanvas.width = Math.round(bitmap.width * skala);
    kanvas.height = Math.round(bitmap.height * skala);

    const konteks = kanvas.getContext('2d');
    konteks.drawImage(bitmap, 0, 0, kanvas.width, kanvas.height);
    bitmap.close();

    const blob = await new Promise((selesai) => kanvas.toBlob(selesai, 'image/jpeg', mutu));

    if (!blob || blob.size >= berkas.size) return berkas;

    return new File([blob], berkas.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
}
