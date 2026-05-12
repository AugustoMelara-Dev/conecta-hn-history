<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RedirectController extends Controller
{
    public function explore(Request $request): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect('/directorio'.($query ? '?'.$query : ''), 302);
    }
}
