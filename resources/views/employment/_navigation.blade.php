<nav class="section-links attendance-navigation" aria-label="Administrasi karyawan">
    @foreach(array_merge(\App\Support\PrototypeEmployment::definitions(), ['lifecycle' => ['title' => 'Siklus kerja'], 'assets' => ['title' => 'Aset'], 'settings' => ['title' => 'Pengaturan'], 'audit' => ['title' => 'Audit'], 'imports' => ['title' => 'Impor']]) as $key => $section)
        <a href="{{ route('prototype.'.$key, $key === $kind ? $filters : array_intersect_key($filters, array_flip(['branch', 'employee']))) }}" @if($key === $kind) aria-current="page" @endif>{{ $section['title'] }}</a>
    @endforeach
</nav>
