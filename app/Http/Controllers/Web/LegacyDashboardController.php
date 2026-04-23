<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LegacyDashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $target = AuthRedirector::destinationFor($user instanceof User ? $user : null);

        return redirect()->to($target);
    }
}
