<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait LogsActivity
 *
 * Tambahkan trait ini ke model manapun agar setiap perubahan
 * (created, updated, deleted) otomatis tercatat di tabel activity_logs.
 *
 * Gunakan property $logExclude pada model untuk mengecualikan kolom tertentu:
 *   protected array $logExclude = ['updated_at'];
 *
 * Gunakan property $logOnly untuk hanya mencatat kolom tertentu saja:
 *   protected array $logOnly = ['name', 'email'];
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            static::recordActivity($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $old = $model->getOriginal();
            $new = $model->getChanges();

            // Hilangkan kolom yang tidak relevan
            $exclude = array_merge(
                ['updated_at', 'created_at', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
                $model->logExclude ?? []
            );

            foreach ($exclude as $col) {
                unset($old[$col], $new[$col]);
            }

            // Jika ada kolom $logOnly, filter hanya kolom tersebut
            if (! empty($model->logOnly ?? [])) {
                $only = $model->logOnly;
                $old = array_intersect_key($old, array_flip($only));
                $new = array_intersect_key($new, array_flip($only));
            }

            // Sinkronkan old dengan perubahan yang ada
            $oldFiltered = array_intersect_key($old, $new);

            if (! empty($new)) {
                static::recordActivity($model, 'updated', $oldFiltered, $new);
            }
        });

        static::deleted(function ($model) {
            static::recordActivity($model, 'deleted', $model->getOriginal(), []);
        });
    }

    protected static function recordActivity($model, string $action, array $old, array $new): void
    {
        try {
            ActivityLog::create([
                'user_id'    => Auth::id(),
                'model_type' => get_class($model),
                'model_id'   => (string) $model->getKey(),
                'action'     => $action,
                'old_values' => empty($old) ? null : $old,
                'new_values' => empty($new) ? null : $new,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Jangan hentikan proses utama jika logging gagal
            \Illuminate\Support\Facades\Log::warning('ActivityLog gagal: ' . $e->getMessage());
        }
    }
}
