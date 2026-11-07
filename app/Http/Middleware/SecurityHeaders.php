<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $turnstileOrigin = config('turnstile.enabled') ? ' https://challenges.cloudflare.com' : '';
        // Only authorized raster/PDF attachments may be framed by this same application.
        $attachmentFrame = $request->routeIs('tickets.attachments.inline');
        $frameAncestors = $attachmentFrame ? "'self'" : "'none'";

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $attachmentFrame ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'{$turnstileOrigin}; font-src 'self' data:; connect-src 'self'; frame-src 'self'{$turnstileOrigin}; frame-ancestors {$frameAncestors}; base-uri 'self'; form-action 'self'"
        );

        return $response;
    }
}
