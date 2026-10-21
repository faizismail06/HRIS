<x-identity-layout title="Notifikasi">
    <p>Kabar terbaru dari pengajuan dan pekerjaan yang dapat Anda akses.</p>
    <ul class="identity-directory">
        @forelse ($data as $item)
            <li>
                <h2>{{ [
                    'request.submitted' => 'Pengajuan telah dikirim', 'request.action_required' => 'Pengajuan menunggu peninjauan',
                    'request.approved' => 'Pengajuan disetujui', 'request.rejected' => 'Pengajuan ditolak',
                    'request.withdrawn' => 'Pengajuan ditarik', 'request.missing_approver' => 'Peninjau belum tersedia',
                    'request.reminder' => 'Pengingat peninjauan', 'request.escalated' => 'Pengajuan memerlukan perhatian',
                ][$item['event']] ?? 'Pembaruan '.str_replace(['.', '_'], ' ', $item['event']) }}</h2>
                <p>{{ $item['read_at'] ? 'Sudah dibaca' : 'Belum dibaca' }} · {{ Illuminate\Support\Carbon::parse($item['created_at'])->setTimezone(config('app.timezone'))->format('d M Y H:i T') }}</p>
                @if ($item['subject_type'] === 'workflow_request')<p><a class="inline-link" href="{{ route('workspace.requests.show', $item['subject_id']) }}">Buka pengajuan #{{ $item['subject_id'] }}</a></p>
                @else<p class="history-note">Referensi {{ str_replace('_', ' ', $item['subject_type']) }} #{{ $item['subject_id'] }}. Layar rincian modul ini belum terhubung.</p>@endif
                <form class="workspace-actions" method="POST" action="{{ route('workspace.notifications.update', $item['id']) }}">
                    @csrf
                    @if (!$item['read_at'])<button class="secondary-button" name="action" value="read" type="submit">Tandai dibaca</button>@endif
                    <button class="secondary-button" name="action" value="archive" type="submit">Arsipkan</button>
                </form>
            </li>
        @empty<li><h2>Tidak ada notifikasi pada halaman ini</h2><p>Notifikasi muncul ketika ada pembaruan pada pekerjaan Anda. Gunakan halaman berikutnya jika tersedia.</p></li>@endforelse
    </ul>
    @if ($next_before_id)<p><a class="secondary-button" href="{{ route('workspace.notifications', ['before_id' => $next_before_id]) }}">Lihat notifikasi lebih lama</a></p>@endif
</x-identity-layout>
