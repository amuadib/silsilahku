<?php

namespace App\Services;

use App\Models\User;
use App\Models\Pernikahan;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BackupService
{
    /**
     * Collect all relevant data for backup.
     */
    public function collectData(bool $includeLogs = true): array
    {
        return [
            'users' => User::all()->makeVisible(['password'])->toArray(),
            'pernikahans' => Pernikahan::all()->toArray(),
            'activity_logs' => $includeLogs ? ActivityLog::all()->toArray() : [],
            'exported_at' => now()->toDateTimeString(),
            'version' => '1.0',
        ];
    }

    /**
     * Create a ZIP backup file.
     * Returns the absolute path to the generated ZIP.
     */
    public function createZipBackup(bool $includeLogs = true): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \Exception('ZipArchive extension is not enabled.');
        }

        $data = $this->collectData($includeLogs);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        $filename = 'silsilah_backup_' . date('Y-m-d_H-i-s') . '.zip';
        $directory = storage_path('app/public/backups');
        
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $zipPath = $directory . '/' . $filename;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            $zip->addFromString('data.json', $json);
            
            // Add photos
            $files = Storage::disk('public')->allFiles('foto');
            foreach ($files as $file) {
                $absolutePath = storage_path('app/public/' . $file);
                if (file_exists($absolutePath)) {
                    $zip->addFile($absolutePath, 'storage/' . $file);
                }
            }
            
            $zip->close();
        } else {
            throw new \Exception('Could not create ZIP file.');
        }

        return $zipPath;
    }

    /**
     * Run the automated daily backup.
     */
    public function runAutoBackup(): void
    {
        $today = now()->toDateString();
        $lastBackup = Cache::get('last_auto_backup_date');

        if ($lastBackup === $today) {
            return;
        }

        try {
            $this->createZipBackup(true);
            Cache::put('last_auto_backup_date', $today, now()->addDays(2));
            
            // Clean old backups (keep last 7 days)
            $this->cleanOldBackups();
            
        } catch (\Exception $e) {
            \Log::error('Auto-backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove backups older than 7 days.
     */
    private function cleanOldBackups(): void
    {
        $files = Storage::disk('public')->files('backups');
        $now = time();
        $maxAge = 7 * 24 * 60 * 60; // 7 days

        foreach ($files as $file) {
            if (Storage::disk('public')->lastModified($file) < ($now - $maxAge)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
