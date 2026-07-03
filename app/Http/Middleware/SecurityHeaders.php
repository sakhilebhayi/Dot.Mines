<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders Middleware
 *
 * Adds security-related HTTP headers to all responses
 * Helps protect against common web vulnerabilities
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate nonce BEFORE calling $next() so Blade views can read it
        // via request()->attributes->get('csp_nonce') during rendering.
        // Generating it after $next() means the nonce in the HTML and the
        // nonce in the CSP header would always be different (making nonces useless).
        $nonce = bin2hex(random_bytes(12));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        // Content Security Policy - Helps prevent XSS attacks
        // 'unsafe-inline' is required in style-src because Leaflet.js dynamically
        // sets inline style="" attributes and injects <style> elements via JavaScript;
        // these cannot carry a nonce and cannot be hashed.
        // 'unsafe-eval' is required because Alpine.js evaluates x-* expressions
        // using new Function(), which triggers unsafe-eval.
        $scriptSrc = "'self' 'nonce-{$nonce}' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com";
        $styleSrc = "'self' 'unsafe-inline' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com https://fonts.bunny.net https://unpkg.com";

        $csp = "default-src 'self'; ".
               "script-src {$scriptSrc}; ".
               "script-src-elem {$scriptSrc}; ".
               "style-src {$styleSrc}; ".
               "style-src-elem {$styleSrc}; ".
               "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net; ".
               "img-src 'self' data: https: blob:; ".
               "connect-src 'self' https://unpkg.com https://cdnjs.cloudflare.com https://*.pusher.com https://*.pusherapp.com ws: wss:; ".
               "frame-ancestors 'none';";

        // Set enforcement in production; use report-only in staging/testing to collect violations.
        if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', $csp);
        } else {
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        }

        // Prevent page from being loaded in an iframe - Clickjacking protection
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: disabled (value 0) — the legacy XSS auditor is removed from
        // modern browsers and can introduce vulnerabilities. CSP (above) is the correct
        // defence; keeping the header at 0 prevents the auditor from firing in old browsers.
        $response->headers->set('X-XSS-Protection', '0');

        // Referrer Policy - Control how much referrer information is shared
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Force HTTPS in production
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Permissions Policy - disable all browser features the app does not use.
        // geolocation is () (fully blocked): machine GPS comes from server-side OEM APIs,
        // not the browser Geolocation API.
        $response->headers->set('Permissions-Policy',
            'geolocation=(), '.
            'microphone=(), '.
            'camera=(), '.
            'payment=(), '.
            'usb=()'
        );

        // Cross-Origin-Opener-Policy: isolates the browsing context group so that
        // cross-origin documents cannot access window references to this page.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin-Resource-Policy: prevents other origins from reading this
        // response via no-cors requests (e.g. <img src="...">, fetch with no-cors).
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // X-DNS-Prefetch-Control: prevents browsers from speculatively resolving
        // domains linked in the page, reducing information leakage to DNS resolvers.
        $response->headers->set('X-DNS-Prefetch-Control', 'off');

        return $response;
    }
}
