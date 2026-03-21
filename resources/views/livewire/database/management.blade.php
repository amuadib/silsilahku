<?php

use App\Models\User;
use App\Models\Pernikahan;
use App\Models\ActivityLog;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Traits\HasToast;

new class extends Component {
    use WithFileUploads, HasToast;

    public $file;
    public $includeLogs = true;

    public function mount()
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
    }

    public function exportJson()
    {
        $backupService = new \App\Services\BackupService();
        $data = $backupService->collectData($this->includeLogs);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'silsilah_backup_' . date('Y-m-d_H-i-s') . '.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function exportZip()
    {
        try {
            $backupService = new \App\Services\BackupService();
            $zipPath = $backupService->createZipBackup($this->includeLogs);
            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function import()
    {
        $this->validate([
            'file' => 'required|file|mimes:json,zip|max:51200', // 50MB max
        ]);

        $path = $this->file->getRealPath();
        $extension = $this->file->getClientOriginalExtension();

        try {
            DB::beginTransaction();

            if ($extension === 'json') {
                $json = file_get_contents($path);
                $data = json_decode($json, true);
                $this->processImport($data);
            } else {
                $zip = new \ZipArchive();
                if ($zip->open($path) === true) {
                    $json = $zip->getFromName('data.json');
                    $data = json_decode($json, true);

                    // Extract photos
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (str_starts_with($filename, 'storage/foto/')) {
                            $targetPath = str_replace('storage/', '', $filename);
                            Storage::disk('public')->put($targetPath, $zip->getFromIndex($i));
                        }
                    }

                    $zip->close();
                    $this->processImport($data);
                }
            }

            DB::commit();

            ActivityLog::create([
                'user_id' => User::where('id', Auth::id())->exists() ? Auth::id() : null,
                'action' => 'imported_database',
                'model_type' => 'Database',
                'model_id' => 'all',
                'new_values' => ['filename' => $this->file->getClientOriginalName()],
                'ip_address' => request()->ip(),
            ]);

            $this->toastSuccess(__('Database successfully imported.'));
            $this->reset('file');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->toastError(__('Import failed: ') . $e->getMessage());
        }
    }

    private function processImport($data)
    {
        if (!isset($data['users']) || !isset($data['pernikahans'])) {
            throw new \Exception('Invalid backup file format.');
        }

        $driver = DB::getDriverName();

        // Disable foreign key checks
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::table('users')->delete();
            DB::table('pernikahan')->delete();
            if ($this->includeLogs && isset($data['activity_logs'])) {
                DB::table('activity_logs')->delete();
            }

            // Insert Users
            foreach (array_chunk($data['users'], 50) as $chunk) {
                DB::table('users')->insert($chunk);
            }

            // Insert Pernikahans
            foreach (array_chunk($data['pernikahans'], 50) as $chunk) {
                DB::table('pernikahan')->insert($chunk);
            }

            // Insert Logs if applicable
            if ($this->includeLogs && isset($data['activity_logs'])) {
                foreach (array_chunk($data['activity_logs'], 50) as $chunk) {
                    DB::table('activity_logs')->insert($chunk);
                }
            }
        } finally {
            // Enable foreign key checks
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
}; ?>

<section class="item-center mx-auto flex w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="w-full">
        <flux:main>
            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading size="xl" level="1">{{ __('Database Management') }}</flux:heading>
                    <flux:subheading>{{ __('Backup and Restore your family tree data.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Export Card --}}
                <flux:card>
                    <flux:heading size="lg" icon="arrow-down-tray" class="mb-4">{{ __('Export Data') }}
                    </flux:heading>
                    <p class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Download a copy of the database. JSON contains records only, while ZIP includes profile photos.') }}
                    </p>

                    <div class="mb-6">
                        <flux:checkbox wire:model="includeLogs" label="{{ __('Include Activity Logs') }}" />
                    </div>

                    <div class="flex gap-2">
                        <flux:button wire:click="exportJson" variant="primary" icon="document-text"
                            class="flex-1 cursor-pointer">
                            {{ __('Export JSON') }}
                        </flux:button>
                        <flux:button wire:click="exportZip" variant="filled" icon="archive-box"
                            class="flex-1 cursor-pointer">
                            {{ __('Export ZIP') }}
                        </flux:button>
                    </div>
                </flux:card>

                {{-- Import Card --}}
                <flux:card>
                    <flux:heading size="lg" icon="arrow-up-tray" class="mb-4">{{ __('Import Data') }}
                    </flux:heading>
                    <p class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Upload a previously exported JSON or ZIP file to restore the database.') }}
                    </p>

                    <form wire:submit="import" class="space-y-4">
                        <flux:input type="file" wire:model="file" accept=".json,.zip" />

                        @error('file')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror

                        <div
                            class="rounded border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20">
                            <p class="flex items-center gap-2 text-xs font-semibold text-red-700 dark:text-red-400">
                                <flux:icon.exclamation-triangle class="size-4" />
                                {{ __('WARNING: This will replace all current data!') }}
                            </p>
                        </div>

                        <flux:button type="submit" variant="danger" icon="cloud-arrow-up" class="w-full cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>{{ __('Restore Database') }}</span>
                            <span wire:loading>{{ __('Processing...') }}</span>
                        </flux:button>
                    </form>
                </flux:card>
            </div>
        </flux:main>
    @include('partials.toast-bridge')
    </div>
</section>
