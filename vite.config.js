import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Tema panel admin. Berkas terpisah, bukan bagian app.css:
                // Filament membawa Tailwind-nya sendiri dengan source(none),
                // dan menggabungkannya akan menyeret seluruh utilitas panel ke
                // dalam bundel yang diunduh setiap pengguna aplikasi.
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
        tailwindcss(),

        // injectManifest, bukan generateSW: antrean offline butuh service
        // worker tulisan tangan. Strategi bawaan Workbox bisa meng-cache
        // halaman, tapi tidak bisa menahan transaksi yang gagal terkirim lalu
        // mengirimkannya lagi tanpa menggandakannya.
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            registerType: 'prompt',
            injectRegister: null,
            outDir: 'public/build',
            manifestFilename: 'manifest.webmanifest',
            injectManifest: {
                globDirectory: 'public/build',
                globPatterns: ['**/*.{js,css,woff2}'],
                globIgnores: ['**/lampiran/**'],
            },
            manifest: {
                id: '/app',
                name: 'Rafin',
                short_name: 'Rafin',
                description: 'Buku kas untuk pribadi dan usaha kecil.',
                lang: 'id',
                dir: 'ltr',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/app',
                scope: '/',
                background_color: '#FBFBF9',
                theme_color: '#FBFBF9',
                categories: ['finance', 'productivity'],
                // ?v= bukan hiasan. Nama berkas ikon tetap sepanjang umur
                // aplikasi, sementara Cloudflare meng-cache .png dan .ico
                // berdasarkan ekstensinya tanpa menunggu perintah dari asalnya.
                // Tanpa penanda versi, ikon yang diganti akan tetap disajikan
                // versi lamanya sampai seseorang membersihkan cache lewat
                // dasbor — dan berkas yang hanya bisa diperbarui lewat dasbor
                // pihak ketiga bukan berkas yang benar-benar kita kendalikan.
                //
                // Naikkan angkanya setiap kali ikonnya berubah, berbarengan
                // dengan tag <link> di resources/views/components/layouts/.
                icons: [
                    { src: '/ikon/192.png?v=2', sizes: '192x192', type: 'image/png' },
                    { src: '/ikon/512.png?v=2', sizes: '512x512', type: 'image/png' },
                    { src: '/ikon/maskable-512.png?v=2', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],

                // Tekan-tahan ikon di layar utama. Empat hal yang paling sering
                // dilakukan, langsung tanpa membuka beranda dulu.
                shortcuts: [
                    { name: 'Catat pengeluaran', short_name: 'Keluar', url: '/app/tambah?kind=expense' },
                    { name: 'Catat pemasukan', short_name: 'Masuk', url: '/app/tambah?kind=income' },
                    { name: 'Lihat saldo', short_name: 'Saldo', url: '/app/akun' },
                    { name: 'Inbox', short_name: 'Inbox', url: '/app/inbox' },
                ],

                // Berbagi tangkapan layar struk atau teks notifikasi bank dari
                // aplikasi lain langsung ke Rafin. Masuk ke inbox.
                share_target: {
                    action: '/app/share',
                    method: 'POST',
                    enctype: 'multipart/form-data',
                    params: {
                        title: 'title',
                        text: 'text',
                        url: 'url',
                        files: [
                            { name: 'berkas', accept: ['image/*'] },
                        ],
                    },
                },
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
