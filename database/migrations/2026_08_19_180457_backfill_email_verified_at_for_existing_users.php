<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is being enforced for the first time (User now
     * implements MustVerifyEmail, so the 'verified' middleware on the main
     * route group actually gates access). Accounts created before
     * enforcement never received a verification email, so without this
     * backfill they would all be locked out of the app on deploy.
     * Grandfather them in; accounts registered after this deploy must
     * verify normally.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Irreversible by design: we cannot distinguish grandfathered
        // timestamps from organic verifications after the fact.
    }
};
