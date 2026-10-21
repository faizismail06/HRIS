<x-identity-layout title="Periode kehadiran">
    <p>Tinjau kehadiran per cabang sebelum menyimpan snapshot periode tertutup.</p>
    <ul class="identity-directory">@forelse ($periods as $period)<li><a class="inline-link" href="{{ route('workspace.periods.show', $period->id) }}">{{ $period->branch_name }} · {{ $period->starts_on }} — {{ Carbon\CarbonImmutable::parse($period->ends_on)->subDay()->toDateString() }}</a><p>{{ ['open' => 'Terbuka', 'review' => 'Dalam tinjauan', 'closed' => 'Ditutup'][$period->status] ?? $period->status }} · revisi {{ $period->revision }}{{ $period->payroll_finalized ? ' · Payroll final' : '' }}</p></li>@empty<li>Belum ada periode. Buat periode pertama di bawah ini.</li>@endforelse</ul>
    {{ $periods->links() }}
    <section class="identity-section"><h2>Buat periode</h2><p class="history-note">Tanggal terakhir termasuk dalam periode. Maksimal 62 hari; periode pada cabang yang sama tidak boleh bertumpuk.</p>
        <form class="identity-form" method="POST" action="{{ route('workspace.periods.store') }}">
            @csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
            <x-core-field name="branch_id" label="Cabang" type="select" required><option value="">Pilih cabang</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</x-core-field>
            <x-core-field name="starts_on" label="Tanggal kerja pertama" type="date" :value="old('starts_on')" required />
            <x-core-field name="last_work_date" label="Tanggal kerja terakhir (termasuk)" type="date" :value="old('last_work_date')" required />
            <button class="primary-button" type="submit">Buat periode terbuka</button>
        </form>
    </section>
    <p><a class="inline-link" href="{{ route('workspace.attendance', ['view' => 'team']) }}">Buka kehadiran tim</a></p>
</x-identity-layout>
