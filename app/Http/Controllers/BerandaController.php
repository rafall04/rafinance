<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman depan.
 *
 * Sebuah controller, bukan closure di routes/web.php. Produksi menjalankan
 * `route:cache`, dan closure tidak bisa diserialisasi — memakainya akan
 * menggagalkan kontainer saat start, bukan saat request pertama.
 *
 * Orang yang sudah masuk langsung dibawa ke buku kasnya. Memaksa mereka
 * melewati halaman pemasaran setiap kali membuka alamat utama adalah tol yang
 * dibayar tiap hari oleh orang yang sudah terlanjur percaya.
 */
final class BerandaController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to(config('fortify.home', '/app'));
        }

        return view('beranda');
    }
}
