<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every web response (phase 12). The CSP allows the OSM tile servers,
 * Google Fonts and the Vite dev server in local development.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        // Vite's dev server (npm run dev) serves scripts from another port and uses a websocket.
        $dev = app()->isLocal() && is_file(public_path('hot')) ? trim((string) file_get_contents(public_path('hot'))) : '';
        $devWs = $dev ? preg_replace('#^http#', 'ws', $dev) : '';

        return implode('; ', array_filter([
            "default-src 'self'",
            trim("script-src 'self' 'unsafe-inline' 'unsafe-eval' {$dev}"),
            trim("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com {$dev}"),
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://upload.wikimedia.org",
            trim("connect-src 'self' {$dev} {$devWs}"),
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));
    }
}
