<x-identity-layout title="Ganti jadwal kerja">
    <p>{{ $person->name }} · {{ $shift->work_date }} · {{ $shift->timezone }}</p>
    <p>Jadwal sekarang: {{ $shift->is_day_off ? 'Hari libur' : $shift->starts_at->setTimezone($shift->timezone)->format('d M H:i').' — '.$shift->ends_at->setTimezone($shift->timezone)->format('d M H:i') }}.</p>
    <p class="history-note">Hanya jadwal mendatang yang belum ditutup dan tidak memiliki kehadiran atau pengajuan terkait yang dapat diganti. Publikasi menyimpan riwayat jadwal lama dan memberi notifikasi kepada karyawan.</p>
    <form class="identity-form" method="POST" action="{{ route('workspace.schedules.replace.store', $shift->id) }}">
        @csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="expected_version" value="{{ $shift->version }}">
        <x-core-field name="is_day_off" label="Jadwal pengganti" type="select" required><option value="">Pilih jadwal</option><option value="0" @selected(old('is_day_off') === '0')>Hari kerja dengan template baru</option><option value="1" @selected(old('is_day_off') === '1')>Hari libur</option></x-core-field>
        <x-core-field name="shift_template_id" label="Template (wajib untuk hari kerja)" type="select"><option value="">Pilih template aktif</option>@foreach ($templates as $template)<option value="{{ $template['id'] }}" @selected((string) old('shift_template_id') === (string) $template['id'])>{{ $template['name'] }} · {{ substr($template['starts_local'], 0, 5) }}–{{ substr($template['ends_local'], 0, 5) }}</option>@endforeach</x-core-field>
        <x-core-field name="replacement_reason" label="Alasan pergantian jadwal" type="textarea" rows="3" maxlength="1000" :value="old('replacement_reason')" required />
        <button class="primary-button" type="submit">Buat draf pengganti</button>
    </form>
    @if ($catalog['last_page'] > 1)<p><a class="inline-link" href="{{ route('workspace.schedules.replace', ['id' => $shift->id, 'page' => $catalog['current_page'] < $catalog['last_page'] ? $catalog['current_page'] + 1 : 1]) }}">Pilihan template berikutnya (isian belum disimpan)</a></p>@endif
    <p><a class="inline-link" href="{{ route('workspace.attendance.show', $shift->id) }}">Kembali ke kehadiran</a></p>
</x-identity-layout>
