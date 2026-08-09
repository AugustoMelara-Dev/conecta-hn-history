<?php

declare(strict_types=1);

use App\Application\Ai\Registry\AiRegistrySecretPolicy;
use Illuminate\Validation\ValidationException;

it('rejects camel case uri and encoded credential material', function (array $payload): void {
    expect(fn () => (new AiRegistrySecretPolicy)->assertSafePayload($payload))
        ->toThrow(ValidationException::class);
})->with([
    'camel api key' => [['apiKey' => 'opaque']],
    'camel client secret' => [['clientSecret' => 'opaque']],
    'camel access key' => [['accessKey' => 'opaque']],
    'camel private key' => [['privateKey' => 'opaque']],
    'camel auth token' => [['authToken' => 'opaque']],
    'camel access token' => [['accessToken' => 'opaque']],
    'uri user info' => [['endpoint' => 'https://operator:Sup3rSecret@example.test/v1']],
    'uri query' => [['endpoint' => 'https://api.example.test/v1?apiKey=sk-project-1234567890abcdef']],
    'uri short token query' => [['endpoint' => 'https://api.example.test/v1?token=abc']],
    'relative token query' => [['endpoint' => '/callback?token=abc']],
    'relative camel query' => [['endpoint' => '/callback?apiKey=x']],
    'absolute fragment credential' => [['endpoint' => 'https://api.example.test/v1#clientSecret=y']],
    'relative fragment credential' => [['endpoint' => '/callback#apiKey=x']],
    'leading query assignment' => [['value' => '?token=abc']],
    'ampersand assignment' => [['value' => '&apiKey=x']],
    'fragment assignment' => [['value' => '#clientSecret=y']],
    'delimited credential' => [['value' => 'operation=rotate;credential=y']],
    'percent encoded' => [['value' => rawurlencode('clientSecret=encoded-secret-value')]],
    'percent encoded relative query' => [['value' => rawurlencode('?token=abc')]],
    'base64 encoded' => [['value' => base64_encode('apiKey=encoded-secret-value')]],
    'base64 encoded token' => [['value' => base64_encode('accessToken=abc')]],
    'short base64 token assignment' => [['value' => 'dG9rZW49eA==']],
    'short unpadded base64url token assignment' => [['value' => 'dG9rZW49eA']],
]);

it('keeps operational prose and bounded output configuration valid', function (): void {
    (new AiRegistrySecretPolicy)->assertSafePayload([
        'notes' => 'Credential rotation policy reviewed without inline material.',
        'maxOutputTokens' => 640,
        'endpoint' => 'https://api.example.test/v1',
        'encoded_label' => 'dGVzdA==',
        'short_code' => 'YWJj',
    ]);

    expect(true)->toBeTrue();
});
