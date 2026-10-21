@php
    $value = is_object($value) ? (array) $value : $value;
    $labels = App\Support\OperationPresentation::labels();
@endphp
@if (is_array($value))
    @if (!$value)<p class="history-note">Tidak ada catatan.</p>
    @elseif ($depth < 10)
        @if (array_is_list($value))
            <ol class="operation-results">@foreach (array_slice($value, 0, 500) as $item)<li>@include('workspace.operations.result', ['value' => $item, 'depth' => $depth + 1])</li>@endforeach</ol>
        @else
            <dl class="identity-facts operation-facts">
                @foreach ($value as $key => $item)
                    @if (!App\Support\OperationPresentation::hidden((string) $key))
                        <div><dt>{{ $labels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</dt><dd>
                            @if (App\Support\OperationPresentation::money((string) $key) && is_numeric($item))Rp {{ App\Domain\Payroll\IntegerMoney::format((int) $item) }}
                            @else @include('workspace.operations.result', ['value' => App\Support\OperationPresentation::decode($item), 'depth' => $depth + 1]) @endif
                        </dd></div>
                    @endif
                @endforeach
            </dl>
        @endif
    @else<p>Lihat rincian dari catatan sumber.</p>@endif
@elseif (is_bool($value)){{ $value ? 'Ya' : 'Tidak' }}
@elseif ($value === null)Belum diisi
@else{{ App\Support\OperationPresentation::text((string) $value) }}@endif
