<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 25px 34px; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .letterhead { border-bottom: 2px solid #111827; margin-bottom: 12px; padding-bottom: 9px; text-align: center; }
        .letterhead img { max-height: 88px; max-width: 100%; }
        .school-name { font-size: 17px; font-weight: bold; margin: 0; }
        .school-address { font-size: 8px; margin: 3px 0 0; }
        h1 { font-size: 15px; margin: 12px 0 4px; text-align: center; }
        .number { margin: 0 0 18px; text-align: center; }
        .details { border-collapse: collapse; margin: 10px 0 18px; width: 100%; }
        .details td { padding: 3px 5px; vertical-align: top; }
        .details .label { font-weight: bold; width: 145px; }
        .box { border: 1px solid #9ca3af; margin: 14px 0; min-height: 72px; padding: 10px; }
        .box-title { font-weight: bold; margin-bottom: 7px; }
        .signatures { margin-top: 26px; width: 100%; }
        .signatures td { text-align: center; width: 50%; }
        .space { height: 62px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
@forelse ($rooms as $room)
    <section class="page">
        @include('documents.partials.letterhead')
        <h1>BERITA ACARA PELAKSANAAN UJIAN</h1>
        <p class="number">Nomor: ............................................................</p>
        <p>Pada hari ini, <strong>{{ $exam->start_at->locale('id')->translatedFormat('l') }}</strong>, tanggal <strong>{{ $exam->start_at->locale('id')->translatedFormat('d F Y') }}</strong>, telah dilaksanakan ujian dengan rincian sebagai berikut:</p>
        <table class="details">
            <tr><td class="label">Nama Ujian</td><td>: {{ $exam->name }}</td></tr>
            <tr><td class="label">Mata Pelajaran</td><td>: {{ $exam->subject->name }}</td></tr>
            <tr><td class="label">Ruang</td><td>: {{ $room['name'] }}</td></tr>
            <tr><td class="label">Waktu</td><td>: {{ $exam->start_at->format('H:i') }} WIB - selesai (durasi {{ $exam->duration_minutes }} menit)</td></tr>
            <tr><td class="label">Peserta Terdaftar</td><td>: {{ count($room['participants']) }} peserta</td></tr>
            <tr><td class="label">Peserta Login</td><td>: {{ $room['started_count'] }} peserta</td></tr>
            <tr><td class="label">Peserta Selesai</td><td>: {{ $room['finished_count'] }} peserta</td></tr>
        </table>
        <div class="box"><div class="box-title">Catatan/Kejadian Selama Ujian:</div><span class="muted">................................................................................................................................................................................................................................<br><br>................................................................................................................................................................................................................................</span></div>
        <div class="box"><div class="box-title">Tindak Lanjut:</div><span class="muted">................................................................................................................................................................................................................................<br><br>................................................................................................................................................................................................................................</span></div>
        <p>Demikian berita acara ini dibuat dengan sebenarnya untuk dapat digunakan sebagaimana mestinya.</p>
        <table class="signatures"><tr><td>Mengetahui,<br>Kepala Sekolah<div class="space"></div><strong>{{ $school->principal_name ?: '(........................................)' }}</strong></td><td>{{ $school->address }}, {{ $exam->start_at->locale('id')->translatedFormat('d F Y') }}<br>Pengawas Ruang<div class="space"></div><strong>{{ $room['observer_name'] }}</strong></td></tr></table>
    </section>
@empty
    <section class="page">@include('documents.partials.letterhead')<h1>BERITA ACARA PELAKSANAAN UJIAN</h1><p class="muted" style="text-align:center">Belum ada ruang yang ditautkan ke ujian ini.</p></section>
@endforelse
</body>
</html>
