<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 22px 25px; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .grid { border-collapse: separate; border-spacing: 8px; table-layout: fixed; width: 100%; }
        .grid > tbody > tr > td { height: 375px; vertical-align: top; width: 50%; }
        .card { border: 1.5px solid #374151; height: 360px; overflow: hidden; position: relative; }
        .card-head { border-bottom: 2px solid #dc2626; height: 64px; padding: 7px 9px; text-align: center; }
        .card-head img { max-height: 48px; max-width: 100%; }
        .school { font-size: 11px; font-weight: bold; margin: 8px 0 2px; }
        .card-title { background: #dc2626; color: white; font-size: 10px; font-weight: bold; padding: 5px; text-align: center; }
        .content { padding: 10px; }
        .identity { border-collapse: collapse; width: 100%; }
        .identity td { padding: 2px; vertical-align: top; }
        .photo-cell { width: 68px; }
        .photo { border: 1px solid #d1d5db; height: 82px; object-fit: cover; width: 64px; }
        .placeholder { background: #f3f4f6; border: 1px solid #d1d5db; box-sizing: border-box; color: #6b7280; font-size: 18px; font-weight: bold; height: 82px; padding-top: 29px; text-align: center; width: 64px; }
        .label { color: #6b7280; width: 61px; }
        .credentials { background: #f3f4f6; border: 1px dashed #9ca3af; margin-top: 9px; padding: 7px; }
        .credentials table { width: 100%; }
        .credentials td { padding: 2px; }
        .credentials strong { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
        .code { color: #dc2626; font-family: DejaVu Sans Mono, monospace; font-weight: bold; }
        .notice { color: #6b7280; font-size: 7px; margin-top: 6px; text-align: center; }
    </style>
</head>
<body>
@foreach ($cards->chunk(4) as $pageCards)
    <section class="page">
        <table class="grid"><tbody>
        @foreach ($pageCards->chunk(2) as $rowCards)
            <tr>
            @foreach ($rowCards as $card)
                <td><div class="card">
                    <div class="card-head">
                        @if ($letterhead)<img src="{{ $letterhead }}" alt="Kop surat">@else<p class="school">{{ strtoupper($school->name) }}</p><div>{{ $school->npsn }} | {{ $school->email }}</div>@endif
                    </div>
                    <div class="card-title">KARTU PESERTA UJIAN</div>
                    <div class="content">
                        <table class="identity"><tr><td class="photo-cell" rowspan="6">@if ($card['photo'])<img class="photo" src="{{ $card['photo'] }}" alt="Foto peserta">@else<div class="placeholder">{{ $card['initials'] }}</div>@endif</td><td class="label">Nama</td><td>: <strong>{{ $card['name'] }}</strong></td></tr><tr><td class="label">NISN</td><td>: {{ $card['nisn'] }}</td></tr><tr><td class="label">Kelas</td><td>: {{ $card['classroom'] }}</td></tr><tr><td class="label">Ruang</td><td>: {{ $card['room'] }}</td></tr><tr><td class="label">Ujian</td><td>: {{ $exam->name }}</td></tr><tr><td class="label">Jadwal</td><td>: {{ $exam->start_at->format('d/m/Y H:i') }}</td></tr></table>
                        <div class="credentials"><table><tr><td>Kode Ujian</td><td>: <span class="code">{{ $exam->access_code }}</span></td></tr><tr><td>Username</td><td>: <strong>{{ $card['username'] }}</strong></td></tr><tr><td>Password</td><td>: <strong>{{ $card['password'] }}</strong></td></tr></table></div>
                        <div class="notice">Simpan kartu ini dengan aman. Kredensial bersifat rahasia.</div>
                    </div>
                </div></td>
            @endforeach
            @if ($rowCards->count() === 1)<td></td>@endif
            </tr>
        @endforeach
        </tbody></table>
    </section>
@endforeach
</body>
</html>
