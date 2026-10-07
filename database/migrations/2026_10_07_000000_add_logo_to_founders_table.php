<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Founder (kurucu) logos are downloaded, squared and stored under
     * public/founder-logos. This column holds the public-relative path of a
     * founder's stored logo (e.g. "founder-logos/ak_portfoy.png"), or null when
     * we have no logo for them — the UI then falls back to the initials chip.
     */
    public function up(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            $table->string('logo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
