<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Public\Contracts\BuyerRepository;
use App\Domain\TenantGovernance\Services\BuyerOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BuyerController - Clean Active Record
 */
final class BuyerController extends Controller
{
    public function __construct(
        private readonly BuyerRepository $repository,
        private readonly BuyerOnboardingService $onboarding
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('Buyer/Profile', [
            'favorites' => $this->repository->getFavorites($userId),
            'followedTenants' => $this->repository->getFollowedTenants($userId),
            'onboarding' => $this->onboarding->snapshot($request->user()),
        ]);
    }
}
