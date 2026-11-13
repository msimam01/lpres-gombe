<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the administration area to designated L-PRES administrators.
 * Authenticated non-administrators are refused, never redirected into the
 * admin panel.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            abort(403, 'This area is restricted to L-PRES administrators.');
        }

        return $next($request);
    }
}
