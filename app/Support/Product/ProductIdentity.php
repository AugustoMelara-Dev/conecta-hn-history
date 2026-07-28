<?php

declare(strict_types=1);

namespace App\Support\Product;

final class ProductIdentity
{
    public static function name(): string
    {
        return (string) config('dovra.identity.name');
    }

    public static function shortName(): string
    {
        return (string) config('dovra.identity.short_name');
    }

    public static function category(): string
    {
        return (string) config('dovra.identity.category');
    }

    public static function commercialPromise(): string
    {
        return (string) config('dovra.identity.commercial_promise');
    }

    public static function originMarket(): string
    {
        return (string) config('dovra.identity.origin_market');
    }

    public static function legalEntityName(): ?string
    {
        $entityName = config('dovra.legal.entity_name');

        return is_string($entityName) && $entityName !== '' ? $entityName : null;
    }

    public static function nameClearanceConfirmed(): bool
    {
        return (bool) config('dovra.legal.name_clearance_confirmed', false);
    }
}
