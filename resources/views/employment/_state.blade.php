@php($stateClass = match($item['status']) { 'ACTIVE', 'EFFECTIVE', 'ISSUED' => 'state-active', 'SCHEDULED' => 'state-preboarding', 'IN_REVIEW' => 'request-state-in_review', default => 'state-ended' })
<span class="employment-state {{ $stateClass }}">{{ $item['status_label'] }}</span>
