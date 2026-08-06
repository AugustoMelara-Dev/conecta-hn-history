<?php

declare(strict_types=1);

use App\Domain\Crm\ValueObjects\DealScore;
use App\Models\Contact;

it('LeadScoringService assigns higher score to qualified leads', function () {
    $service = new \App\Application\Crm\LeadScoringService();

    $qualifiedContact = new Contact([
        'lifecycle_stage' => 'qualified',
        'email' => 'test@example.com',
        'phone' => '+504 9999 9999',
        'job_title' => 'Director',
        'source' => 'referral',
        'last_contact_at' => now()->subDays(2),
    ]);
    $qualifiedContact->id = 'test-qualified';

    $unqualifiedContact = new Contact([
        'lifecycle_stage' => 'lead',
        'email' => null,
        'phone' => null,
        'source' => 'cold',
        'last_contact_at' => now()->subDays(200),
    ]);
    $unqualifiedContact->id = 'test-unqualified';

    $qualifiedScore = $service->score($qualifiedContact);
    $unqualifiedScore = $service->score($unqualifiedContact);

    expect($qualifiedScore->value)->toBeGreaterThan($unqualifiedScore->value);
});

it('DealScore tier classification works', function () {
    $hot = DealScore::fromFactors(85, []);
    $warm = DealScore::fromFactors(70, []);
    $cold = DealScore::fromFactors(20, []);

    expect($hot->tier)->toBe('hot');
    expect($warm->tier)->toBe('warm');
    expect($cold->tier)->toBe('cold');

    expect($hot->isHot())->toBeTrue();
    expect($warm->isWarm())->toBeTrue();
    expect($cold->isHot())->toBeFalse();
});

it('DealScore rejects invalid values', function () {
    DealScore::fromFactors(150, []);
})->throws(InvalidArgumentException::class);
