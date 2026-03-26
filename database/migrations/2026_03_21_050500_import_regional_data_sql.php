<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $files = [
            'nk_provinsi.sql',
            'nk_kabupaten_kota.sql',
            'nk_kecamatan.sql',
            'nk_desa_kelurahan.sql',
        ];

        foreach ($files as $file) {
            $path = database_path('SQL/' . $file);
            if (File::exists($path)) {
                DB::unprepared(File::get($path));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS nk_desa_kelurahan');
        DB::statement('DROP TABLE IF EXISTS nk_kecamatan');
        DB::statement('DROP TABLE IF EXISTS nk_kabupaten_kota');
        DB::statement('DROP TABLE IF EXISTS nk_provinsi');
    }
};
