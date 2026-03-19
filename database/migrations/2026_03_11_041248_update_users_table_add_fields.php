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
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('jenis_kelamin')->nullable();
            $table->uuid('ayah_id')->nullable();
            $table->uuid('ibu_id')->nullable();
            $table->uuid('ortu_id')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->date('tanggal_wafat')->nullable();
            $table->unsignedTinyInteger('anak_ke')->nullable();
            $table->text('alamat')->nullable();
            $table->text('hp')->nullable();
            $table->text('foto')->nullable();
            $table->uuid('manager_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('jenis_kelamin');
            $table->dropColumn('ayah_id');
            $table->dropColumn('ibu_id');
            $table->dropColumn('ortu_id');
            $table->dropColumn('tanggal_lahir');
            $table->dropColumn('tanggal_wafat');
            $table->dropColumn('anak_ke');
            $table->dropColumn('alamat');
            $table->dropColumn('hp');
            $table->dropColumn('foto');
            $table->dropColumn('manager_id');
        });
    }
};
