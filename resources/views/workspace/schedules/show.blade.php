<x-identity-layout :title="'Roster #'.$record->id">
    <p>{{ $record->status === 'draft' ? 'Draf · belum tersedia bagi karyawan' : 'Dipublikasikan' }} · Versi {{ $record->version }} · {{ $shifts->total() }} jadwal</p>
    @if ($record->replacements)<section class="identity-section"><h2>Penggantian jadwal</h2><p>{{ $record->replacement_reason }}</p><p class="history-note">{{ count($record->replacements) }} jadwal lama diganti saat publikasi; riwayat tetap disimpan.</p></section>@endif
    @if ($warnings)
        <section class="identity-section"><h2>Peringatan staffing</h2><ul class="identity-directory">@foreach ($warnings as $warning)<li>{{ $warning['work_date'] }} · cakupan minimum {{ $warning['actual'] }} dari kebutuhan {{ $warning['required'] }} orang<p>{{ $warning['blocking'] ? 'Wajib diselesaikan sebelum publikasi.' : 'Tinjau dan akui peringatan sebelum publikasi.' }}</p></li>@endforeach</ul></section>
    @endif
    <ul class="identity-directory">@foreach ($shifts as $shift)<li><strong>{{ $people[$shift->employee_id] ?? 'Karyawan' }} · {{ $shift->work_date }}</strong><p>{{ $shift->is_day_off ? 'Hari libur' : $shift->starts_at->setTimezone($shift->timezone)->format('d M H:i').' — '.$shift->ends_at->setTimezone($shift->timezone)->format('d M H:i') }} · {{ $shift->timezone }}</p>@if ($shift->status === 'published')<a class="inline-link" href="{{ route('workspace.attendance.show', $shift->id) }}">Buka kehadiran</a>@endif</li>@endforeach</ul>
    {{ $shifts->links() }}
    @if ($record->status === 'draft')
        <section class="identity-section"><h2>Publikasikan jadwal</h2><p>Publikasi memeriksa ulang akses, penempatan, konflik roster dan coverage staffing. Jadwal tidak dipublikasikan jika pemeriksaan gagal.</p>
            <form class="identity-form" method="POST" action="{{ route('workspace.schedules.publish', $record->id) }}">
                @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $record->version }}"><input type="hidden" name="checksum" value="{{ $record->checksum }}">
                @if ($warnings)<label class="roster-checkbox"><input type="checkbox" name="acknowledge_warnings" value="1"><span>Saya sudah meninjau peringatan staffing. Peringatan wajib tetap harus diselesaikan.</span></label>@endif
                <button class="primary-button" type="submit">Publikasikan roster</button>
            </form>
        </section>
    @endif
    <p><a class="inline-link" href="{{ route('workspace.schedules') }}">Kembali ke roster</a> · <a class="inline-link" href="{{ route('workspace.schedules.show', $record->id) }}">Muat ulang rincian</a></p>
</x-identity-layout>
