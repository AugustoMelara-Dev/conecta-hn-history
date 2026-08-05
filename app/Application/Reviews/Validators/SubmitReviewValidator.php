<?php

declare(strict_types=1);

namespace App\Application\Reviews\Validators;

use App\Application\Reviews\Commands\SubmitReviewCommand;

/**
 * SubmitReviewValidator - Structural Validation
 */
class SubmitReviewValidator
{
    public function rules(SubmitReviewCommand $command): array
    {
        return [
            'tenantId' => ['required', 'uuid', 'exists:tenants,id'],
            'userId' => ['required', 'uuid', 'exists:users,id'],
            'productId' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
