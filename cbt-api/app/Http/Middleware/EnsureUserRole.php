<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'Akun tidak memiliki akses ke modul ini.');
        if ($roles !== ['platform']) {
            abort_unless(in_array($user->role->value, $roles, true), 403, 'Role tidak diizinkan.');
        }

        return $next($request);
    }
}
