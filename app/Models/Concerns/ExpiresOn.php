<?php

namespace App\Models\Concerns;

use App\Support\ApiPayload;
use App\Support\CredentialStatus;
use Carbon\CarbonInterface;

/**
 * Shared expiry behaviour for anything an operator has to keep current.
 *
 * Licences, medicals and training certificates all answer the same three
 * questions -- is it valid, is it about to lapse, has it lapsed -- and they
 * must answer them identically, because the compliance dashboard, the
 * eligibility check and the expiry alerts all read them. Computing the answer
 * from `expires_on` every time is what stops a stored status quietly
 * disagreeing with the date printed next to it.
 */
trait ExpiresOn
{
    /**
     * How many days before expiry counts as "expiring soon".
     *
     * The widest configured warning window: a credential is shown as expiring
     * from the first moment anyone would be told about it, so the badge on
     * screen and the notification in the inbox never contradict each other.
     */
    public static function warningWindowDays(): int
    {
        $windows = ApiPayload::intList(config('operators.warning_days'));

        return $windows === [] ? 30 : max($windows);
    }

    public function expiryStatus(?CarbonInterface $asOf = null): string
    {
        $expiry = $this->expires_on;

        if ($expiry === null) {
            return CredentialStatus::PERPETUAL;
        }

        $asOf ??= now();

        if ($expiry->isBefore($asOf->copy()->startOfDay())) {
            return CredentialStatus::EXPIRED;
        }

        return $expiry->isBefore($asOf->copy()->addDays(static::warningWindowDays()))
            ? CredentialStatus::EXPIRING
            : CredentialStatus::VALID;
    }

    /**
     * Whole days until expiry; negative once it has passed, null if it never
     * expires.
     */
    public function daysUntilExpiry(?CarbonInterface $asOf = null): ?int
    {
        if ($this->expires_on === null) {
            return null;
        }

        $asOf ??= now();

        return (int) $asOf->copy()->startOfDay()->diffInDays($this->expires_on->copy()->startOfDay(), false);
    }

    public function hasExpired(?CarbonInterface $asOf = null): bool
    {
        return $this->expiryStatus($asOf) === CredentialStatus::EXPIRED;
    }

    /**
     * Current enough to rely on -- not expired, and not otherwise withdrawn.
     */
    public function isCurrent(?CarbonInterface $asOf = null): bool
    {
        return ! $this->hasExpired($asOf) && $this->isInGoodStanding();
    }

    /**
     * Overridden by anything that can be withdrawn independently of its date
     * (a suspended licence, a failed competency).
     */
    public function isInGoodStanding(): bool
    {
        return true;
    }
}
