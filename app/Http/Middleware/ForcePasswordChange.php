<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->force_password_change) {
            return redirect()->route('password.change')->with(
                'warning',
                'Por seguridad, debes establecer una contraseña personal antes de continuar.'
            );
        }

        return $next($request);
    }
}
