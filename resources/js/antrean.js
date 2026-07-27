import { openDB } from 'idb';

/*
 * Antrean transaksi di sisi client.
 *
 * Aturan yang tidak boleh dilanggar: formulir tambah transaksi SELALU bisa
 * disubmit, online maupun tidak. Aplikasi keuangan yang menolak mencatat saat
 * sinyal hilang akan ditinggalkan, karena sinyal paling sering hilang justru di
 * tempat orang mengeluarkan uang — pasar, parkiran basement, jalan antar kota.
 */

const NAMA_DB = 'rafin';
const TOKO = 'antrean';

async function db() {
    return openDB(NAMA_DB, 1, {
        upgrade(database) {
            if (!database.objectStoreNames.contains(TOKO)) {
                database.createObjectStore(TOKO, { keyPath: 'id' });
            }
        },
    });
}

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
    const koneksi = await db();

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
    return (await db()).count(TOKO);
}

export async function daftarTertunda() {
    return (await db()).getAll(TOKO);
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
    const registrasi = await navigator.serviceWorker?.ready;

    if (!registrasi) return;

    if ('sync' in registrasi) {
        try {
            await registrasi.sync.register('rafin-antrean');
            return;
        } catch {
            // Izin ditolak atau tidak didukung; jatuh ke cara manual.
        }
    }

    registrasi.active?.postMessage({ type: 'KIRIM_ANTREAN' });
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
