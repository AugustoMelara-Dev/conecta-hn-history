<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Illuminate\Contracts\Validation\Validator;

interface ValidatorFactoryInterface
{
    public function make(array $data, array $rules): Validator;
}
