<x-identity-layout title="Hak cuti karyawan">
    @include('workspace.leave._nav')
    <form class="identity-form" method="GET" action="{{ route('workspace.leave') }}"><x-core-field name="q" label="Cari nama atau nomor karyawan" :value="$q" maxlength="100" /><button class="secondary-button" type="submit">Cari kredit</button></form>
    <ul class="identity-directory">@forelse ($lots as $lot)<li><a class="inline-link" href="{{ route('workspace.leave.lots.show', $lot->id) }}">{{ $lot->employee_name }} · {{ $lot->employee_number }} · {{ $lot->type_code }} / {{ $lot->entitlement_year }}</a><p>Kredit awal {{ $lot->minutes }} menit · {{ $lot->valid_from }} — {{ Carbon\CarbonImmutable::parse($lot->valid_until)->subDay()->toDateString() }}</p><p class="history-note">Buka rincian untuk saldo setelah reservasi, pemakaian dan koreksi.</p></li>@empty<li>Belum ada kredit yang sesuai pencarian.</li>@endforelse</ul>{{ $lots->links() }}
</x-identity-layout>
