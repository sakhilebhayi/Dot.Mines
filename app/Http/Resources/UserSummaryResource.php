<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person, as referenced from another record (who acknowledged an alert,
 * who generated a report, who dispensed fuel).
 *
 * Deliberately minimal. Eager-loaded user relations previously serialized
 * the whole User model into API payloads -- email address, 2FA confirmation
 * timestamp, notification preferences, current team -- on endpoints whose
 * subject was a report or a work order. A name and an id are what a
 * consumer needs to render "acknowledged by"; the rest was accidental PII.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
