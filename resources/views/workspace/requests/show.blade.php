<x-identity-layout :title="($kinds[$record->kind] ?? ucwords(str_replace('_', ' ', $record->kind))).' #'.$record->id">
    <p>{{ $statuses[$record->status] ?? $record->status }} · Versi {{ $record->version }}</p>
    @if ($overtimeId)<p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.overtime.show', $overtimeId) }}">Pantau dan isi aktual lembur</a></p>@endif
    @if ($record->status === 'blocked')<p class="identity-notice">Peninjau belum tersedia. Hubungi HR untuk penetapan peninjau berwenang; pengajuan belum disetujui.</p>@endif
    @if (!$supported)<p class="identity-notice">Status pengajuan tersedia. Rincian dan tindakan jenis ini akan tersedia saat integrasi layar modulnya selesai.</p>@endif
    @php
        $labels = ['starts_on' => 'Tanggal mulai', 'ends_on' => 'Tanggal terakhir', 'minutes' => 'Durasi (menit)', 'work_date' => 'Tanggal kerja', 'planned_start' => 'Mulai lembur', 'planned_end' => 'Selesai lembur', 'task' => 'Pekerjaan', 'original_request_id' => 'Pengajuan cuti asal', 'effective_in' => 'Usulan jam masuk', 'effective_out' => 'Usulan jam keluar', 'reason' => 'Alasan', 'segment' => 'Bagian shift'];
    @endphp
    <dl class="identity-facts">
        @if ($employeeLabel)<div><dt>Karyawan</dt><dd>{{ $employeeLabel }}</dd></div>@endif
        @if ($leaveType)<div><dt>Jenis cuti</dt><dd>{{ $leaveType }}</dd></div>@endif
        @foreach ($labels as $field => $label)
            @php($value = $supported ? ($details[$field] ?? $record->summary[$field] ?? null) : null)
            @if ($value !== null && is_scalar($value))<div><dt>{{ $label }}</dt><dd>{{ $field === 'segment' ? (['full' => 'Sehari penuh', 'first_half' => 'Paruh pertama', 'second_half' => 'Paruh kedua'][$value] ?? $value) : $value }}</dd></div>@endif
        @endforeach
    </dl>
    @if ($steps->isNotEmpty())
        <section class="identity-section"><h2>Alur persetujuan</h2>
            <ol class="workflow-steps">@foreach ($steps as $step)<li><strong>{{ ['manager' => 'Atasan', 'hr_admin' => 'HR Admin', 'hr_officer' => 'HR Officer', 'finance_approver' => 'Finance', 'counterparty' => 'Rekan penukar'][$step->role] ?? str_replace('_', ' ', $step->role) }}</strong> · {{ ['waiting' => 'Menunggu tahap sebelumnya', 'pending' => 'Menunggu keputusan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'blocked' => 'Peninjau belum tersedia', 'cancelled' => 'Dibatalkan'][$step->status] ?? $step->status }}@if ($step->due_at)<p class="history-note">Batas peninjauan {{ $step->due_at->setTimezone(config('app.timezone'))->format('d M Y H:i T') }}</p>@endif</li>@endforeach</ol>
        </section>
    @endif
    @if ($supported)
        <section class="identity-section"><h2>Bukti pendukung</h2>
            <p class="history-note">Bukti medis hanya tersedia bagi pemilik dan HR yang berwenang.</p>
            <ul class="identity-directory">@forelse ($attachments as $file)<li><a class="inline-link" href="{{ route('workspace.api.attachments.download', $file->id) }}">Unduh bukti #{{ $file->id }} ({{ $file->mime }}, {{ number_format($file->size / 1024, 1) }} KB)</a></li>@empty<li>Tidak ada bukti yang dapat Anda akses.</li>@endforelse</ul>
            @if ($owned && in_array($record->status, ['draft', 'in_review', 'blocked']))
                <form class="identity-form" method="POST" enctype="multipart/form-data" action="{{ route('workspace.requests.upload', $record->id) }}">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <x-core-field name="file" label="Unggah bukti" type="file" accept="image/jpeg,image/png,application/pdf" help="JPG, PNG atau PDF, maksimal 5 MB." required />
                    <button class="secondary-button" type="submit">Unggah bukti</button>
                </form>
            @endif
        </section>
        @if ($owned && $record->status === 'draft')
            <form class="workspace-actions" method="POST" action="{{ route('workspace.requests.action', [$record->id, 'submit']) }}">
                @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $record->version }}">
                <button class="primary-button" type="submit">Kirim untuk ditinjau</button>
            </form>
        @endif
        @if ($canDecide)
            <section class="identity-section"><h2>Keputusan Anda</h2>
                <form class="identity-form" method="POST" action="{{ route('workspace.requests.action', [$record->id, 'decide']) }}">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $record->version }}">
                    <x-core-field name="decision" label="Keputusan" type="select" required><option value="">Pilih keputusan</option><option value="approve" @selected(old('decision') === 'approve')>Setujui</option><option value="reject" @selected(old('decision') === 'reject')>Tolak</option></x-core-field>
                    <x-core-field name="reason" label="Catatan keputusan" type="textarea" rows="3" maxlength="1000" help="Wajib diisi jika menolak pengajuan." :value="old('reason')" />
                    <button class="primary-button" type="submit">Simpan keputusan</button>
                </form>
            </section>
        @endif
        @if ($owned && in_array($record->status, ['draft', 'in_review', 'blocked']))
            <details class="identity-recovery"><summary>Tarik pengajuan</summary>
                <form class="identity-form" method="POST" action="{{ route('workspace.requests.action', [$record->id, 'withdraw']) }}">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $record->version }}">
                    <x-core-field name="withdrawal_reason" label="Alasan penarikan" type="textarea" rows="3" maxlength="1000" :value="old('withdrawal_reason')" required />
                    <button class="secondary-button" type="submit">Tarik pengajuan ini</button>
                </form>
            </details>
        @endif
    @endif
    <p><a class="inline-link" href="{{ route('workspace.requests') }}">Kembali ke pengajuan</a> · <a class="inline-link" href="{{ route('workspace.requests.show', $record->id) }}">Muat status terbaru</a></p>
</x-identity-layout>
