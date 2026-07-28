<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domain\Billing\Exceptions\QuotaTerlampaui;
use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Billing\Services\QuotaGuard;
use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Capture\Models\InboxItem;
use App\Domain\Capture\Services\CaptureText;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Attachment;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Menerima kiriman dari tombol "Bagikan" aplikasi lain.
 *
 * Ini jalur yang paling sering dipakai orang di lapangan: notifikasi m-banking
 * atau e-wallet ditekan-tahan, dibagikan ke Rafin, selesai. Tidak ada
 * pengetikan sama sekali, dan tidak ada kesempatan salah ketik nominal.
 *
 * Yang masuk lewat sini tidak pernah ditolak. Yang bisa dibaca jadi transaksi,
 * yang tidak jadi item inbox.
 */
final class ShareTargetController
{
    public function __invoke(Request $request, CaptureText $tangkap, TenantContext $tenant): RedirectResponse
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:4000'],
            'url' => ['nullable', 'string', 'max:2000'],
            'berkas' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        $workspace = $tenant->workspace();

        if ($workspace === null) {
            return redirect()->route('onboarding.workspace');
        }

        $teks = trim(implode(' ', array_filter([
            $request->string('title')->toString(),
            $request->string('text')->toString(),
        ])));

        $berkas = $request->file('berkas');

        if ($berkas === null && $teks === '') {
            return redirect()->route('app.inbox')
                ->with('kabar', 'Tidak ada yang bisa dibaca dari kiriman itu.');
        }

        if ($berkas === null) {
            $hasil = $tangkap(
                teks: $teks,
                sumber: TransactionSource::PwaOffline,
                pengguna: $request->user(),
                currency: $workspace->currency,
            );

            return redirect()->route('app.inbox')->with(
                'kabar',
                $hasil->berhasil() ? 'Tersimpan.' : 'Masuk inbox, lengkapi nanti.',
            );
        }

        // Kuota lampiran diperiksa SEBELUM berkasnya ditulis ke disk. Kalau
        // diperiksa sesudahnya, berkas yang ditolak tetap menempati ruang dan
        // hanya bisa dibersihkan oleh orang yang ingat bahwa ia ada.
        try {
            app(QuotaGuard::class)->pastikanBolehMenambah(
                UsageCounter::LAMPIRAN_BYTE,
                (int) $berkas->getSize(),
            );
        } catch (QuotaTerlampaui) {
            // Teksnya tetap diselamatkan kalau ada — orang yang berbagi struk
            // beserta keterangannya tidak boleh kehilangan keduanya sekaligus
            // hanya karena gambarnya tidak muat.
            if ($teks !== '') {
                $tangkap(
                    teks: $teks,
                    sumber: TransactionSource::PwaOffline,
                    pengguna: $request->user(),
                    currency: $workspace->currency,
                );
            }

            return redirect()->route('app.inbox')->with(
                'kabar',
                'Kuota lampiran sudah penuh, jadi gambarnya tidak disimpan.'
                    .($teks !== '' ? ' Keterangannya masuk inbox.' : ''),
            );
        }

        // Ada gambar: simpan sebagai lampiran dan buat item inbox. OCR menunggu
        // plan berbayar dan sekarang dimatikan feature flag (aturan A12).
        $item = InboxItem::query()->create([
            'source' => TransactionSource::PwaOffline,
            'raw_text' => $teks !== '' ? $teks : null,
            'parse_status' => ParseStatus::Failed,
            'created_by' => $request->user()?->getKey(),
        ]);

        $path = $berkas->store('lampiran/'.$workspace->getKey(), Attachment::DISK);

        Attachment::query()->create([
            'disk_path' => $path,
            'mime' => $berkas->getClientMimeType(),
            'size_bytes' => $berkas->getSize(),
            'sha256' => hash_file('sha256', $berkas->getRealPath()) ?: Str::random(64),
            'uploaded_by' => $request->user()?->getKey(),
        ]);

        $item->forceFill(['media_path' => $path])->save();

        app(QuotaGuard::class)->catatPemakaian(UsageCounter::LAMPIRAN_BYTE, (int) $berkas->getSize());

        return redirect()->route('app.inbox')->with('kabar', 'Gambar tersimpan di inbox.');
    }
}
