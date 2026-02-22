<?php
require 'vendor/autoload.php';

try {
    $test = new Tests\Unit\Services\OrderStateServiceTest('it_can_reset_event_bus_between_tests');
    $result = $test->run();
    echo "Setup Successful\n";
    echo "Setup Successful\n";
} catch (\Throwable $e) {
    echo "FATAL ERROR CAUGHT:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
