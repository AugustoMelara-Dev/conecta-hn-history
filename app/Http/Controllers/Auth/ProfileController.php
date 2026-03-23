<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\DeleteUserAction;
use App\Application\Actions\Auth\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Support\Auth\AuthRedirector;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

final class ProfileController extends Controller
{
    public function __construct(
        private UpdateUserProfileAction $updateUserAction,
        private DeleteUserAction $deleteUserAction,
        private StatefulGuard $auth,
        private Redirector $redirector
    ) {}

    public function edit(Request $request): Response|RedirectResponse
    {
        return redirect()->to(AuthRedirector::profileDestinationFor($request->user()));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // DELEGATED: Business logic (email dirty check, save) moved to Action
        $this->updateUserAction->execute(
            $request->user(),
            $request->validated()
        );

        return $this->redirector->route('profile.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $this->auth->logout();

        // DELEGATED: Deletion logic moved to Action
        $this->deleteUserAction->execute($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->redirector->to('/');
    }
}
