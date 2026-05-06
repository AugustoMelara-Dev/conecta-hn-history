<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

use App\Domain\Shared\ValueObjects\TenantId;
use App\Exports\SalesExport;

echo "Testing SalesExport Reflection...\n";

$uuid = '550e8400-e29b-41d4-a716-446655440000';
$t = new TenantId($uuid);
echo 'TenantId created: '.$t->toString()."\n";

try {
    $ref = new ReflectionClass(SalesExport::class);
    $ctor = $ref->getConstructor();
    if (! $ctor) {
        echo "No Constructor!\n";
        exit(1);
    }
    $params = $ctor->getParameters();
    if (empty($params)) {
        echo "No Params!\n";
        exit(1);
    }

    $param0 = $params[0];
    echo 'Param 0 Name: '.$param0->getName()."\n";
    echo 'Param 0 Type: '.$param0->getType()->getName()."\n";

    if ($param0->getType()->getName() === TenantId::class) {
        echo 'MATCH! Type matches '.TenantId::class."\n";
    } else {
        echo 'MISMATCH! Expected '.TenantId::class.' but got '.$param0->getType()->getName()."\n";
    }
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo $e->getTraceAsString();
}

use App\Exceptions\InvalidBusinessRuleException;
use App\Exports\Criteria\SalesExportCriteria;

echo "\nTesting Criteria Exception:\n";
try {
    SalesExportCriteria::create('2025-12-31', '2025-01-01');
} catch (InvalidBusinessRuleException $e) {
    echo "Caught Exception: '".$e->getMessage()."'\n";
    echo 'Hex: '.bin2hex($e->getMessage())."\n";
} catch (Throwable $e) {
    echo 'Caught Other: '.get_class($e).' '.$e->getMessage()."\n";
}
