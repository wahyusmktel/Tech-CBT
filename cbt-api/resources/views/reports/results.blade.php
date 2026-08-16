<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 34px; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .letterhead { border-bottom: 2px solid #111827; margin-bottom: 14px; padding-bottom: 10px; text-align: center; }
        .letterhead img { max-height: 105px; max-width: 100%; }
        .school { font-size: 17px; font-weight: bold; margin: 0; }
        .address { font-size: 9px; margin: 4px 0 0; }
        h1 { font-size: 15px; margin: 13px 0 4px; text-align: center; }
        .subtitle { margin: 0 0 14px; text-align: center; }
        .summary { margin-bottom: 14px; width: 100%; }
        .summary td { background: #f3f4f6; border: 1px solid #d1d5db; padding: 7px; text-align: center; }
        table.data { border-collapse: collapse; width: 100%; }
        .data th { background: #dc2626; color: white; padding: 7px 5px; }
        .data td { border: 1px solid #d1d5db; padding: 6px 5px; }
        .center { text-align: center; }
        .footer { color: #6b7280; font-size: 8px; margin-top: 12px; text-align: right; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if ($letterhead)
            <img src="{{ $letterhead }}" alt="Kop surat">
        @else
            <p class="school">{{ strtoupper($school->name) }}</p>
            <p class="address">{{ $school->address }} | NPSN {{ $school->npsn }} | {{ $school->email }}</p>
        @endif
    </div>
    <h1>REKAP HASIL UJIAN</h1>
    <p class="subtitle">{{ $report['exam']['name'] }} - {{ $report['exam']['subject'] }}<br>{{ date('d/m/Y H:i', strtotime($report['exam']['start_at'])) }}</p>
    <table class="summary"><tr>
        <td>Peserta<br><strong>{{ $report['summary']['participant_count'] }}</strong></td>
        <td>Selesai<br><strong>{{ $report['summary']['finished_count'] }}</strong></td>
        <td>Rata-rata<br><strong>{{ $report['summary']['average_score'] !== null ? number_format($report['summary']['average_score'], 2, ',', '.') : '-' }}</strong></td>
        <td>Tertinggi<br><strong>{{ $report['summary']['highest_score'] !== null ? number_format($report['summary']['highest_score'], 2, ',', '.') : '-' }}</strong></td>
        <td>Terendah<br><strong>{{ $report['summary']['lowest_score'] !== null ? number_format($report['summary']['lowest_score'], 2, ',', '.') : '-' }}</strong></td>
    </tr></table>
    <table class="data">
        <thead><tr><th>No</th><th>NISN</th><th>Nama Siswa</th><th>Kelas</th><th>Status</th><th>Nilai</th></tr></thead>
        <tbody>
        @forelse ($report['results'] as $index => $result)
            <tr><td class="center">{{ $index + 1 }}</td><td>{{ $result['nisn'] }}</td><td>{{ $result['name'] }}</td><td>{{ $result['classroom'] }}</td><td class="center">{{ $result['status'] === 'finished' ? 'Selesai' : ($result['status'] === 'in_progress' ? 'Mengerjakan' : 'Belum mulai') }}</td><td class="center">{{ $result['score'] !== null ? number_format($result['score'], 2, ',', '.') : '-' }}</td></tr>
        @empty
            <tr><td colspan="6" class="center">Belum ada peserta ujian.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="footer">Dicetak oleh Teknoplek CBT pada {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
