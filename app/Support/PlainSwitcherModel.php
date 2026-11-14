<?php

namespace App\Support;

use Dot\Ui\PlatformSwitcher;

/**
 * The view model for the shared Dot Apps switcher in Dot.Mines.
 *
 * Mines has its own sign-in and its own subscription handling. It has no InfoDot company concept for the signed-in
 * person and no InfoDot service secret, so it cannot ask InfoDot which platforms a company holds, and it must not
 * pretend to. The honest answer is the shared package's "degraded" model: the core platforms, each opened through
 * InfoDot's /go/<key> (InfoDot decides, and signs the person in), this platform marked "You are here", no tile saying
 * what is or is not switched on, and a visible line that says why.
 *
 * The platform list comes from the package's central registry data (dot-ui/data/categories.json), never from
 * config/ecosystem.php, so a platform added there appears with no Mines edit. The package keeps its degraded builder
 * private, because every other host reaches it by failing to ask InfoDot; Mines is a host that never asks, so it
 * reaches the same builder directly instead of inventing a second list.
 */
/** @psalm-suppress UnusedClass Used from resources/views/livewire/navbar.blade.php. */
class PlainSwitcherModel
{
    public const PLATFORM = 'mines';

    public const NOTICE = 'Could not check your platforms from here';

    public const NOTICE_DETAIL = 'Dot.Mines has its own sign-in, so it does not know which platforms your company has. These open through InfoDot, which checks your access.';

    /**
     * @return array<array-key, mixed>
     */
    public static function build(): array
    {
        $switcher = PlatformSwitcher::fromConfig(self::PLATFORM);

        /** @var array<array-key, mixed> $model */
        $model = (new \ReflectionMethod($switcher, 'degraded'))->invoke($switcher, null);

        return $model;
    }
}
