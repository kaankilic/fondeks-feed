<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent audit columns stamped at signup. The three required consents
 * (terms of service, KVKK notice, 18+ age confirmation) are non-null the
 * moment the account exists; `marketing_consent_at` is optional and stays
 * null until the user opts in. Timestamps carry a timezone so revocations
 * and prior-version lookups line up with legal records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('terms_accepted_at')->nullable();
            $table->timestampTz('kvkk_acknowledged_at')->nullable();
            $table->timestampTz('age_confirmed_at')->nullable();
            $table->timestampTz('marketing_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_accepted_at',
                'kvkk_acknowledged_at',
                'age_confirmed_at',
                'marketing_consent_at',
            ]);
        });
    }
};
