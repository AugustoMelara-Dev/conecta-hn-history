<?php

declare(strict_types=1);

namespace App\Domain\Geography\Enums;

enum FulfillmentMode: string
{
    case Storefront = 'storefront';
    case Delivery = 'delivery';
    case OnSite = 'on_site';
    case Remote = 'remote';
    case Pickup = 'pickup';
}
