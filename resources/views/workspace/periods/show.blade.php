<x-identity-layout :title="'Periode kehadiran #'.$period->id">
    <p>{{ $branch->name }} · {{ $period->starts_on }} — {{ Carbon\CarbonImmutable::parse($period->ends_on)->subDay()->toDateString() }} · {{ $branch->timezone }}</p>
    <p>{{ ['open' => 'Terbuka', 'review' => 'Dalam tinjauan', 'closed' => 'Ditutup'][$period->status] ?? $period->status }} · Versi {{ $period->version }} · Revisi {{ $period->revision }}</p>
    @if ($review)
        <section class="identity-section"><h2>Hasil tinjauan tersimpan</h2><p>{{ $review['rows'] }} jadwal diperiksa · {{ count($review['blockers']) }} penghambat.</p><p class="history-note">Hasil ini berasal dari tinjauan terakhir. Penutupan memeriksa sumber kembali; tinjau ulang setelah memperbaiki data.</p>
            <ul class="identity-directory">@forelse ($blockers as $blocker)<li><strong>{{ [
                'period_not_ended' => 'Periode belum berakhir', 'attendance_unresolved' => 'Kehadiran belum selesai atau bertumpuk dengan cuti',
                'leave_pending' => 'Cuti masih menunggu keputusan', 'correction_pending' => 'Koreksi masih menunggu keputusan',
                'overtime_unverified_or_stale' => 'Lembur belum diverifikasi atau sumbernya berubah', 'shift_outside_active_employment' => 'Shift di luar masa kerja aktif',
                'schedule_gap' => 'Jadwal karyawan belum lengkap',
            ][$blocker['code']] ?? $blocker['code'] }}</strong>
                @if (isset($blocker['work_date']))<p>{{ $blocker['work_date'] }} · {{ $blockerPeople[$blocker['employee_id']]->name ?? 'Karyawan #'.$blocker['employee_id'] }}</p>@endif
                @if ($blocker['code'] === 'schedule_gap' && isset($blockerPeople[$blocker['employee_id']]))<a class="inline-link" href="{{ route('workspace.schedules.create', ['q' => $blockerPeople[$blocker['employee_id']]->employee_number, 'as_of' => $blocker['work_date']]) }}">Lengkapi roster karyawan ini</a>@endif
                @if (isset($blocker['roster_shift_id']))<a class="inline-link" href="{{ route('workspace.attendance.show', $blocker['roster_shift_id']) }}">Periksa kehadiran #{{ $blocker['roster_shift_id'] }}</a>@endif
                @if (isset($blocker['overtime_id']))<a class="inline-link" href="{{ route('workspace.overtime.show', $blocker['overtime_id']) }}">Periksa lembur #{{ $blocker['overtime_id'] }}</a>@endif
            </li>@empty<li>Tidak ada penghambat pada tinjauan ini.</li>@endforelse</ul>
            {{ $blockers->links() }}
        </section>
    @endif
    @if (in_array($period->status, ['open', 'review']))
        <form class="workspace-actions" method="POST" action="{{ route('workspace.periods.action', [$period->id, 'review']) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $period->version }}"><button class="secondary-button" type="submit">{{ $period->status === 'review' ? 'Tinjau ulang sumber' : 'Jalankan tinjauan' }}</button></form>
        @if ($review && empty($review['blockers']))
            <form class="workspace-actions" method="POST" action="{{ route('workspace.periods.action', [$period->id, 'close']) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $period->version }}"><input type="hidden" name="checksum" value="{{ $period->review_checksum }}"><button class="primary-button" type="submit">Tutup periode dan simpan snapshot</button></form>
        @endif
    @endif
    @if ($period->revision > 0)
        <section class="identity-section"><h2>Snapshot revisi {{ $revision }}</h2>
            <form class="identity-form" method="GET" action="{{ route('workspace.periods.show', $period->id) }}"><x-core-field name="revision" label="Nomor revisi tersimpan" type="number" min="1" :max="$period->revision" :value="$revision" required /><button class="secondary-button" type="submit">Lihat revisi</button></form>
            <p class="history-note">Data berikut dibekukan pada penutupan revisi yang dipilih, termasuk setelah periode dibuka kembali.</p>
            <ul class="identity-directory">@forelse ($snapshots as $snapshot)<li><strong>{{ $snapshot['data']['employee_name'] }} · {{ $snapshot['data']['work_date'] }}</strong><p>{{ App\Http\Controllers\AttendanceWorkspaceController::STATUSES[$snapshot['data']['result']['status']] ?? $snapshot['data']['result']['status'] }} · {{ $snapshot['data']['result']['work_minutes'] }} menit kerja · {{ array_sum(array_column($snapshot['data']['overtime'], 'payable_minutes')) }} menit lembur</p><p class="history-note">Snapshot #{{ $snapshot['id'] }} · SHA-256 {{ $snapshot['sha256'] }}</p></li>@empty<li>Tidak ada snapshot jadwal pada revisi ini.</li>@endforelse</ul>
            {{ $snapshots->links() }}
        </section>
    @endif
    @if ($period->status === 'closed' && !$period->payroll_finalized)
        <details class="identity-recovery"><summary>Buka kembali periode untuk ditinjau ulang</summary><p>Snapshot sebelumnya dipertahankan. Penutupan berikutnya membuat revisi baru.</p>
            <form class="identity-form" method="POST" action="{{ route('workspace.periods.action', [$period->id, 'reopen']) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $period->version }}"><x-core-field name="reason" label="Alasan membuka kembali" type="textarea" rows="3" maxlength="1000" :value="old('reason')" required /><button class="secondary-button" type="submit">Buka kembali periode ini</button></form>
        </details>
    @elseif ($period->payroll_finalized)<p class="identity-notice">Payroll sudah final. Gunakan penyesuaian terpisah; periode tidak dapat dibuka kembali.</p>@endif
    <p><a class="inline-link" href="{{ route('workspace.periods') }}">Kembali ke periode</a> · <a class="inline-link" href="{{ route('workspace.periods.show', $period->id) }}">Muat status terbaru</a></p>
</x-identity-layout>
