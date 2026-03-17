<?php
$j = json_decode(file_get_contents('composer.json'), true);
echo json_encode(array_merge($j['autoload']['psr-4'] ?? [], $j['autoload-dev']['psr-4'] ?? []), JSON_PRETTY_PRINT);
