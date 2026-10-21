<x-identity-layout title="Lembur aktual">
    <p>Otorisasi rencana belum menjadi durasi yang dibayar. Karyawan mengisi waktu aktual, lalu atasan atau HR memverifikasinya.</p>
    @if (auth()->user()->employee_id)<p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.requests.create', ['kind' => 'overtime']) }}">Ajukan otorisasi lembur</a></p>@endif
    <form class="identity-form" method="GET" action="{{ route('workspace.overtime') }}">
        @if ($shiftId)<input type="hidden" name="roster_shift_id" value="{{ $shiftId }}">@endif
        <x-core-field name="status" label="Status lembur" type="select"><option value="">Semua status</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</x-core-field><button class="secondary-button" type="submit">Terapkan filter</button>
    </form>
    <ul class="identity-directory">@forelse ($rows as $row)<li><a class="inline-link" href="{{ route('workspace.overtime.show', $row['record']->id) }}">{{ $row['person']->name }} · {{ $row['shift']->work_date }} · #{{ $row['record']->id }}</a><p>{{ $statuses[$row['record']->status] ?? $row['record']->status }} · hasil verifikasi tersimpan {{ $row['record']->payable_minutes }} menit</p></li>@empty<li><h2>Belum ada lembur yang terlihat</h2><p>Coba status lain. Otorisasi muncul setelah pengajuan lembur dikirim.</p></li>@endforelse</ul>
    @if ($hasMore)<p><a class="secondary-button" href="{{ route('workspace.overtime', ['cursor' => $cursor, 'status' => $status, 'roster_shift_id' => $shiftId]) }}">Lihat lembur berikutnya</a></p>@endif
    @if ($shiftId)<p><a class="inline-link" href="{{ route('workspace.overtime') }}">Lihat semua shift yang dapat diakses</a></p>@endif
</x-identity-layout>
