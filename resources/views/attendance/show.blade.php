<x-app-layout title="Detail shift" active="attendance">
    <a class="back-link" href="{{ route($backRoute, $backFilters) }}"><x-icon name="arrow" size="16"/>{{ $backLabel }}</a>
    <div class="page-heading"><div><h1>{{ $record['person']['name'] }}</h1><p>Hari kerja {{ $record['date_label'] }} · {{ $record['person']['branch'] }}</p></div><span class="employment-state attendance-state-{{ strtolower($record['status']) }}">{{ $record['status_label'] }}</span></div>
    <p class="profile-notice"><x-icon name="info" size="17"/>SIMULATED · Seluruh waktu, lokasi dan hasil di halaman ini adalah contoh. Tidak ada pembacaan GPS, kamera atau keputusan kehadiran nyata.</p>
    <div class="profile-grid">
        <section class="profile-section" aria-labelledby="shift-title"><h2 id="shift-title">Penugasan shift</h2><dl class="profile-fields">
            <div><dt>Referensi</dt><dd class="break-text">{{ $record['id'] }}</dd></div><div><dt>Zona waktu</dt><dd>Asia/Jakarta · WIB</dd></div>
            <div><dt>Jadwal mulai</dt><dd>{{ \App\Support\PrototypeAttendance::time($record['start']) }}</dd></div><div><dt>Jadwal selesai</dt><dd>{{ \App\Support\PrototypeAttendance::time($record['end']) }}@if($record['overnight'])<small class="cell-meta">Hari berikutnya</small>@endif</dd></div>
            <div><dt>Clock-in contoh</dt><dd>{{ \App\Support\PrototypeAttendance::time($record['in']) }}</dd></div><div><dt>Clock-out contoh</dt><dd>{{ \App\Support\PrototypeAttendance::time($record['out']) }}</dd></div>
            <div><dt>Istirahat tidak dibayar</dt><dd>{{ $record['off'] ? 'Tidak ada shift' : $record['break_start']->format('H.i').'–'.$record['break_end']->format('H.i').' (60 mnt)' }}</dd></div><div><dt>Durasi kerja contoh</dt><dd>@if($record['minutes'] !== null){{ intdiv($record['minutes'], 60) }} jam {{ $record['minutes'] % 60 }} menit @else Belum dapat ditetapkan @endif</dd></div>
            <div><dt>Terlambat</dt><dd>{{ $record['in'] ? $record['late'].' menit' : 'Tidak dinilai' }}</dd></div><div><dt>Pulang awal</dt><dd>{{ $record['out'] ? $record['early'].' menit' : 'Tidak dinilai' }}</dd></div>
        </dl><p class="directory-footnote">Durasi hanya ditampilkan saat interval lengkap, dikurangi istirahat terjadwal contoh. Kehadiran lebih lama tidak otomatis menjadi lembur terbayar.</p></section>
        <section class="profile-section" aria-labelledby="events-title"><h2 id="events-title">Jejak peristiwa contoh</h2>
            @if(count($record['events']))
                <ol class="history-list">@foreach($record['events'] as $event)<li><span class="history-dot {{ $event['accepted'] ? '' : 'event-rejected' }}"></span><div><time datetime="{{ $event['time']->toIso8601String() }}">{{ \App\Support\PrototypeAttendance::time($event['time']) }} WIB</time><h3>{{ $event['title'] }}</h3><p>{{ $event['note'] }}</p></div></li>@endforeach</ol>
            @else
                <p class="team-empty">Tidak ada peristiwa clock-in/out dalam skenario ini.</p>
            @endif
            <p class="history-note">Sumber: SIMULATED · kebijakan contoh ATT-DEMO-V1 · roster RST-OCT-V1. Tidak ada bukti atau unduhan dokumen nyata.</p>
        </section>
    </div>
    <section class="profile-section team-section" aria-labelledby="followup-title"><h2 id="followup-title">Arti status dan tindak lanjut</h2>
        <div class="attendance-explanation">
            @switch($record['status'])
                @case('INCOMPLETE')<p>Jam pulang belum tercatat setelah shift berakhir. Status belum lengkap tidak dianggap sebagai pulang tepat waktu atau tidak hadir. Karyawan perlu mengajukan koreksi dengan alasan dan bukti.</p>@break
                @case('ABSENT')<p>Dalam skenario ini shift dan tenggat telah lewat tanpa bukti kehadiran yang diterima, cuti atau pengecualian. Percobaan lokasi yang ditolak tidak otomatis menjadi hadir.</p>@break
                @case('LEAVE')<p>Contoh cuti telah disetujui untuk hari kerja ini. Alasan pribadi dan lampiran tidak ditampilkan. Cuti tidak otomatis menonaktifkan akun.</p>@break
                @case('DAY_OFF')<p>Hari ini libur menurut roster karyawan. Tidak ada shift yang perlu dicatat; hari libur bukan tidak hadir dan tidak mengurangi cuti.</p>@break
                @case('SCHEDULED')<p>Shift belum dimulai pada snapshot 20 Oktober 2026 pukul 08.45 WIB. Tidak ada penilaian tidak hadir untuk jadwal yang belum berjalan.</p>@break
                @case('IN_PROGRESS')<p>Clock-in contoh sudah diterima dan shift masih berlangsung pada snapshot. Clock-out yang belum ada selama shift berjalan bukan status belum lengkap.</p>@break
                @default<p>Interval kehadiran contoh lengkap. Terlambat dan pulang awal tetap dicatat sebagai atribut independen, tanpa menghilangkan status hadir.</p>
            @endswitch
            @if(in_array($record['status'], ['INCOMPLETE', 'ABSENT', 'PRESENT']))<details class="correction-guidance"><summary>Panduan koreksi contoh <x-icon name="chevron" size="16"/></summary><p>Default PRD adalah pengajuan paling lambat tujuh hari dari shift dan sebelum penutupan periode. Batas contoh shift ini: {{ $record['correction_until'] }}. Raw event tetap disimpan; koreksi pasca-close memakai penyesuaian. Pengajuan, lampiran dan persetujuan belum tersedia di prototipe ini.</p></details>@endif
        </div>
    </section>
</x-app-layout>
