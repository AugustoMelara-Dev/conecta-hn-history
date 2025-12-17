{{-- Kanban Card for Orders --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-3 hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between mb-2">
        <span class="text-xs font-bold text-gray-500">{{ $order->order_number ?? '#'.$order->id }}</span>
        <span class="text-xs text-gray-400">{{ $order->created_at->format('h:i A') }}</span>
    </div>
    
    @if($order->customer_name)
        <p class="font-medium text-gray-900 text-sm mb-1">{{ $order->customer_name }}</p>
    @endif
    
    {{-- Items Summary --}}
    <div class="text-xs text-gray-500 mb-2">
        @foreach($order->items->take(2) as $item)
            <p>{{ $item->quantity }}x {{ Str::limit($item->product_name, 20) }}</p>
        @endforeach
        @if($order->items->count() > 2)
            <p class="text-gray-400">+{{ $order->items->count() - 2 }} más...</p>
        @endif
    </div>
    
    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
        <span class="font-bold text-gray-900">L. {{ number_format($order->total, 2) }}</span>
        
        @if($nextAction)
            <button
                wire:click="advanceOrder({{ $order->id }})"
                class="px-2 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100"
            >
                Avanzar →
            </button>
        @endif
    </div>
</div>
