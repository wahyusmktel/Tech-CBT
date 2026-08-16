<div class="letterhead">
    @if ($letterhead)
        <img src="{{ $letterhead }}" alt="Kop surat">
    @else
        <p class="school-name">{{ strtoupper($school->name) }}</p>
        <p class="school-address">{{ $school->address }} | NPSN {{ $school->npsn }} | {{ $school->email }}</p>
    @endif
</div>
