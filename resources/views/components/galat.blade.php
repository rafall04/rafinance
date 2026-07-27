@props(['untuk'])

{{--
    Pesan galat menjelaskan apa yang terjadi dan bagaimana memperbaikinya,
    tanpa meminta maaf. Permintaan maaf tidak membantu siapa pun memperbaiki
    isian yang salah.
--}}
@error($untuk)
    <p class="pesan-galat mt-1.5" id="galat-{{ $untuk }}">{{ $message }}</p>
@enderror
