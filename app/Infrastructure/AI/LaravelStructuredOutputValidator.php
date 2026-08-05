<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Contracts\StructuredOutputValidator;
use App\Exceptions\Ai\AiMalformedResponseException;
use Illuminate\Support\Facades\Validator;

final class LaravelStructuredOutputValidator implements StructuredOutputValidator
{
    public function validate(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new AiMalformedResponseException('AI provider returned output outside the required schema.');
        }

        return $validator->validated();
    }
}
