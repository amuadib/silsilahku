<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gelar_depan')->nullable()->after('name');
            $table->string('gelar_belakang')->nullable()->after('gelar_depan');
        });

        // Migrasi otomatis memindahkan gelar_depan (KH., H., dan Hj.) menjadi gelar_depan
        $users = DB::table('users')->select('id', 'name')->get();

        foreach ($users as $user) {
            if (empty($user->name)) {
                continue;
            }

            $gelar = null;
            $cleanName = null;

            if (preg_match('/^(KH\.|K\.H\.|KH)\s+(.+)$/i', $user->name, $m)) {
                $gelar = 'KH.';
                $cleanName = trim($m[2]);
            } elseif (preg_match('/^(Hj\.|Hj)\s+(.+)$/i', $user->name, $m)) {
                $gelar = 'Hj.';
                $cleanName = trim($m[2]);
            } elseif (preg_match('/^(H\.|H)\s+(.+)$/i', $user->name, $m)) {
                $gelar = 'H.';
                $cleanName = trim($m[2]);
            }

            if ($gelar !== null && !empty($cleanName)) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'gelar_depan' => $gelar,
                        'name' => $cleanName,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan gelar_depan dan gelar_belakang ke kolom name sebelum drop
        $users = DB::table('users')
            ->whereNotNull('gelar_depan')
            ->orWhereNotNull('gelar_belakang')
            ->select('id', 'name', 'gelar_depan', 'gelar_belakang')
            ->get();

        foreach ($users as $user) {
            $parts = array_filter([$user->gelar_depan, $user->name]);
            $revertedName = implode(' ', $parts);
            if (!empty($user->gelar_belakang)) {
                $revertedName .= ', ' . $user->gelar_belakang;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['name' => $revertedName]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gelar_depan', 'gelar_belakang']);
        });
    }
};

