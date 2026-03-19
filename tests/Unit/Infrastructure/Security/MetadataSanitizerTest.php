<?php

declare(strict_types=1);

use App\Infrastructure\Security\MetadataSanitizer;

test('metadata sanitizer strips html tags', function () {
    $sanitizer = new MetadataSanitizer;
    $input = ['key' => 'Hello <script>alert("XSS")</script> World'];
    $output = $sanitizer->clean($input);

    expect($output['key'])->toBe('Hello alert("XSS") World');
});

test('metadata sanitizer prunes deep nesting', function () {
    $sanitizer = new MetadataSanitizer;
    $input = ['level1' => ['level2' => ['level3' => ['level4' => 'too deep']]]];
    $output = $sanitizer->clean($input);

    expect($output['level1']['level2']['level3'])->toBeArray()->toBeEmpty();
});

test('metadata sanitizer truncates long strings', function () {
    $sanitizer = new MetadataSanitizer;
    $longString = str_repeat('A', 1000);
    $input = ['key' => $longString];
    $output = $sanitizer->clean($input);

    expect(mb_strlen($output['key']))->toBe(500);
});

test('metadata sanitizer preserves scalar values', function () {
    $sanitizer = new MetadataSanitizer;
    $input = [
        'count' => 42,
        'active' => true,
        'ratio' => 3.14,
        'empty' => null,
    ];
    $output = $sanitizer->clean($input);

    expect($output)->toBe($input);
});

test('metadata sanitizer sanitizes keys', function () {
    $sanitizer = new MetadataSanitizer;
    $input = ['<b>bold</b>' => 'value'];
    $output = $sanitizer->clean($input);

    expect($output)->toHaveKey('bold');
    expect(array_key_exists('<b>bold</b>', $output))->toBeFalse();
});

test('metadata sanitizer drops non-scalar non-array values', function () {
    $sanitizer = new MetadataSanitizer;
    $input = ['obj' => new stdClass, 'safe' => 'ok'];
    $output = $sanitizer->clean($input);

    expect(array_key_exists('obj', $output))->toBeFalse();
    expect($output['safe'])->toBe('ok');
});
