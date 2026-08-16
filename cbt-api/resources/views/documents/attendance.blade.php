<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 32px; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .letterhead { border-bottom: 2px solid #111827; margin-bottom: 10px; padding-bottom: 8px; text-align: center; }
        .letterhead img { max-height: 86px; max-width: 100%; }
        .school-name { font-size: 17px; font-weight: bold; margin: 0; }
        .school-address { font-size: 8px; margin: 3px 0 0; }
        h1 { font-size: 14px; margin: 10px 0 12px; text-align: center; }
        .meta { border-collapse: collapse; margin-bottom: 12px; width: 100%; }
        .meta td { padding: 2px 4px; vertical-align: top; }
        .meta .label { font-weight: bold; width: 90px; }
        .data { border-collapse: collapse; width: 100%; }
        .data th { background: #dc2626; color: white; padding: 7px 4px; }
        .data td { border: 1px solid #9ca3af; height: 25px; padding: 4px; }
        .center { text-align: center; }
        .signature { margin-left: auto; margin-top: 18px; text-align: center; width: 210px; }
        .signature-space { height: 55px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
@forelse ($rooms as $room)
    <section class="page">
        @include('documents.partials.letterhead')
        <h1>DAFTAR HADIR PESERTA UJIAN</h1>
        <table class="meta">
            <tr><td class="label">Ujian</td><td>: {{ $exam->name }}</td><td class="label">Ruang</td><td>: {{ $room['name'] }}</td></tr>
            <tr><td class="label">Mata Pelajaran</td><td>: {{ $exam->subject->name }}</td><td class="label">Tanggal</td><td>: {{ $exam->start_at->locale('id')->translatedFormat('d F Y, H:i') }}</td></tr>
            <tr><td class="label">Durasi</td><td>: {{ $exam->duration_minutes }} menit</td><td class="label">Pengawas</td><td>: {{ $room['observer_name'] }}</td></tr>
        </table>
        <table class="data">
            <thead><tr><th style="width: 34px">No</th><th style="width: 90px">NISN</th><th>Nama Peserta</th><th style="width: 70px">Kelas</th><th style="width: 78px">Masuk</th><th style="width: 78px">Keluar</th><th style="width: 100px">Tanda Tangan</th></tr></thead>
            <tbody>
            @forelse ($room['participants'] as $index => $participant)
                <tr><td class="center">{{ $index + 1 }}</td><td>{{ $participant['nisn'] }}</td><td>{{ $participant['name'] }}</td><td class="center">{{ $participant['classroom'] }}</td><td></td><td></td><td></td></tr>
            @empty
                <tr><td colspan="7" class="center muted">Belum ada peserta yang dipetakan ke ruang ini.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="signature">{{ $school->address }}, {{ $exam->start_at->locale('id')->translatedFormat('d F Y') }}<br>Pengawas Ruang<div class="signature-space"></div><strong>{{ $room['observer_name'] }}</strong></div>
    </section>
@empty
    <section class="page">@include('documents.partials.letterhead')<h1>DAFTAR HADIR PESERTA UJIAN</h1><p class="center muted">Belum ada ruang yang ditautkan ke ujian ini.</p></section>
@endforelse
</body>
</html>
