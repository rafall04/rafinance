/*
 * Pengiriman antrean offline — satu-satunya salinan aturannya.
 *
 * Dipakai dua tempat yang berbeda sifatnya:
 *
 *   service worker  — jalur utama, hidup meski tab ditutup, punya Background Sync
 *   halaman         — cadangan, dipakai saat service worker tidak ada
 *
 * Kenapa cadangannya wajib ada: service worker bisa gagal terdaftar karena
 * hal-hal di luar kendali kode ini — mode privat, kebijakan perangkat, atau
 * satu header yang salah di server. Kalau pengiriman hanya hidup di dalam
 * service worker, kegagalan itu berarti transaksi mengendap di IndexedDB
 * selamanya sementara pengguna sudah membaca "tersimpan". Itu persis yang
 * terjadi di produksi sampai Juli 2026: nol service worker terdaftar, dan
 * antrean yang tidak pernah dikuras siapa pun.
 *
 * Yang membuat pengulangan aman bukan berkas ini, melainkan ULID: id transaksi
 * dibuat di ponsel dan dipakai sekaligus sebagai Idempotency-Key, jadi kiriman
 * kedua mengembalikan transaksi yang sudah ada (aturan A7 dan A9).
 */

import { openDB } from 'idb';

export const NAMA_DB = 'rafin';
export const TOKO = 'antrean';

export async function bukaDb() {
    return openDB(NAMA_DB, 1, {
        upgrade(database) {
            if (!database.objectStoreNames.contains(TOKO)) {
                database.createObjectStore(TOKO, { keyPath: 'id' });
            }
        },
    });
}

/**
 * Baris yang masih menunggu giliran kirim.
 *
 * Yang ditandai `ditolak` tidak ikut: server sudah menyatakan bentuknya tidak
 * akan pernah sah, jadi mengirimnya lagi hanya membakar baterai. Ia tetap
 * disimpan supaya pemiliknya bisa melihat dan memperbaikinya — lihat catatan
 * di tanganiBalasan().
 */
export async function antreanTertunda() {
    const semua = await (await bukaDb()).getAll(TOKO);

    return semua.filter((baris) => !baris.ditolak);
}

export async function antreanDitolak() {
    const semua = await (await bukaDb()).getAll(TOKO);

    return semua.filter((baris) => baris.ditolak);
}

async function buang(id) {
    return (await bukaDb()).delete(TOKO, id);
}

async function perbarui(id, ubahan) {
    const koneksi = await bukaDb();
    const baris = await koneksi.get(TOKO, id);
    if (!baris) return;

    await koneksi.put(TOKO, { ...baris, ...ubahan });
}

/**
 * Mengirim seluruh antrean. Aman dipanggil berkali-kali.
 *
 * `csrfSegar` adalah token dari halaman yang sedang terbuka, kalau ada.
 * Ia dipakai menggantikan token yang tersimpan bersama barisnya, dan itu
 * bukan kerapian:
 *
 *   - Token yang disimpan saat mengantre ikut mati bersama sesinya, dan sesi
 *     Rafin berumur dua jam. Transaksi yang menunggu sinyal lebih lama dari
 *     itu akan selamanya dijawab 419 — tersimpan di ponsel, tidak pernah
 *     sampai, dan tidak ada yang memberi tahu pemiliknya.
 *   - Sesudah seseorang keluar, tokennya juga mati. Itu justru yang kita mau:
 *     antrean milik orang sebelumnya dijawab 419 dan ditahan, bukan terkirim
 *     ke dalam sesi orang berikutnya yang masuk di ponsel yang sama.
 *
 * @returns {Promise<{sisa: number, terkirim: number, ditolak: number}>}
 */
export async function kirimAntrean(csrfSegar = null) {
    const daftar = await antreanTertunda();
    let terkirim = 0;

    for (const baris of daftar) {
        let balasan;

        try {
            balasan = await fetch(baris.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfSegar || baris.csrf,
                    'Idempotency-Key': baris.id,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(baris.payload),
                credentials: 'same-origin',
            });
        } catch (galat) {
            // Masih offline. Berhenti — sisanya akan gagal dengan cara yang
            // sama, dan mencobanya satu per satu hanya menghabiskan baterai.
            await perbarui(baris.id, {
                percobaan: (baris.percobaan || 0) + 1,
                galat: String(galat.message).slice(0, 300),
            });
            break;
        }

        const lanjut = await tanganiBalasan(baris, balasan);

        if (lanjut === 'terkirim') terkirim++;
        if (lanjut === 'berhenti') break;
    }

    const [sisa, ditolak] = await Promise.all([
        antreanTertunda().then((d) => d.length),
        antreanDitolak().then((d) => d.length),
    ]);

    return { sisa, terkirim, ditolak };
}

/**
 * @returns {Promise<'terkirim'|'lanjut'|'berhenti'>}
 */
async function tanganiBalasan(baris, balasan) {
    // 409 berarti server sudah pernah menerimanya. Dari sudut pandang ponsel,
    // itu keberhasilan — bukan galat.
    if (balasan.ok || balasan.status === 409) {
        await buang(baris.id);

        return 'terkirim';
    }

    // Sesi habis. Tahan seluruh antrean apa adanya; pengguna akan masuk lagi
    // dan pengiriman dilanjutkan dengan token yang baru.
    if (balasan.status === 401 || balasan.status === 419) {
        return 'berhenti';
    }

    // 422 berarti bentuknya tidak akan pernah sah, jadi mengulanginya memang
    // sia-sia. Tapi menghapusnya diam-diam — yang dilakukan versi sebelumnya —
    // berarti catatan pengeluaran seseorang lenyap tanpa ia pernah diberi
    // tahu. Untuk buku kas, itu lebih buruk daripada galat yang berisik.
    //
    // Jadi ia ditahan, ditandai, dan berhenti diulang. Lencana antrean yang
    // menyebutkannya adalah satu-satunya alasan pemiliknya bisa tahu.
    if (balasan.status === 422) {
        const isi = await balasan.json().catch(() => null);

        await perbarui(baris.id, {
            ditolak: true,
            galat: (isi?.message ?? 'Ditolak server').slice(0, 300),
        });

        return 'lanjut';
    }

    await perbarui(baris.id, {
        percobaan: (baris.percobaan || 0) + 1,
        galat: `HTTP ${balasan.status}`,
    });

    return 'lanjut';
}
