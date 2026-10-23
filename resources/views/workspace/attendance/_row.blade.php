@php
    $shift = $row['shift'];
    $person = $row['person'];
    $result = $row['result'];
    $in = $result->effective_in ? \Carbon\CarbonImmutable::parse($result->effective_in)->setTimezone($shift->timezone) : null;
    $out = $result->effective_out ? \Carbon\CarbonImmutable::parse($result->effective_out)->setTimezone($shift->timezone) : null;
    $date = \Carbon\CarbonImmutable::parse($shift->work_date);
@endphp
<tr>
    <th scope="row">
        <a class="person-cell" href="{{ route('workspace.attendance.show', $shift->id) }}">
            <span class="avatar">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
            <span>{{ $person->name }}<small>{{ $person->employee_number }}</small></span>
        </a>
    </th>
    <td>
        <span class="attendance-cell-main">{{ $date->locale('id')->translatedFormat('d M Y') }}</span>
        <small>{{ $date->locale('id')->translatedFormat('l') }}</small>
    </td>
    <td>
        @if($shift->is_day_off)
            <span class="attendance-muted">Hari libur</span><small>Tidak ada shift</small>
        @else
            <span class="attendance-cell-main">{{ $shift->starts_at->setTimezone($shift->timezone)->format('H:i') }}–{{ $shift->ends_at->setTimezone($shift->timezone)->format('H:i') }}</span>
            <small>
                {{ $shift->timezone }}
                @if($shift->ends_at->setTimezone($shift->timezone)->toDateString() !== $shift->work_date)
                    · +1 hari
                @endif
            </small>
        @endif
    </td>
    @foreach([$in, $out] as $time)
        <td>
            <span class="attendance-time">{{ $time?->format('H:i') ?? '—' }}</span>
            @if($time && $time->toDateString() !== $shift->work_date)
                <small>{{ $time->locale('id')->translatedFormat('d M') }}</small>
            @elseif(!$time && !$shift->is_day_off)
                <small>Belum tercatat</small>
            @endif
        </td>
    @endforeach
    <td>
        <span class="attendance-pill tone-{{ strtolower($result->status) }}"><i aria-hidden="true"></i>{{ $statuses[$result->status] ?? $result->status }}</span>
        @if($result->late_minutes)
            <small class="attendance-warning">Terlambat {{ $result->late_minutes }} mnt</small>
        @endif
        @if($result->early_minutes)
            <small class="attendance-warning">Pulang awal {{ $result->early_minutes }} mnt</small>
        @endif
    </td>
    <td>
        @if($in && $out)
            <span class="attendance-cell-main">{{ intdiv((int) $result->work_minutes, 60) }} j {{ (int) $result->work_minutes % 60 }} mnt</span>
        @elseif($result->status === 'IN_PROGRESS')
            <span class="attendance-muted">Berlangsung</span>
        @else
            <span class="attendance-muted">—</span>
        @endif
    </td>
    <td><a class="attendance-detail-link" href="{{ route('workspace.attendance.show', $shift->id) }}" aria-label="Detail kehadiran {{ $person->name }}, {{ $date->locale('id')->translatedFormat('j F Y') }}">Detail<x-icon name="chevron" size="14"/></a></td>
</tr>
