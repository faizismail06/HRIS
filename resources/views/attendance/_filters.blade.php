<form method="get" action="{{ route($schedule ? 'prototype.schedules' : 'prototype.attendance') }}" class="directory-filters attendance-filters">
    @if($schedule)
        <label><span>Minggu kerja</span><select name="week"><option value="2026-10-12" @selected($filters['week'] === '2026-10-12')>12–18 Oktober 2026</option><option value="2026-10-19" @selected($filters['week'] === '2026-10-19')>19–25 Oktober 2026</option></select></label>
    @else
        <label><span>Tanggal kerja</span><input type="date" name="date" value="{{ $filters['date'] }}" min="2026-10-12" max="2026-10-25" required></label>
    @endif
    <label><span>Karyawan</span><select name="employee"><option value="">Semua karyawan contoh</option>@foreach($employees as $employee)<option value="{{ $employee['id'] }}" @selected($filters['employee'] === $employee['id'])>{{ $employee['name'] }}</option>@endforeach</select></label>
    <label><span>Cabang</span><select name="branch"><option value="">Semua cabang</option>@foreach(['Jakarta', 'Bandung', 'Surabaya'] as $branch)<option @selected($filters['branch'] === $branch)>{{ $branch }}</option>@endforeach</select></label>
    @unless($schedule)
        <label><span>Status</span><select name="status"><option value="">Semua status</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
    @endunless
    <button type="submit" class="primary-button">Terapkan</button>
</form>
