<?php

namespace App\Sso;

use App\Models\User;
use Dot\SsoClient\Http\Controllers\SsoController;
use Dot\SsoClient\Support\Sso;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * The package's controller with ONE difference: finishing a sign-in honours Mines' two-factor rule.
 *
 * InfoDot proves who the person is, nothing about a second factor, and Mines requires 2FA for its admins. A person with
 * two-factor set up here is therefore not logged in directly: the session is prepared for Fortify's own challenge
 * (login.id), the deep link is kept as the intended URL, and the challenge finishes the sign-in. Everyone else is
 * handled by the package unchanged. Bound over the package controller in MinesSsoServiceProvider; the package is not edited.
 */
class MinesSsoController extends SsoController
{
    /** @param array{sub: string, email: string, name: string} $claims */
    #[\Override]
    public function finish(Request $request, Authenticatable $user, array $claims, ?string $deepLink, string $idToken, string $how): RedirectResponse
    {
        if (! $user instanceof User || blank($user->two_factor_secret)) {
            return parent::finish($request, $user, $claims, $deepLink, $idToken, $how);
        }

        // A fresh session for the arriving person (no fixation, nothing left from a previous person on this browser).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => false,
            // The back-channel logout record: if InfoDot signs this identity out before the code is entered, the session ends.
            'dotsso.session' => ['sub' => $claims['sub'], 'at_ms' => (int) floor(microtime(true) * 1000.0), 'id_token' => $idToken],
        ]);

        if ($deepLink !== null) {
            $request->session()->put('url.intended', $deepLink);
        }

        TwoFactorAuthenticationChallenged::dispatch($user);

        Sso::log('two_factor_challenge', ['how' => $how, 'sub' => Sso::tag($claims['sub']), 'deep_link' => $deepLink !== null]);

        return redirect()->route('two-factor.login')->header('Cache-Control', 'no-store');
    }
}
