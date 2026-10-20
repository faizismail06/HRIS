@if($settlements)
    <ol class="history-list">@foreach($settlements as $settlement)<li><span class="history-dot" aria-hidden="true"></span><div><h3>{{ $settlement['id'] }} · {{ $settlement['state'] === 'CLEARED' ? 'Berhasil tercatat' : 'Gagal · tidak dihitung terbayar' }}</h3><p>{{ \App\Support\PrototypeFinance::idr($settlement['amount']) }} · {{ \App\Support\PrototypePeople::date($settlement['date']) }}</p><p>Referensi: {{ $settlement['reference'] }} · {{ $settlement['actor'] }}</p></div></li>@endforeach</ol>
@else<p class="history-note">Belum ada settlement pembayaran tercatat.</p>@endif
