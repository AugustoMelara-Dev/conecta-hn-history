<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exceptions;

use DomainException;

/**
 * FeatureRequiresProException - Domain Exception for PRO-Only Features
 *
 * Thrown when a tenant attempts to use a feature requiring PRO subscription.
 */
final class FeatureRequiresProException extends DomainException
{
    public function __construct(string $feature = 'This feature')
    {
        parent::__construct("{$feature} requires a PRO subscription.");
    }

    /**
     * Create for landing page feature.
     */
    public static function forLandingPage(): self
    {
        return new self('Landing page customization');
    }
}
