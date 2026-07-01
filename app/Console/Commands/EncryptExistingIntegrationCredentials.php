<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptExistingIntegrationCredentials extends Command
{
    protected $signature = 'integration:encrypt-credentials
                            {--dry-run : Preview rows that would be migrated without writing}';

    protected $description = 'One-time migration: encrypt any plaintext JSON in the integrations.credentials column';

    public function handle(): int
    {
        $rows = DB::table('integrations')
            ->whereNotNull('credentials')
            ->get(['id', 'credentials']);

        if ($rows->isEmpty()) {
            $this->info('No integrations with credentials found — nothing to do.');

            return self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            // Detect whether the value is already encrypted (starts with 'eyJ')
            // Laravel encryption produces base64-encoded JSON payloads.
            $raw = $row->credentials;

            $isAlreadyEncrypted = false;
            try {
                Crypt::decryptString($raw);
                $isAlreadyEncrypted = true;
            } catch (\Throwable) {
                // Not yet encrypted — proceed
            }

            if ($isAlreadyEncrypted) {
                $skipped++;

                continue;
            }

            // Attempt to parse as JSON
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $this->warn("Row id={$row->id}: credentials is not valid JSON — skipping.");
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] Would encrypt id={$row->id} keys=".implode(',', array_keys($decoded)));
                $migrated++;

                continue;
            }

            // Re-save through Eloquent so the encrypted:array cast applies
            DB::table('integrations')
                ->where('id', $row->id)
                ->update(['credentials' => Crypt::encryptString(json_encode($decoded, JSON_THROW_ON_ERROR))]);

            $migrated++;
        }

        $this->info("Done. Encrypted: {$migrated} | Already encrypted / skipped: {$skipped}");

        return self::SUCCESS;
    }
}
