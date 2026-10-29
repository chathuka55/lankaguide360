<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
        [$dev, $devWs] = $this->viteDevSources();

        return implode('; ', array_filter([
            "default-src 'self'",
            trim("script-src 'self' 'unsafe-inline' 'unsafe-eval' {$dev}"),
            trim("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com {$dev}"),
            trim("font-src 'self' https://fonts.gstatic.com data: {$dev}"),
            trim("img-src 'self' data: blob: https://*.tile.openstreetmap.org https://upload.wikimedia.org {$dev}"),
            trim("connect-src 'self' {$dev} {$devWs}"),
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));
    }

    /**
     * Sources for Vite's dev server (npm run dev), which serves assets from another port and
     * pushes updates over a websocket. Local only, and only while public/hot exists.
     *
     * CSP cannot name IPv6 hosts: when Vite announces http://[::1]:5173 we allow the http/ws
     * schemes instead, otherwise the browser drops the source and blocks every asset.
     *
     * @return array{0: string, 1: string}
     */
    private function viteDevSources(): array
    {
        if (! app()->isLocal() || ! is_file(Vite::hotFile())) {
            return ['', ''];
        }

        $url = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '' || str_starts_with($host, '[') || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ['http:', 'ws:'];
        }

        return [$url, (string) preg_replace('#^http#', 'ws', $url)];
    }
}
