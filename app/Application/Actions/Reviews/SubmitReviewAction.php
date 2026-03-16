<?php

declare(strict_types=1);

namespace App\Application\Actions\Reviews;

use App\Application\Reviews\Results\SubmitReviewResult;
use App\Domain\Reviews\Events\ReviewCreated;
use App\Domain\Reviews\Repositories\ReviewRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Services\TenantContext;

final class SubmitReviewAction
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TenantContext $tenantContext
    ) {}

    public function execute(SubmitReviewResult $data): object
    {
        if (! $this->tenantContext->isInitialized()) {
            $this->tenantContext->setTenant($data->tenantId);
        } elseif ($this->tenantContext->isRoot()) {
            throw new \RuntimeException('Review submission requires a concrete tenant context.');
        } elseif ($this->tenantContext->id() !== $data->tenantId) {
            throw new \RuntimeException('Tenant context mismatch for review submission.');
        }

        $review = $this->reviewRepository->create([
            'product_id' => $data->productId,
            'rating' => $data->rating,
            'comment' => $data->comment,
            'is_approved' => false,
            'user_id' => $data->userId,
        ]);

        if ($data->productId !== null) {
            $this->eventDispatcher->dispatch(new ReviewCreated(
                reviewId: $review->id,
                productId: $data->productId,
                tenantId: $data->tenantId,
                rating: $data->rating
            ));
        }

        return $review;
    }
}

