<?php

declare(strict_types=1);

/**
 * Notification Messages for WebSocket Broadcasts (English - Fallback)
 */

return [

    'order_paid' => [
        'title' => 'Payment Confirmed',
        'message' => 'Payment of :amount for order :order has been confirmed',
    ],

    'order_cancelled' => [
        'title' => 'Order Cancelled',
        'message' => 'Order :order has been cancelled.',
        'message_with_refund' => 'Order :order has been cancelled. A refund will be processed.',
    ],

    'order_shipped' => [
        'title' => 'Order Shipped',
        'message' => 'Your order :order has been shipped via :carrier',
    ],

    'order_refunded' => [
        'title' => 'Refund Processed',
        'message_full' => 'A full refund of :amount has been processed for order :order',
        'message_partial' => 'A partial refund of :amount has been processed for order :order',
    ],

    'low_stock' => [
        'title' => 'Low Stock',
        // Pluralization: {0} out of stock | {1} 1 unit | [2,*] N units
        'message' => '{0}Product :product is out of stock|{1}Product :product has only :stock unit left|[2,*]Product :product has only :stock units left',
        'message_with_sku' => '{0}Product :product (SKU: :sku) is out of stock|{1}Product :product (SKU: :sku) has only :stock unit left|[2,*]Product :product (SKU: :sku) has only :stock units left',
    ],

    'new_login' => [
        'title' => 'New Login Detected',
        'message' => 'A new login was detected from :ip at :time',
        'message_with_device' => 'A new login was detected from :device (:ip) at :time',
    ],

];
