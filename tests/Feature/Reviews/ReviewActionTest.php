<?php

use App\Application\Actions\Reviews\SubmitReviewAction;
use App\Application\Reviews\Results\SubmitReviewResult;
use App\Filament\App\Resources\ReviewResource;
use App\Filament\App\Resources\ReviewResource\Pages\ListReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('submit review action, dto and resource classes compile correctly', function () {
    expect(class_exists(SubmitReviewResult::class))->toBeTrue();
    expect(class_exists(SubmitReviewAction::class))->toBeTrue();
    expect(class_exists(ReviewResource::class))->toBeTrue();
    expect(class_exists(ListReviews::class))->toBeTrue();
});
