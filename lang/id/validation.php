<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pesan validasi
|--------------------------------------------------------------------------
|
| Kalimat aktif, tanpa permintaan maaf, dan sedapat mungkin memberi tahu apa
| yang harus dilakukan — bukan sekadar menyatakan bahwa isian salah.
|
| Yang ditulis di sini adalah aturan yang benar-benar dipakai Rafin. Aturan
| yang belum dipakai sengaja tidak diborong dari daftar bawaan Laravel supaya
| berkas ini tetap bisa dibaca dan tetap jujur soal apa yang sudah dipikirkan.
|
*/

return [

    'accepted' => 'Bagian :attribute harus disetujui.',
    'after' => 'Isi :attribute dengan tanggal setelah :date.',
    'after_or_equal' => 'Isi :attribute dengan tanggal :date atau sesudahnya.',
    'alpha_dash' => 'Isi :attribute hanya dengan huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Isi :attribute hanya dengan huruf dan angka.',
    'before' => 'Isi :attribute dengan tanggal sebelum :date.',
    'before_or_equal' => 'Isi :attribute dengan tanggal :date atau sebelumnya.',
    'boolean' => 'Isi :attribute hanya dengan ya atau tidak.',
    'confirmed' => 'Ulangan :attribute belum sama.',
    'current_password' => 'Kata sandi tidak cocok.',
    'date' => 'Isi :attribute dengan tanggal yang benar.',
    'declined' => 'Bagian :attribute harus ditolak.',
    'different' => 'Isi :attribute berbeda dari :other.',
    'digits' => 'Isi :attribute dengan :digits angka.',
    'digits_between' => 'Isi :attribute dengan :min sampai :max angka.',
    'email' => 'Isi :attribute dengan alamat surel yang benar.',
    'exists' => 'Pilihan :attribute tidak ditemukan.',
    'file' => 'Unggah :attribute sebagai berkas.',
    'filled' => 'Isian :attribute tidak boleh dikosongkan.',
    'image' => 'Unggah :attribute sebagai gambar.',
    'in' => 'Pilihan :attribute tidak tersedia.',
    'integer' => 'Isi :attribute dengan bilangan bulat.',
    'max' => [
        'array' => 'Pilih paling banyak :max :attribute.',
        'file' => 'Ukuran :attribute paling besar :max kilobyte.',
        'numeric' => 'Isi :attribute paling besar :max.',
        'string' => 'Isi :attribute paling panjang :max karakter.',
    ],
    'mimes' => 'Jenis berkas :attribute harus :values.',
    'min' => [
        'array' => 'Pilih setidaknya :min :attribute.',
        'file' => 'Ukuran :attribute setidaknya :min kilobyte.',
        'numeric' => 'Isi :attribute setidaknya :min.',
        'string' => 'Isi :attribute setidaknya :min karakter.',
    ],
    'not_in' => 'Pilihan :attribute tidak tersedia.',
    'numeric' => 'Isi :attribute dengan angka.',
    'password' => [
        'letters' => 'Kata sandi harus memuat setidaknya satu huruf.',
        'mixed' => 'Kata sandi harus memuat huruf besar dan huruf kecil.',
        'numbers' => 'Kata sandi harus memuat setidaknya satu angka.',
        'symbols' => 'Kata sandi harus memuat setidaknya satu simbol.',
        'uncompromised' => 'Kata sandi ini pernah bocor di kebocoran data. Pilih yang lain.',
    ],
    'present' => 'Isian :attribute harus disertakan.',
    'prohibited' => 'Isian :attribute tidak boleh diisi.',
    'regex' => 'Bentuk :attribute belum sesuai.',
    'required' => 'Isian :attribute wajib diisi.',
    'required_if' => 'Isian :attribute wajib diisi kalau :other bernilai :value.',
    'required_with' => 'Isian :attribute wajib diisi bersama :values.',
    'same' => 'Isi :attribute dan :other harus sama.',
    'size' => [
        'array' => 'Pilih tepat :size :attribute.',
        'file' => 'Ukuran :attribute harus :size kilobyte.',
        'numeric' => 'Isi :attribute harus :size.',
        'string' => 'Isi :attribute harus :size karakter.',
    ],
    'string' => 'Isi :attribute dengan teks.',
    'timezone' => 'Pilih zona waktu yang tersedia.',
    'unique' => 'Nilai :attribute ini sudah dipakai.',
    'uploaded' => 'Berkas :attribute gagal diunggah. Coba lagi.',
    'url' => 'Isi :attribute dengan tautan yang benar.',
    'ulid' => 'Isi :attribute dengan ULID yang benar.',

    'custom' => [
        'email' => [
            'unique' => 'Surel ini sudah terdaftar. Masuk saja, atau setel ulang kata sandinya.',
        ],
    ],

    'attributes' => [
        'name' => 'nama',
        'email' => 'surel',
        'password' => 'kata sandi',
        'password_confirmation' => 'ulangan kata sandi',
        'current_password' => 'kata sandi saat ini',
    ],

];
