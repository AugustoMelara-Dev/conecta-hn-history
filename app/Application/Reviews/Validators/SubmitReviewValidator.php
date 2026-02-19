<?php

declare(strict_types=1);

namespace App\Application\Validators\Reviews;

use App\Application\Reviews\Commands\SubmitReviewCommand;

/**
 * SubmitReviewValidator - Structural Validation
 */
final class SubmitReviewValidator
{
    public function rules(SubmitReviewCommand $command): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
