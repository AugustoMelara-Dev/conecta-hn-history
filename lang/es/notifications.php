<?php

declare(strict_types=1);

/**
 * Notification Messages for WebSocket Broadcasts (Spanish)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  i18n READY: All user-facing broadcast text lives here                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PLACEHOLDER CONVENTION:                                                 ║
 * ║    :amount       → Formatted money amount (e.g., "L 1,500.00")          ║
 * ║    :order        → Order number (e.g., "ORD-2026-00001")                ║
 * ║    :product      → Product name                                         ║
 * ║    :sku          → Product SKU (optional)                               ║
 * ║    :stock        → Current stock level                                  ║
 * ║    :carrier      → Shipping carrier name                                ║
 * ║    :ip           → IP address (anonymized)                              ║
 * ║    :device       → Device/browser description                           ║
 * ║    :time         → Formatted timestamp                                  ║
 * ║                                                                          ║
 * ║  PLURALIZATION:                                                         ║
 * ║    Uses Laravel's {0}|{1}|[2,*] syntax for proper grammar              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Order Payment Notifications
    |--------------------------------------------------------------------------
    */
    'order_paid' => [
        'title' => 'Pago Confirmado',
        'message' => 'Se ha confirmado el pago de :amount para el pedido :order',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Cancellation Notifications
    |--------------------------------------------------------------------------
    */
    'order_cancelled' => [
        'title' => 'Pedido Cancelado',
        'message' => 'El pedido :order ha sido cancelado.',
        'message_with_refund' => 'El pedido :order ha sido cancelado. Se procesará un reembolso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Shipment Notifications
    |--------------------------------------------------------------------------
    */
    'order_shipped' => [
        'title' => 'Pedido Enviado',
        'message' => 'Tu pedido :order ha sido enviado vía :carrier',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Refund Notifications
    |--------------------------------------------------------------------------
    */
    'order_refunded' => [
        'title' => 'Reembolso Procesado',
        'message_full' => 'Se ha procesado un reembolso completo de :amount para el pedido :order',
        'message_partial' => 'Se ha procesado un reembolso parcial de :amount para el pedido :order',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory Alert Notifications (with Pluralization)
    |--------------------------------------------------------------------------
    */
    'low_stock' => [
        'title' => 'Stock Bajo',
        // Pluralization: {0} agotado | {1} 1 unidad | [2,*] N unidades
        'message' => '{0}El producto :product está agotado|{1}El producto :product tiene solo :stock unidad|[2,*]El producto :product tiene solo :stock unidades',
        'message_with_sku' => '{0}El producto :product (SKU: :sku) está agotado|{1}El producto :product (SKU: :sku) tiene solo :stock unidad|[2,*]El producto :product (SKU: :sku) tiene solo :stock unidades',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Notifications
    |--------------------------------------------------------------------------
    */
    'new_login' => [
        'title' => 'Nuevo Inicio de Sesión',
        'message' => 'Se detectó un nuevo inicio de sesión desde :ip a las :time',
        'message_with_device' => 'Se detectó un nuevo inicio de sesión desde :device (:ip) a las :time',
    ],

];
