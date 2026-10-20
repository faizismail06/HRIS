<x-identity-layout :title="'Pengajuan perubahan · '.$change->employee->name">
    <p>{{ $change->kind === 'identity' ? 'Identitas kerja' : 'Penempatan' }} · {{ match ($change->status) { 'pending' => 'Menunggu tinjauan', 'approved' => 'Disetujui', default => 'Ditolak' } }}</p>
    <dl class="identity-facts"><div><dt>Pengaju</dt><dd>{{ $change->requester->name }}</dd></div><div><dt>Alasan</dt><dd>{{ $change->reason }}</dd></div><div><dt>Mulai berlaku</dt><dd>{{ $change->effective_on?->format('d M Y') ?? 'Setelah disetujui' }}</dd></div></dl>
    <h2>Perubahan yang diajukan</h2>
    <p class="history-note">Nilai sebelum perubahan disimpan saat pengajuan dibuat. Nama cabang, departemen, jabatan dan atasan mengikuti register saat dibuka. Jika versi karyawan berubah, persetujuan ditolak dan pengajuan perlu dibuat ulang.</p>
    <dl class="identity-facts">@foreach ($comparison as $row)<div><dt>{{ $row['label'] }}</dt><dd>{{ $row['before'] }} → {{ $row['after'] }}</dd></div>@endforeach</dl>
    @if ($change->status === 'pending')
        @if (App\Domain\Identity\Access::companyPermission(auth()->user(), 'employees.review') && auth()->id() !== $change->requested_by && auth()->user()->employee_id !== $change->employee_id)
            <form class="identity-form" method="POST" action="{{ route('workspace.changes.review', $change->id) }}">
                @csrf
                <input type="hidden" name="version" value="{{ $change->version }}">
                <x-core-field name="review_note" label="Catatan tinjauan" type="textarea" rows="3" maxlength="1000" :value="old('review_note')" required />
                <x-core-field name="decision" label="Keputusan" type="select" required><option value="">Pilih keputusan</option><option value="approve" @selected(old('decision') === 'approve')>Setujui perubahan</option><option value="reject" @selected(old('decision') === 'reject')>Tolak pengajuan</option></x-core-field>
                <button class="primary-button" type="submit">Simpan keputusan</button>
            </form>
        @else
            <p class="identity-notice">Menunggu HR Admin lain yang bukan pengaju atau subjek perubahan.</p>
        @endif
    @else
        <p>Ditinjau {{ $change->reviewer?->name }} · {{ $change->reviewed_at?->format('d M Y H:i') }}</p><p>{{ $change->review_note }}</p>
    @endif
    <p><a class="inline-link" href="{{ route('workspace.changes') }}">Kembali ke pengajuan</a> · <a class="inline-link" href="{{ route('workspace.employees.show', $change->employee_id) }}">Buka profil karyawan</a></p>
</x-identity-layout>
