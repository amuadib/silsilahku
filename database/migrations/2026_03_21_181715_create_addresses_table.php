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
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alamat')->nullable();
            $table->string('kode_desa',13)->nullable();
            $table->string('nama_desa',100)->nullable();
            $table->string('nama_kecamatan',100)->nullable();
            $table->string('nama_kabupaten',100)->nullable();
            $table->string('nama_provinsi',100)->nullable();
            $table->string('kode_pos',10)->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->timestamps();

            $table->foreign('kode_desa')
                ->references('kode')
                ->on('nk_desa_kelurahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
