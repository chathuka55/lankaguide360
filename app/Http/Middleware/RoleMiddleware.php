<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin,agent')
 *
 * Guests are sent to the login page; signed-in users without one of the roles get 403.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        abort_unless($user->hasRole(...$roles), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
