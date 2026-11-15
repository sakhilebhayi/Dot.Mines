<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Ecosystem\LinkOutcome;
use App\Actions\Ecosystem\LinkUserFromInfoDot;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InfoDot\IdentityClient;
use App\Services\InfoDot\InfoDotUnavailable;
use App\Support\ApiPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * The Dot.Mines receiver for InfoDot's launch form (R-52 section 6; same contract as Dot.Tasks and Dot.Central).
 *
 * InfoDot posts a single-use token in the BODY of a form (never a URL). Mines checks the post came from InfoDot,
 * redeems the token server to server with its own secret, links the proven identity to an existing Mines user, and
 * signs that user in on a fresh session. Mines' own email and password sign-in is untouched.
 *
 * Every refusal renders a clear page (never a blank page) and logs a distinct, non-sensitive reason. The token, the
 * secret and the person's email are never logged.
 *
 * Two-factor: a user with two-factor set up in Mines still has to pass Fortify's challenge. InfoDot's proof says who
 * they are but nothing about a second factor, and Mines requires 2FA for its admins, so SSO must not become a way
 * round it. They are sent into the existing two-factor.login flow instead of being signed in directly.
 */
class EcosystemAuthController extends Controller
{
    /** @var array<string, array{status: int, title: string, message: string, retry: bool}> */
    private const REASONS = [
        'origin_rejected' => [
            'status' => 403,
            'title' => 'That sign-in did not come from InfoDot',
            'message' => 'For your safety Dot.Mines only accepts a sign-in that InfoDot sends. Open Dot.Mines from the InfoDot app switcher, or sign in here with your Dot.Mines email and password.',
            'retry' => true,
        ],
        'token_missing' => [
            'status' => 400,
            'title' => 'The sign-in from InfoDot was incomplete',
            'message' => 'No sign-in code arrived. Open Dot.Mines again from InfoDot, or sign in here with your Dot.Mines email and password.',
            'retry' => true,
        ],
        'not_configured' => [
            'status' => 503,
            'title' => 'Sign-in through InfoDot is not set up here yet',
            'message' => 'Dot.Mines has not been connected to InfoDot on this server. Sign in with your Dot.Mines email and password, and tell your administrator.',
            'retry' => false,
        ],
        'infodot_unavailable' => [
            'status' => 503,
            'title' => 'InfoDot could not be reached',
            'message' => 'Sign-in through InfoDot is unavailable for a moment. Try again shortly, or sign in here with your Dot.Mines email and password.',
            'retry' => true,
        ],
        'token_refused' => [
            'status' => 403,
            'title' => 'That InfoDot sign-in has expired or was already used',
            'message' => 'Sign-in codes work once and only for a minute. Open Dot.Mines again from InfoDot to get a fresh one, or sign in here with your Dot.Mines email and password.',
            'retry' => true,
        ],
        'wrong_platform' => [
            'status' => 403,
            'title' => 'That InfoDot sign-in was meant for a different app',
            'message' => 'Open Dot.Mines again from the InfoDot app switcher.',
            'retry' => true,
        ],
        LinkOutcome::BAD_IDENTITY => [
            'status' => 403,
            'title' => 'InfoDot could not confirm who you are',
            'message' => 'Your InfoDot account needs a verified email address before it can open Dot.Mines. Verify it in InfoDot, or sign in here with your Dot.Mines email and password.',
            'retry' => false,
        ],
        LinkOutcome::NO_ACCOUNT => [
            'status' => 403,
            'title' => 'Your InfoDot account is not linked to a Dot.Mines account',
            'message' => 'Dot.Mines accounts are created by invitation. Ask your Dot.Mines administrator to invite you with the email address you use on InfoDot. Nothing was created. If you already have a Dot.Mines login, sign in with it.',
            'retry' => false,
        ],
        LinkOutcome::MINES_EMAIL_UNVERIFIED => [
            'status' => 403,
            'title' => 'Verify your Dot.Mines email first',
            'message' => 'A Dot.Mines account exists for your email, but that address has not been verified there. Sign in with your Dot.Mines password and verify it, then open Dot.Mines from InfoDot again.',
            'retry' => false,
        ],
        LinkOutcome::LINKED_ELSEWHERE => [
            'status' => 403,
            'title' => 'That Dot.Mines account belongs to a different InfoDot account',
            'message' => 'Your email matches a Dot.Mines account that is already connected to another InfoDot identity, so it was not opened. Ask your Dot.Mines administrator for help.',
            'retry' => false,
        ],
    ];

    public function legacy(): RedirectResponse
    {
        // A token in a URL is never read (nor wanted): the old GET shape just goes to the sign-in page.
        return redirect('/login');
    }

    public function handle(Request $request, IdentityClient $infoDot, LinkUserFromInfoDot $link): Response|RedirectResponse
    {
        if (! $this->postedFromInfoDot($request)) {
            return $this->refused('origin_rejected', ['origin_host' => $this->postedHost($request)]);
        }

        $token = $request->post('token');

        if (! is_string($token) || $token === '') {
            return $this->refused('token_missing');
        }

        if (! $infoDot->enabled()) {
            Log::error('Mines SSO receiver not configured: INFODOT_SERVICE_SECRET or INFODOT_REDEEM_URL is empty.');

            return $this->refused('not_configured');
        }

        try {
            $identity = $infoDot->redeem($token);
        } catch (InfoDotUnavailable) {
            return $this->refused('infodot_unavailable');
        }

        if ($identity === null) {
            return $this->refused('token_refused');
        }

        $platform = ApiPayload::str($identity['platform'] ?? null);

        if ($platform !== '' && $platform !== ApiPayload::str(config('ecosystem.infodot.platform'))) {
            return $this->refused('wrong_platform');
        }

        $outcome = $link->resolve($identity);

        if (! $outcome->user instanceof User) {
            $infodotUser = ApiPayload::assoc($identity['user'] ?? null);

            return $this->refused((string) $outcome->reason, ['infodot_user_uuid' => ApiPayload::str($infodotUser['uuid'] ?? null)]);
        }

        $user = $outcome->user;

        // Fresh session for the arriving person (no fixation, nothing left over from a previous person on this browser).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The team is chosen among teams they already belong to; anything else leaves their own current team alone.
        $link->selectTeam($user, $identity);

        if ($this->hasTwoFactor($user)) {
            // The existing Fortify challenge finishes the sign-in (it logs the user in and regenerates the session).
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);
            Log::info('Mines SSO verified by InfoDot; sending to the two-factor challenge.', ['user_id' => $user->id]);

            return redirect()->route('two-factor.login', [], 303)->withHeaders($this->privateHeaders());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard', [], 303)->withHeaders($this->privateHeaders());
    }

    private function hasTwoFactor(User $user): bool
    {
        // The same test Fortify applies to a password sign-in (RedirectIfTwoFactorAuthenticatable).
        return filled($user->two_factor_secret);
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer'];
    }

    /** Login-CSRF guard: the post must be made from the InfoDot origin (browsers always send Origin or Referer on a form post). */
    private function postedFromInfoDot(Request $request): bool
    {
        $host = $this->postedHost($request);
        $infodot = parse_url(ApiPayload::str(config('ecosystem.infodot.redeem_url')), PHP_URL_HOST);

        return $host !== null && is_string($infodot) && $infodot !== '' && strtolower($host) === strtolower($infodot);
    }

    private function postedHost(Request $request): ?string
    {
        $origin = (string) $request->headers->get('Origin', '');
        $source = $origin !== '' && $origin !== 'null' ? $origin : (string) $request->headers->get('Referer', '');
        $host = parse_url($source, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /** @param array<string, mixed> $context host and uuid only: never the token, the secret or an email address */
    private function refused(string $reason, array $context = []): Response
    {
        $copy = self::REASONS[$reason] ?? self::REASONS['token_refused'];

        Log::warning('Mines SSO refused.', ['reason' => $reason, ...$context]);

        $infodot = rtrim(ApiPayload::str(config('ecosystem.platforms.infodot.url')), '/');

        return response()->view('auth.ecosystem-refused', [
            'code' => $copy['status'],
            'reason' => $reason,
            'title' => $copy['title'],
            'message' => $copy['message'],
            'retryUrl' => $copy['retry'] && $infodot !== '' ? $infodot.'/go/mines' : null,
            'infodotUrl' => $infodot !== '' ? $infodot : null,
        ], $copy['status'])->withHeaders($this->privateHeaders());
    }
}
