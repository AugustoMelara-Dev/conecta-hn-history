<?php

declare(strict_types=1);

use App\Domain\Automation\Enums\ConditionType;

it('ConditionType::Equals evaluates correctly', function () {
    expect(ConditionType::Equals->evaluate('foo', 'foo'))->toBeTrue();
    expect(ConditionType::Equals->evaluate('foo', 'bar'))->toBeFalse();
});

it('ConditionType::Contains works with strings', function () {
    expect(ConditionType::Contains->evaluate('hello world', 'world'))->toBeTrue();
    expect(ConditionType::Contains->evaluate('hello', 'xyz'))->toBeFalse();
});

it('ConditionType::GreaterThan and LessThan work numerically', function () {
    expect(ConditionType::GreaterThan->evaluate(100, 50))->toBeTrue();
    expect(ConditionType::GreaterThan->evaluate(50, 100))->toBeFalse();
    expect(ConditionType::LessThan->evaluate(10, 20))->toBeTrue();
    expect(ConditionType::LessThan->evaluate(20, 10))->toBeFalse();
});

it('ConditionType::IsTrue and IsFalse work with booleans', function () {
    expect(ConditionType::IsTrue->evaluate(true, null))->toBeTrue();
    expect(ConditionType::IsTrue->evaluate(false, null))->toBeFalse();
    expect(ConditionType::IsFalse->evaluate(false, null))->toBeTrue();
    expect(ConditionType::IsFalse->evaluate(true, null))->toBeFalse();
});

it('ConditionType::DateAfter and DateBefore work', function () {
    expect(ConditionType::DateAfter->evaluate('2026-01-01', '2025-01-01'))->toBeTrue();
    expect(ConditionType::DateBefore->evaluate('2025-01-01', '2026-01-01'))->toBeTrue();
});
