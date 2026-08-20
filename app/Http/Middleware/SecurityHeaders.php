<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
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
        // Content Security Policy - Helps prevent XSS attacks
        // Stronger CSP: remove 'unsafe-inline' and 'unsafe-eval'. Prefer nonces or SRI
        // for inline assets. Generate a per-request nonce and add it to script/style-src.
        //
        // This must run BEFORE $next($request): the view is rendered inside $next(),
        // so the nonce needs to exist first for both Vite (@vite tags) and any manual
        // nonce="{{ ... }}" attributes in Blade to pick it up.
        $nonce = bin2hex(random_bytes(12));

        // Share nonce with views. Views can read via request()->attributes->get('csp_nonce'),
        // and @vite()-generated <script>/<link> tags pick it up automatically.
        $request->attributes->set('csp_nonce', $nonce);
        app(Vite::class)->useCspNonce($nonce);

        $response = $next($request);

        // 'unsafe-eval' is REQUIRED by Livewire 3: its bundled Alpine
        // evaluates x-data/x-show expressions via new Function(). Without it
        // every Alpine binding in the app EvalErrors -- confirmed live in
        // production (mobile menu, notification panel, all toggles dead).
        // Locally this never surfaced because non-production environments
        // send the policy as Report-Only (see below).
        $scriptSrc = "'self' 'nonce-{$nonce}' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com";

        // Styles deliberately use 'unsafe-inline' WITHOUT a nonce: Livewire
        // injects runtime <style> elements (wire:loading/wire:cloak rules)
        // that can never carry our nonce, and per the CSP spec the presence
        // of a nonce/hash in a directive makes browsers IGNORE
        // 'unsafe-inline' there. Inline styles are a far weaker injection
        // vector than scripts; script-src stays nonce-locked.
        $styleSrc = "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com https://fonts.bunny.net https://unpkg.com";

        $csp = "default-src 'self'; ".
               "script-src {$scriptSrc}; ".
               "script-src-elem {$scriptSrc}; ".
               "style-src {$styleSrc}; ".
               "style-src-elem {$styleSrc}; ".
               // Alpine.js (x-show/x-transition) and Livewire mutate element.style directly at
               // runtime — nonces and hashes never apply to style="" attribute mutations per the
               // CSP spec, only to <style>/<script> elements. Scoping 'unsafe-inline' to just the
               // attribute vector (not style-src-elem, not script-src) keeps new <style> tags and
               // all scripts nonce-locked while still allowing this first-party JS to toggle styles.
               "style-src-attr 'self' 'unsafe-inline'; ".
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

        // Enable browser XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy - Control how much referrer information is shared
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Authenticated (operational) pages must never be indexed by search
        // engines -- robots.txt is advisory only, this header is binding.
        // Public marketing/legal pages stay indexable.
        if ($request->user() !== null) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Force HTTPS in production
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Permissions Policy - Control browser features
        $response->headers->set('Permissions-Policy',
            'geolocation=(self), '.
            'microphone=(), '.
            'camera=(), '.
            'payment=(), '.
            'usb=()'
        );

        return $response;
    }
}
