<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWhenSetupDone
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
