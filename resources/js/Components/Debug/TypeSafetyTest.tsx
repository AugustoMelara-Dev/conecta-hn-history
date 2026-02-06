import React from 'react';

// NOTA: Usamos los tipos globales generados en resources/js/types/generated.d.ts
// No importamos 'OrderStatus' porque es un type definition, no un valor en runtime.

export default function TypeSafetyTest({ order }: { order: App.Application.Sales.Results.CreateOrderResult }) {

    return (
        <div className="p-6 bg-gray-50 border border-gray-200 rounded-lg">
            <h2 className="text-xl font-bold mb-4">Verificación de Tipos Estrictos</h2>

            {/* PRUEBA 1: Acceso a Propiedad Primitiva */}
            <div className="mb-2">
                <strong>Orden ID:</strong> {order.orderNumber} {/* Debe autocompletar orderNumber */}
            </div>

            {/* PRUEBA 2: Validación de Enum (Simulada con string estricto, ya que en TS es string) */}
            <div className="mb-2">
                <strong>Estado:</strong>
                {order.status === 'confirmed' ? (
                    <span className="text-green-600 font-bold ml-2">CONFIRMADO</span>
                ) : (
                    <span className="text-yellow-600 ml-2">{order.status}</span>
                )}
            </div>

            {/* PRUEBA 3: Iteración de Array Tipado (Nested DTO) */}
            <div className="mt-4">
                <h3 className="font-semibold">Items (Tipado Recursivo):</h3>
                <ul className="list-disc pl-5">
                    {Object.values(order.items ?? {}).map((item) => (
                        <li key={item.productId}>
                            {/* Aquí 'item' debe ser reconocido como OrderItemDto */}
                            {/* Escribir 'item.' debe sugerir 'productName', 'quantity', etc. */}
                            {item.productName} — Cantidad: {item.quantity} — Total: ${item.total}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
