<?php

declare(strict_types=1);

use App\Infrastructure\Security\MetadataSanitizer;

beforeEach(function () {
    $this->sanitizer = new MetadataSanitizer();
});

test('metadata sanitizer strips html tags', function () {
    $input = ['key' => 'Hello <script>alert("XSS")</script> World'];
    $output = $this->sanitizer->clean($input);

    expect($output['key'])->toBe('Hello alert("XSS") World');
});

test('metadata sanitizer prunes deep nesting', function () {
    $input = ['level1' => ['level2' => ['level3' => ['level4' => 'too deep']]]];
    $output = $this->sanitizer->clean($input);

    expect($output['level1']['level2']['level3'])->toBeArray()->toBeEmpty();
});

test('metadata sanitizer truncates long strings', function () {
    $longString = str_repeat('A', 1000);
    $input = ['key' => $longString];
    $output = $this->sanitizer->clean($input);

    expect(mb_strlen($output['key']))->toBe(500);
});

test('metadata sanitizer preserves scalar values', function () {
    $input = [
        'count' => 42,
        'active' => true,
        'ratio' => 3.14,
        'empty' => null,
    ];
    $output = $this->sanitizer->clean($input);

    expect($output)->toBe($input);
});

test('metadata sanitizer sanitizes keys', function () {
    $input = ['<b>bold</b>' => 'value'];
    $output = $this->sanitizer->clean($input);

    expect($output)->toHaveKey('bold');
    expect($output)->not->toHaveKey('<b>bold</b>');
});

test('metadata sanitizer drops non-scalar non-array values', function () {
    $input = ['obj' => new stdClass(), 'safe' => 'ok'];
    $output = $this->sanitizer->clean($input);

    expect($output)->not->toHaveKey('obj');
    expect($output['safe'])->toBe('ok');
});
