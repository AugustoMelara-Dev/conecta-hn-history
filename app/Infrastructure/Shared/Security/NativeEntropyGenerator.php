<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Security;

use App\Application\Shared\Contracts\EntropyGeneratorInterface;
use Exception;

class NativeEntropyGenerator implements EntropyGeneratorInterface
{
    /**
     * @throws Exception
     */
    public function generateNumeric(int $length): string
    {
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    /**
     * @throws Exception
     */
    public function generateString(int $length): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}
