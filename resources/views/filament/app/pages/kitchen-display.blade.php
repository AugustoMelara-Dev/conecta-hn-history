<x-filament-panels::page>
    <div class="biz-shell">
        @if (! $board['enabled'])
            <div class="biz-empty-state">
                <h2 class="biz-heading">Cocina no habilitada para este rubro</h2>
                <p class="biz-muted">{{ $board['reason'] }}</p>
            </div>
        @else
            <div class="biz-grid-three">
                @foreach (['confirmed' => 'Confirmados', 'processing' => 'En cocina', 'ready' => 'Listos'] as $status => $label)
                    <div class="biz-card">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">{{ $label }}</p>
                                <h2 class="biz-heading">{{ count($board['columns'][$status] ?? []) }} pedidos</h2>
                            </div>

                            <div class="biz-list">
                                @forelse (($board['columns'][$status] ?? []) as $order)
                                    <div class="biz-stat">
                                        <p class="biz-action-title">#{{ $order['order_number'] }} - {{ $order['customer_name'] ?: 'Cliente' }}</p>
                                        <p class="biz-action-copy">{{ $order['location'] }}</p>
                                        <ul class="biz-action-copy" style="margin-top: .5rem;">
                                            @foreach ($order['items'] as $item)
                                                <li>{{ $item['quantity'] }}x {{ $item['name'] }}@if($item['notes']) - {{ $item['notes'] }}@endif</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @empty
                                    <div class="biz-empty-state">
                                        <p class="biz-muted">Sin pedidos en esta columna.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
