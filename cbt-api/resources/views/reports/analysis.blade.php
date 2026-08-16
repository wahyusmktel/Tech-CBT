<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 25px 30px; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .letterhead { border-bottom: 2px solid #111827; margin-bottom: 10px; padding-bottom: 8px; text-align: center; }
        .letterhead img { max-height: 82px; max-width: 100%; }
        .school { font-size: 16px; font-weight: bold; margin: 0; }
        .address { font-size: 8px; margin: 3px 0 0; }
        h1 { font-size: 14px; margin: 10px 0 3px; text-align: center; }
        .subtitle { margin: 0 0 12px; text-align: center; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #dc2626; color: white; padding: 7px 4px; }
        td { border: 1px solid #d1d5db; padding: 6px 4px; vertical-align: top; }
        .center { text-align: center; }
        .question { width: 48%; }
        .footer { color: #6b7280; font-size: 8px; margin-top: 10px; text-align: right; }
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
    <h1>ANALISIS BUTIR SOAL</h1>
    <p class="subtitle">{{ $report['exam']['name'] }} - {{ $report['exam']['subject'] }} | Peserta selesai: {{ $report['summary']['finished_count'] }}</p>
    <table>
        <thead><tr><th>No</th><th class="question">Pertanyaan</th><th>Kunci</th><th>Benar</th><th>Salah</th><th>Kosong</th><th>% Benar</th></tr></thead>
        <tbody>
        @forelse ($report['question_analysis'] as $item)
            <tr><td class="center">{{ $item['number'] }}</td><td>{{ $item['text'] }}</td><td class="center">{{ $item['correct_answer'] }}</td><td class="center">{{ $item['correct_count'] }}</td><td class="center">{{ $item['wrong_count'] }}</td><td class="center">{{ $item['unanswered_count'] }}</td><td class="center">{{ number_format($item['correct_percentage'], 2, ',', '.') }}%</td></tr>
        @empty
            <tr><td colspan="7" class="center">Belum ada soal untuk dianalisis.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="footer">Dicetak oleh Teknoplek CBT pada {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
