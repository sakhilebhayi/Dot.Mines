<?php

namespace App\Support;

/**
 * The states a dated credential can be in.
 *
 * A class rather than constants on the ExpiresOn trait because PHP does not
 * allow reading a constant off a trait directly -- and the compliance
 * service, the UI badges and the alert job all need this vocabulary without
 * picking an arbitrary model to read it from.
 */
final class CredentialStatus
{
    public const VALID = 'valid';

    public const EXPIRING = 'expiring';

    public const EXPIRED = 'expired';

    public const PERPETUAL = 'perpetual';

    public const MISSING = 'missing';
}
