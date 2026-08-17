<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * integrations.credentials holds raw third-party API secrets (OAuth client
 * secrets, passwords, tokens). It was a plain `json` column -- stored in
 * cleartext at rest -- despite the Integration Manager UI's own copy
 * claiming "Your credentials are encrypted and stored securely." That claim
 * only became true here. A native json/jsonb column rejects the ciphertext
 * Laravel's `encrypted:json` cast produces (it isn't valid JSON), so the
 * column type has to move to text first.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->changeColumnType('text', 'json');

        DB::table('integrations')
            ->select('id', 'credentials')
            ->whereNotNull('credentials')
            ->get()
            ->each(function (object $row): void {
                if ($this->isAlreadyEncrypted($row->credentials)) {
                    return;
                }

                DB::table('integrations')
                    ->where('id', $row->id)
                    ->update(['credentials' => Crypt::encryptString($row->credentials)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('integrations')
            ->select('id', 'credentials')
            ->whereNotNull('credentials')
            ->get()
            ->each(function (object $row): void {
                try {
                    $plaintext = Crypt::decryptString($row->credentials);
                } catch (DecryptException) {
                    return; // Already plaintext (or unreadable) -- leave as-is.
                }

                DB::table('integrations')
                    ->where('id', $row->id)
                    ->update(['credentials' => $plaintext]);
            });

        $this->changeColumnType('json', 'text');
    }

    /**
     * A value only decrypts successfully if it was produced by Crypt::encrypt*()
     * -- Laravel's payload is a specific base64-wrapped JSON envelope
     * (iv/mac/value/tag), so this is a safe, idempotent "already migrated?"
     * check rather than a guess.
     */
    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    private function changeColumnType(string $to, string $from): void
    {
        $driver = DB::getDriverName();

        // Postgres/MySQL's native json/jsonb column types validate their
        // contents as JSON on write and would reject encrypted ciphertext
        // (or, on the way back down, reject nothing since text accepts
        // anything -- the USING clause is what does the real conversion).
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE integrations ALTER COLUMN credentials TYPE {$to} USING credentials::{$to}");

            return;
        }

        if ($driver === 'mysql') {
            $sqlType = $to === 'text' ? 'TEXT' : 'JSON';
            DB::statement("ALTER TABLE integrations MODIFY credentials {$sqlType} NULL");

            return;
        }

        // SQLite has no real json type -- a column declared `json` already
        // stores under TEXT affinity, so this is a formality that keeps the
        // schema honest without needing driver-specific SQL.
        Schema::table('integrations', function (Blueprint $table) use ($to): void {
            $to === 'text' ? $table->text('credentials')->nullable()->change() : $table->json('credentials')->nullable()->change();
        });
    }
};
