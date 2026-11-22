<?php

use App\Sso\MinesProvisioner;

/*
 * Dot.Mines' own choices for the shared sign-in package (packages/dot-sso-client). Everything else, including
 * DOTSSO_ENABLED (default false), comes from the package's defaults and the host .env.
 *
 * TENANT ISOLATION: central sign-in only answers "who is this person". It never chooses, creates or changes a Mines
 * team, role or membership. Mines stays the authority for who may work in which mine.
 *
 * `auto_provision` is true ONLY so the package asks MinesProvisioner::create(), which refuses everyone except a person
 * who holds a pending team invitation for that exact verified address (App\Sso\InvitedAccount). It is NOT tied to
 * INFODOT_PROVISION_USERS and there is no setting that opens accounts to unknown people.
 */
// InfoDot matches the registered post-logout address EXACTLY, and the package's own default (url('/')) has no trailing
// slash. Mines always registers and sends `<origin>/`, whatever the host .env says (tests/Feature/Sso/MinesSignInTest).
$postLogout = rtrim((string) (env('DOTSSO_POST_LOGOUT_URI') ?: env('APP_URL', 'http://localhost')), '/').'/';

return [
    'post_logout_redirect_uri' => $postLogout,

    'provisioner' => MinesProvisioner::class,

    'auto_provision' => true,

    'landing' => '/dashboard',

    // A deep link may open any page of the signed-in app (a team invitation is a signed URL: its query is kept whole).
    // Machine doors, public links and the auth pages are never a destination.
    'deep_link' => [
        'allow' => ['#^/#'],
        'deny' => ['#^/(sso|login|register|logout|forgot-password|reset-password|auth|oauth|two-factor-challenge|user/confirm-password|api|up|offline|openapi\.json|guardian|csp-report|sanctum|webhooks?|broadcasting|email/unsubscribe)(/|\?|$)#i'],
    ],

    'allow_paths' => [],

    // Accept an invited person (pending Jetstream team invitation for the verified address) as a bare account with no
    // team. Turning it off leaves invited newcomers on the "no account yet" page until an administrator creates them.
    'accept_invited' => (bool) env('DOTSSO_ACCEPT_INVITED', true),
];
