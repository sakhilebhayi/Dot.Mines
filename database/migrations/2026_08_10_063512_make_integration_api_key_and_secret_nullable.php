<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * api_key/api_secret were NOT NULL with no default, but nothing in the app
 * reads them -- every real manufacturer service reads credentials from the
 * flexible `credentials` JSON column instead (see BaseManufacturerService's
 * constructor and IntegrationService::getServiceForIntegration()). Because
 * IntegrationManager::createIntegration() -- the only place that actually
 * inserts an Integration row from user input -- never set them, every single
 * "Add Integration" submission through the real UI, for every provider, has
 * always thrown a NOT NULL violation. It was silently swallowed by the
 * component's catch block and shown as a generic "Failed to create
 * integration. Please try again." toast, so this was never visible in
 * normal use. The model's own docblock already declared both nullable.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('api_key')->nullable()->change();
            $table->string('api_secret')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('api_key')->nullable(false)->change();
            $table->string('api_secret')->nullable(false)->change();
        });
    }
};
