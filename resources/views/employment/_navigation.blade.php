<nav class="section-links attendance-navigation" aria-label="Administrasi karyawan">
    @foreach(\App\Support\PrototypeEmployment::definitions() as $key => $section)
        <a href="{{ route('prototype.'.$key, $key === $kind ? $filters : array_intersect_key($filters, array_flip(['branch', 'employee']))) }}" @if($key === $kind) aria-current="page" @endif>{{ $section['title'] }}</a>
    @endforeach
</nav>
