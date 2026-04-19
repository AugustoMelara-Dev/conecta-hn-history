<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class LegalPageController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', [
            'version' => (string) config('legal.current_versions.privacy'),
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', [
            'version' => (string) config('legal.current_versions.terms'),
        ]);
    }
}
