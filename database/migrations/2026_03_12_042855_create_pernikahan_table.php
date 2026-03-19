<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pernikahan', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('suami_id');
            $table->uuid('istri_id');

            $table->date('tanggal_menikah')->nullable();
            $table->date('tanggal_cerai')->nullable();
            $table->uuid('manager_id')->nullable();
            $table->timestamps();

            $table->unique(['suami_id', 'istri_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pernikahan');
    }
};
