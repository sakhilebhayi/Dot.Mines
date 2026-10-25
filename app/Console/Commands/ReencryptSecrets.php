<?php

namespace App\Console\Commands;

use App\Support\ApiPayload;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Finishes an APP_KEY rotation. After the key is replaced and the old one is
 * parked in APP_PREVIOUS_KEYS, everything stored encrypted still reads (the
 * framework tries the previous keys) but is still protected by the OLD key.
 * This re-writes each encrypted column under the current key, and --check
 * proves nothing is left that only the old key can read, which is the signal
 * that APP_PREVIOUS_KEYS can be removed.
 *
 * Works on the raw column values so it does not depend on the model casts.
 * Prints counts only, never a value.
 */
class ReencryptSecrets extends Command
{
    /** @var list<array{0: string, 1: string}> table => encrypted column */
    private const COLUMNS = [
        ['integrations', 'credentials'],
        ['webhook_endpoints', 'secret'],
        ['users', 'two_factor_secret'],
        ['users', 'two_factor_recovery_codes'],
    ];

    protected $signature = 'secrets:reencrypt {--check : Only report values that the current key alone cannot read; change nothing}';

    protected $description = 'Re-encrypt stored secrets under the current APP_KEY (run after rotating the key)';

    public function handle(): int
    {
        $check = $this->option('check') === true;
        $current = $this->currentKeyOnly();
        $unreadable = 0;

        foreach (self::COLUMNS as [$table, $column]) {
            $total = 0;
            $rewritten = 0;
            $stuck = 0;

            DB::table($table)->whereNotNull($column)->orderBy('id')->select('id', $column)->each(
                function (object $row) use ($table, $column, $check, $current, &$total, &$rewritten, &$stuck): void {
                    $raw = ApiPayload::str($row->{$column});
                    $total++;

                    if ($check) {
                        try {
                            $current->decryptString($raw);
                        } catch (DecryptException) {
                            $stuck++;
                        }

                        return;
                    }

                    try {
                        $plain = Crypt::decryptString($raw);
                    } catch (DecryptException) {
                        $stuck++;

                        return;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => Crypt::encryptString($plain)]);
                    $rewritten++;
                },
            );

            $unreadable += $stuck;
            $this->line($table.'.'.$column.': '.$total.' stored, '.($check ? '' : $rewritten.' re-encrypted, ').$stuck.' '.($check ? 'not readable with the current key alone' : 'unreadable (left untouched)'));
        }

        if ($unreadable > 0) {
            $this->error($unreadable.' value(s) could not be handled. Keep APP_PREVIOUS_KEYS until this is 0.');

            return self::FAILURE;
        }

        $this->info($check ? 'Safe to remove APP_PREVIOUS_KEYS.' : 'Done. Run with --check, then remove APP_PREVIOUS_KEYS.');

        return self::SUCCESS;
    }

    private function currentKeyOnly(): Encrypter
    {
        $key = ApiPayload::str(config('app.key'));
        $bytes = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        return new Encrypter(is_string($bytes) ? $bytes : '', ApiPayload::str(config('app.cipher')));
    }
}
