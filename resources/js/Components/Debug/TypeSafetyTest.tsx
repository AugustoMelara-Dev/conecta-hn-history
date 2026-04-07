import React from 'react';

type DebugOrderItem = {
    productId: string | number;
    productName: string;
    quantity: number;
    total: number | string;
};

type DebugOrder = {
    orderNumber: string;
    status: string;
    items?: DebugOrderItem[] | Record<string, DebugOrderItem>;
};

export default function TypeSafetyTest({ order }: { order: DebugOrder }) {
    const items = Array.isArray(order.items) ? order.items : Object.values(order.items ?? {});

    return (
        <div className="p-6 bg-gray-50 border border-gray-200 rounded-lg">
            <h2 className="text-xl font-bold mb-4">Verificacion de Tipos Estrictos</h2>

            <div className="mb-2">
                <strong>Orden ID:</strong> {order.orderNumber}
            </div>

            <div className="mb-2">
                <strong>Estado:</strong>
                {order.status === 'confirmed' ? (
                    <span className="text-green-600 font-bold ml-2">CONFIRMADO</span>
                ) : (
                    <span className="text-yellow-600 ml-2">{order.status}</span>
                )}
            </div>

            <div className="mt-4">
                <h3 className="font-semibold">Items (Tipado Recursivo):</h3>
                <ul className="list-disc pl-5">
                    {items.map((item) => (
                        <li key={item.productId}>
                            {item.productName} - Cantidad: {item.quantity} - Total: ${item.total}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}