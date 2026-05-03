<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantOnboardingState;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        return $user->hasVerifiedEmail()
                    ? redirect()->to(AuthRedirector::destinationFor($user))
                    : Inertia::render('Auth/VerifyEmail', [
                        'status' => session('status'),
                        'onboardingNextAction' => $this->onboardingNextActionFor($user),
                    ]);
    }

    /**
     * @return array{progress:int,title:string,copy:string,href:string}|null
     */
    private function onboardingNextActionFor(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $tenantId = $this->tenantIdFor($user);

        if ($tenantId === null) {
            return null;
        }

        $state = TenantOnboardingState::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->getKey())
            ->latest()
            ->first();

        if (! $state instanceof TenantOnboardingState || blank($state->next_action_title)) {
            return null;
        }

        return [
            'progress' => (int) $state->progress,
            'title' => (string) $state->next_action_title,
            'copy' => (string) $state->next_action_copy,
            'href' => (string) $state->next_action_href,
        ];
    }

    private function tenantIdFor(User $user): ?string
    {
        if (filled($user->tenant_id)) {
            return (string) $user->tenant_id;
        }

        $ownedTenantId = Tenant::query()
            ->where('user_id', $user->getKey())
            ->value('id');

        return is_string($ownedTenantId) && $ownedTenantId !== '' ? $ownedTenantId : null;
    }
}
