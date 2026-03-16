<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum MovementType: string
{
    case INITIAL = 'initial';
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case RESTOCK_RETURN = 'restock_return';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case LOSS = 'loss';
    case TRANSFER = 'transfer';
}
