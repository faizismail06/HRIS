<x-identity-layout :title="($record->exists ? 'Ubah ' : 'Tambah ').strtolower($type['label'])">
    <form class="identity-form" method="POST" action="{{ $record->exists ? route('workspace.organization.update', [$kind, $record->id]) : route('workspace.organization.store', $kind) }}">
        @csrf
        @if ($record->exists) @method('PUT') <input type="hidden" name="version" value="{{ $record->version }}"> @endif
        <x-core-field name="code" label="Kode" :value="old('code', $record->code)" maxlength="40" pattern="[A-Z0-9][A-Z0-9_-]*" required help="Huruf besar, angka, garis bawah atau tanda hubung. Kode harus unik." />
        <x-core-field name="name" label="Nama" :value="old('name', $record->name)" maxlength="160" required />
        @if ($kind === 'branches')
            <x-core-field name="timezone" label="Zona waktu" type="select" required>@foreach (DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected(old('timezone', $record->timezone ?? 'Asia/Jakarta') === $zone)>{{ $zone }}</option>@endforeach</x-core-field>
        @else
            <x-core-field :name="$kind === 'jobs' ? 'department_id' : 'parent_id'" :label="$kind === 'jobs' ? 'Departemen' : 'Departemen induk'" type="select" :required="$kind === 'jobs'">
                    <option value="">{{ $kind === 'jobs' ? 'Pilih departemen' : 'Tanpa induk' }}</option>
                    @foreach ($departments as $department)
                        @if ($kind === 'jobs' || $department->id !== $record->id)
                            <option value="{{ $department->id }}" @selected((string) old($kind === 'jobs' ? 'department_id' : 'parent_id', $kind === 'jobs' ? $record->department_id : $record->parent_id) === (string) $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (diarsipkan)' }}</option>
                        @endif
                    @endforeach
            </x-core-field>
        @endif
        <x-core-field name="is_active" label="Status" type="select"><option value="1" @selected((string) old('is_active', $record->exists ? (int) $record->is_active : 1) === '1')>Aktif</option><option value="0" @selected((string) old('is_active', (int) $record->is_active) === '0' && $record->exists)>Diarsipkan</option></x-core-field>
        <button class="primary-button" type="submit">{{ $record->exists ? 'Simpan perubahan' : 'Tambahkan '.strtolower($type['label']) }}</button>
        <a class="inline-link" href="{{ route('workspace.organization', $kind) }}">Batal dan kembali</a>
    </form>
</x-identity-layout>
