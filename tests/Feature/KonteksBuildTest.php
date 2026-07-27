<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konteks build Docker
|--------------------------------------------------------------------------
|
| Test ini lahir dari satu deploy yang gagal, dan bentuk kegagalannya layak
| dicatat karena ia akan terulang dalam rupa lain.
|
| Tema panel admin mengimpor CSS dasar Filament dari vendor/. Di laptop hal itu
| bekerja sempurna: vendor/ ada di sana, `npm run build` hijau, semua test lulus.
| Di dalam Docker ia langsung mati — .dockerignore mengecualikan vendor/ dengan
| sengaja, jadi berkas itu tidak pernah sampai ke tahap yang membangun aset.
|
| Yang berbahaya bukan kesalahannya, melainkan bahwa TIDAK ADA satu pun
| pemeriksaan lokal yang bisa melihatnya. Suite hijau, Pint hijau, build lokal
| hijau, dan deploy tetap mati.
|
| Karena itu yang diuji di sini adalah hubungan antara dua berkas yang tidak
| pernah saling menyebut: setiap jalur yang diimpor atau dipindai lembar gaya
| harus benar-benar tersedia di tahap Docker yang membutuhkannya.
|
*/

/** Membaca seluruh jalur relatif yang dirujuk sebuah berkas CSS. */
function jalurYangDirujuk(string $berkasCss): array
{
    $isi = (string) file_get_contents($berkasCss);

    preg_match_all("/@(?:import|source)\s+'([^']+)'/", $isi, $cocok);

    return array_values(array_filter(
        $cocok[1],
        static fn (string $j): bool => str_starts_with($j, '.'),
    ));
}

it('menyediakan setiap jalur yang dirujuk tema admin di dalam konteks build', function (): void {
    $tema = resource_path('css/filament/admin/theme.css');
    $dirTema = dirname($tema);

    $dockerignore = (string) file_get_contents(base_path('.dockerignore'));
    $dikecualikan = array_values(array_filter(array_map(
        trim(...),
        explode("\n", $dockerignore),
    ), static fn (string $b): bool => $b !== '' && ! str_starts_with($b, '#') && ! str_starts_with($b, '!')));

    $dockerfile = (string) file_get_contents(base_path('deploy/docker/Dockerfile'));

    foreach (jalurYangDirujuk($tema) as $relatif) {
        // Glob dibuang; yang diperiksa adalah direktori akarnya.
        $bersih = (string) preg_replace('#/\*\*.*$#', '', $relatif);
        $absolut = realpath($dirTema.'/'.$bersih) ?: $dirTema.'/'.$bersih;

        $dariAkar = str_replace('\\', '/', (string) str_replace(
            str_replace('\\', '/', base_path()).'/',
            '',
            str_replace('\\', '/', $absolut),
        ));

        $akarTeratas = explode('/', $dariAkar)[0];

        // Kalau direktori teratasnya dikecualikan .dockerignore, ia HARUS
        // disalin lagi lewat COPY di Dockerfile — kalau tidak, build mati.
        //
        // str_contains(), bukan expect()->toContain(): toContain di Pest itu
        // variadik, jadi argumen kedua diperlakukan sebagai pola KEDUA yang
        // ikut dicari, bukan sebagai pesan kegagalan. Test yang ditulis begitu
        // gagal dengan alasan yang sama sekali berbeda dari yang dimaksud.
        if (in_array($akarTeratas, $dikecualikan, true)) {
            expect(str_contains($dockerfile, $akarTeratas))->toBeTrue(
                "theme.css merujuk {$relatif}, tapi {$akarTeratas}/ dikecualikan .dockerignore ".
                'dan tidak ada COPY di Dockerfile yang membawanya masuk. Build Docker akan gagal '.
                'meski build lokal hijau.',
            );
        }
    }
});

it('membawa berkas Filament ke tahap yang membangun aset', function (): void {
    $dockerfile = (string) file_get_contents(base_path('deploy/docker/Dockerfile'));

    // Tahap aset harus mendapat vendor/filament dari tahap vendor, karena
    // konteks build tidak memuatnya.
    expect($dockerfile)->toContain('COPY --from=vendor /var/www/html/vendor/filament');

    // Dan app/Filament, yang dipindai tema untuk kelas warna di PHP resource.
    expect($dockerfile)->toContain('COPY app/Filament');
});

it('tetap mengecualikan vendor dari konteks build', function (): void {
    // Kalau suatu saat ada yang "memperbaiki" masalah di atas dengan membuang
    // vendor dari .dockerignore, hasilnya adalah ratusan megabyte salinan lokal
    // terkirim ke daemon dan menimpa hasil composer install --no-dev.
    // Perbaikannya bukan itu.
    $dockerignore = (string) file_get_contents(base_path('.dockerignore'));

    expect(preg_match('/^vendor$/m', $dockerignore))->toBe(1)
        ->and(preg_match('/^node_modules$/m', $dockerignore))->toBe(1);
});
