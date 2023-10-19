<?php

namespace Fleetbase\Billing\Http\Middleware;

use Illuminate\Http\Request;

class CheckIsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        if (auth()->check()) {
            /** @var \Fleetbase\Models\User $user */
            $user = auth()->user();

            if ($user->isAdmin()) {
                return $next($request);
            }
        }

        return response()->error('User is not authorized to access this resource.', 401);
    }
}
