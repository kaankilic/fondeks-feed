<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Turkish consumer price index (TÜFE) series, one row per published
     * month, sourced from TCMB's EVDS service. `value` is the raw index level
     * (e.g. 2003=100 base), not a percentage: real fund returns are derived by
     * dividing the index at the end of a window by the index at its start. The
     * date is the first day of the index month, matching EVDS's monthly stamps.
     */
    public function up(): void
    {
        Schema::create('cpi', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->decimal('value', 14, 4);
            $table->timestamp('ingested_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpi');
    }
};
