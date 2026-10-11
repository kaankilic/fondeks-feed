<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fund's total expense ratio (Fon Toplam Gider Kesinti Oranı) as an
     * annual percentage — TEFAS's `fonTopGiderKesoran` from the catalogue feed,
     * e.g. 2.19 for "2,19%". This is the azami (ceiling) rate the fund's
     * internal regulation permits, which is the figure TEFAS publishes; it is
     * distinct from and generally above `management_fee`. Null when TEFAS
     * reports none.
     */
    public function up(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->decimal('ter', 6, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->dropColumn('ter');
        });
    }
};
