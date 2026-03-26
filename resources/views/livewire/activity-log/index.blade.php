<?php

use App\Models\ActivityLog;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, \App\Traits\HasToast;

    public string $search = '';
    public string $action = '';
    public string $model = '';
    public ?int $detailId = null;
    public function mount(): void
    {
        $this->authorize('viewAny', ActivityLog::class);
    }


    /** Reset pagination on filter change */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedModel(): void
    {
        $this->resetPage();
    }

    /** Buka modal detail */
    public function showDetail(int $id): void
    {
        $this->detailId = $id;
        $this->js('$flux.modal("detail-modal").show()');
    }

    /** Tutup modal detail */
    public function closeDetail(): void
    {
        $this->js('$flux.modal("detail-modal").hide()');
        $this->detailId = null;
    }

    /** Hapus semua log (sesuai filter aktif) */
    public function clearLogs(): void
    {
        $query = ActivityLog::query();

        if ($this->action) {
            $query->where('action', $this->action);
        }
        if ($this->model) {
            $query->where('model_type', 'like', '%' . $this->model . '%');
        }

        $query->delete();

        $this->resetPage();
        $this->toastSuccess(__('Log berhasil dibersihkan.'));
    }

    /** Daftar model unik untuk filter dropdown */
    public function uniqueModels(): array
    {
        return ActivityLog::query()->select('model_type')->distinct()->pluck('model_type')->map(fn($m) => class_basename($m))->unique()->sort()->values()->toArray();
    }

    public function with(): array
    {
        $logs = ActivityLog::with('user')
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q) {
                    $q->where('model_id', 'like', '%' . $this->search . '%')->orWhereHas('user', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));
                }),
            )
            ->when($this->action, fn($q) => $q->where('action', $this->action))
            ->when($this->model, fn($q) => $q->where('model_type', 'like', '%' . $this->model . '%'))
            ->latest()
            ->paginate(20);

        $detail = $this->detailId ? ActivityLog::with('user')->find($this->detailId) : null;

        return [
            'logs' => $logs,
            'detail' => $detail,
            'uniqueModels' => $this->uniqueModels(),
        ];
    }
}; ?>

<section class="item-center mx-auto flex w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="w-full">
        <flux:main>
            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading size="xl" level="1">{{ __('Activity Log') }}</flux:heading>
                    <flux:subheading>{{ __('All data changes made by users.') }}</flux:subheading>
                </div>
                <flux:button variant="danger" icon="trash" wire:click="clearLogs"
                    wire:confirm="{{ __('Are you sure you want to delete the displayed logs?') }}">
                    {{ __('Clear Logs') }}
                </flux:button>
            </div>

            {{-- Filter --}}
            <div class="mb-4 flex flex-wrap gap-3">
                <flux:input wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search name user / ID record...') }}" icon="magnifying-glass"
                    class="min-w-48 flex-1" />

                <flux:select wire:model.live="action" class="w-44">
                    <option value="">{{ __('All Actions') }}</option>
                    <option value="created">{{ __('Created') }}</option>
                    <option value="updated">{{ __('Updated') }}</option>
                    <option value="deleted">{{ __('Deleted') }}</option>
                </flux:select>

                <flux:select wire:model.live="model" class="w-48">
                    <option value="">{{ __('All Models') }}</option>
                    @foreach ($uniqueModels as $m)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Tabel --}}
            <div class="w-full overflow-auto border">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 dark:bg-zinc-800">
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Time') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('User') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Model') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('ID Record') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Action') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Detail') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $emoji = match ($log->action) {
                                    'created' => '🟢',
                                    'updated' => '🟡',
                                    'deleted' => '🔴',
                                    default => '⚪',
                                };
                                $actionBg = match ($log->action) {
                                    'created' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    'updated'
                                        => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    'deleted' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    default => 'bg-zinc-100 text-zinc-600',
                                };
                            @endphp
                            <tr class="border-b hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                wire:key="log-{{ $log->id }}">
                                <td class="whitespace-nowrap px-4 py-2 text-zinc-500 dark:text-zinc-400">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-2 font-medium">
                                    {{ $log->user?->name ?? __('System') }}
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="rounded border border-zinc-300 px-1.5 py-0.5 text-xs dark:border-zinc-600">
                                        {{ class_basename($log->model_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ Str::limit($log->model_id, 12) }}
                                </td>
                                <td class="px-4 py-2">
                                    <span class="{{ $actionBg }} rounded px-2 py-0.5 text-xs font-medium">
                                        {{ $emoji }} {{ $log->actionLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($log->old_values || $log->new_values)
                                        <button wire:click="showDetail({{ $log->id }})"
                                            class="cursor-pointer text-xs text-blue-600 hover:underline dark:text-blue-400">
                                            {{ __('View') }}
                                        </button>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-zinc-400">
                                    {{ __('No Activity Log') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </flux:main>

        {{-- ======= MODAL DETAIL =======
             Modal selalu ada di DOM; dikontrol lewat $flux global (paling stabil). --}}
        <flux:modal name="detail-modal">
            @if ($detail)
                {{-- Header --}}
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Change Detail') }}</flux:heading>
                        <div class="mt-1 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            @php
                                $detailBg = match ($detail->action) {
                                    'created' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    'updated'
                                        => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    'deleted' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    default => 'bg-zinc-100 text-zinc-600',
                                };
                            @endphp
                            <span class="{{ $detailBg }} rounded px-2 py-0.5 text-xs font-medium">
                                {{ $detail->actionLabel() }}
                            </span>
                            <span>{{ class_basename($detail->model_type) }}
                                #{{ Str::limit($detail->model_id, 12) }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    {{-- Meta --}}
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('User') }}:</span>
                            <strong class="ml-1">{{ $detail->user?->name ?? __('System') }}</strong>
                        </div>
                        <div>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Time') }}:</span>
                            <strong class="ml-1">{{ $detail->created_at->format('d/m/Y H:i:s') }}</strong>
                        </div>
                        @if ($detail->ip_address)
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400">IP:</span>
                                <code class="ml-1 text-xs">{{ $detail->ip_address }}</code>
                            </div>
                        @endif
                    </div>

                    {{-- Old vs New --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {{-- Old Values --}}
                        <div>
                            <p class="mb-2 text-sm font-semibold text-red-500">{{ __('Before') }}</p>
                            @if ($detail->old_values)
                                <div
                                    class="divide-y divide-zinc-100 overflow-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                                    @foreach ($detail->old_values as $col => $val)
                                        <div class="flex gap-2 px-3 py-2 text-sm">
                                            <span
                                                class="w-28 shrink-0 truncate font-mono text-xs text-zinc-500 dark:text-zinc-400"
                                                title="{{ $col }}">{{ $col }}</span>
                                            <span
                                                class="break-all text-red-600 dark:text-red-400">{{ is_array($val) ? json_encode($val) : $val }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm italic text-zinc-400">{{ __('(No data before)') }}</p>
                            @endif
                        </div>

                        {{-- New Values --}}
                        <div>
                            <p class="mb-2 text-sm font-semibold text-green-500">{{ __('After') }}</p>
                            @if ($detail->new_values)
                                <div
                                    class="divide-y divide-zinc-100 overflow-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                                    @foreach ($detail->new_values as $col => $val)
                                        <div class="flex gap-2 px-3 py-2 text-sm">
                                            <span
                                                class="w-28 shrink-0 truncate font-mono text-xs text-zinc-500 dark:text-zinc-400"
                                                title="{{ $col }}">{{ $col }}</span>
                                            <span
                                                class="break-all text-green-600 dark:text-green-400">{{ is_array($val) ? json_encode($val) : $val }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm italic text-zinc-400">{{ __('(No data after)') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </flux:modal>
    @include('partials.toast-bridge')
    </div>
</section>
