<?php

use App\Domain\Shared\ValueObjects\EncryptedEmail;
test('it accepts an encrypted string and stores it immutably', function () {
    $encryptedPayload = base64_encode('user@example.com'); // Mock encrypted payload format
    $vo = new EncryptedEmail($encryptedPayload);

    expect($vo->value())->toBe($encryptedPayload);
});

test('it throws an exception if a plain text email is provided', function () {
    new EncryptedEmail('plain.text@example.com');
})->throws(InvalidArgumentException::class, 'Security Violation: Email must be encrypted');

test('it throws an exception if the encrypted string is empty', function () {
    new EncryptedEmail('   ');
})->throws(InvalidArgumentException::class, 'Encrypted email cannot be empty');
