<?php

namespace App\Livewire\Concerns;

use InvalidArgumentException;

/**
 * The one way a Livewire component shows a toast.
 *
 * The toast host listens for the `notify` browser event and reads
 * `detail.type` / `detail.message` -- which is what Livewire produces for
 * NAMED dispatch arguments. For years, most components instead passed a
 * positional array, which Livewire wraps one level deeper
 * (`detail[0].message`), so the host read undefined twice and rendered a
 * blank glassy pill: the empty notifications reported from Operator
 * Fatigue existed on every one of those 128 call sites.
 *
 * Routing every toast through this helper makes the contract structural:
 * the shape cannot drift per call site, a blank message throws instead of
 * rendering an empty pill, and NotificationContractTest forbids raw
 * `dispatch('notify', ...)` outside this file.
 */
trait NotifiesUser
{
    /** @var list<string> */
    private static array $notifyTypes = ['success', 'error', 'warning', 'info'];

    protected function notify(string $message, string $type = 'success'): void
    {
        $message = trim($message);

        if ($message === '') {
            // An empty toast tells the user nothing and looks broken --
            // surfacing the programming error beats rendering it.
            throw new InvalidArgumentException('A toast must carry a message.');
        }

        if (! in_array($type, self::$notifyTypes, true)) {
            throw new InvalidArgumentException(
                "Unknown toast type '{$type}' -- use success, error, warning or info."
            );
        }

        $this->dispatch('notify', type: $type, message: $message);
    }
}
