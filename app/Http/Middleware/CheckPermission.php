<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!$request->user()) {
            return abort(401, 'Non authentifié');
        }

        if (!$request->user()->hasAnyPermission($permissions)) {
            return abort(403, 'Permission refusée. Permission(s) requise(s): ' . implode(', ', $permissions));
        }

        return $next($request);
    }
}
