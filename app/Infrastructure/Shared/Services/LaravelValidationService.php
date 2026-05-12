<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Shared\Contracts\ValidationServiceInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class LaravelValidationService implements ValidationServiceInterface
{
    public function validate(array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
