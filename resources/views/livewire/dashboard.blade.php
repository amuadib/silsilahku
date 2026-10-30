<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $jenis_kelamin = '';
    public string $status = ''; // 0: Hidup, 1: Wafat

    /** Reset pagination on filter change */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedJenisKelamin(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $users = User::query()
            ->when(!Auth::user()->isAdmin(), fn($q) => $q->whereIn('id', Auth::user()->getRelatedIds()))
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%'))
            ->when($this->jenis_kelamin, fn($q) => $q->where('jenis_kelamin', $this->jenis_kelamin))
            ->when($this->status !== '', fn($q) => $q->where('wafat', $this->status))
            ->orderBy('name')
            ->paginate(20);

        return [
            'users' => $users,
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-7xl px-6 lg:px-8">
    <div class="w-full">
        {{-- Header --}}
        <div class="relative my-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl" level="1">{{ __('Individual List') }}</flux:heading>
                <flux:subheading size="lg" class="mt-1">{{ __('List of all individuals in the family tree') }}</flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button color="cyan" href="{{ route('pengguna.hubungan') }}" wire:navigate variant="outline" icon="arrows-right-left">
                    {{ __('Find Relationship') }}
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        {{-- Main Card --}}
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">

            {{-- Card Header with Gradient --}}
            <div class="border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 shadow-sm">
                        <svg class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Search & Filter') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Find individuals in the family tree') }}</p>
                    </div>
                </div>
            </div>

            {{-- Filter --}}
            <div class="border-b border-zinc-100 p-4 dark:border-zinc-800">
                <div class="flex flex-wrap gap-3">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name / email...') }}"
                        icon="magnifying-glass" class="min-w-48 flex-1" />

                    <flux:select wire:model.live="jenis_kelamin" class="w-44">
                        <option value="">{{ __('All Gender') }}</option>
                        <option value="1">{{ __('Male') }}</option>
                        <option value="2">{{ __('Female') }}</option>
                    </flux:select>

                    <flux:select wire:model.live="status" class="w-44">
                        <option value="">{{ __('All Status') }}</option>
                        <option value="0">{{ __('Alive') }}</option>
                        <option value="1">{{ __('Deceased') }}</option>
                    </flux:select>
                </div>
            </div>

            {{-- Tabel --}}
            <div class="w-full overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Gender') }}</th>
                            <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Address') }}</th>
                            <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Phone') }}</th>
                            <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($users as $u)
                            <tr class="hover:bg-blue-50/40 dark:hover:bg-zinc-800/50 transition-colors"
                                wire:key="user-{{ $u->id }}">
                                <td class="px-4 py-3 font-medium">
                                    <div class="flex items-center gap-3">
                                        @if ($u->foto)
                                            <img src="{{ asset('storage/' . $u->foto) }}" alt="{{ $u->name }}"
                                                class="size-9 rounded-full border border-zinc-200 bg-zinc-100 object-cover dark:border-zinc-700" />
                                        @else
                                            <div class="flex size-9 items-center justify-center rounded-full dark:border-zinc-700"
                                                style="{{ $u->jenis_kelamin == '1' ? 'background-color:#7ea4b3' : 'background-color:#ce969b' }}">
                                                <span class="text-xs font-bold text-white">{{ $u->initials() }}</span>
                                            </div>
                                        @endif
                                        <div class="flex flex-col">
                                            <a href="{{ route('pengguna.show', $u->id) }}" wire:navigate
                                                class="font-semibold cursor-pointer hover:underline hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ $u->name }}
                                                @if ($u->wafat)
                                                    <span class="text-zinc-400 font-normal">(Alm)</span>
                                                @endif
                                            </a>
                                            <span class="text-xs text-zinc-400">{{ $u->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm"
                                        color="{{ $u->jenis_kelamin == '1' ? 'blue' : 'pink' }}">
                                        {{ $u->jenis_kelamin == '1' ? 'L' : 'P' }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 text-sm">
                                    @if($u->alamat)
                                        {{ $u->alamat->alamat ?? '' }}
                                        {{ $u->alamat->nama_desa ?? '' }}
                                        {{ $u->alamat->nama_kecamatan ?? '' }}
                                        {{ $u->alamat->nama_kabupaten ?? '' }}
                                        {{ $u->alamat->nama_provinsi ?? '' }}
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                    {{ $u->hp ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('update', $u)
                                            <flux:button size="sm" variant="primary" color="yellow" icon="pencil-square"
                                                href="{{ route('pengguna.edit', $u->id) }}" wire:navigate />
                                        @endcan
                        <flux:button size="sm" variant="primary" color="blue" icon="share"
                                            href="{{ route('pengguna.bagan', $u->id) }}" wire:navigate
                                            title="{{ __('View Tree') }}" />
                                        <flux:button size="sm" variant="primary" color="green" icon="list-bullet"
                                            href="{{ route('pengguna.daftar-v2', $u->id) }}" wire:navigate
                                            title="{{ __('List of Family members') }}" />
                                        @can('delete', $u)
                                            <flux:button size="sm" variant="primary" color="red" icon="trash" class="cursor-pointer"
                                                wire:click="delete({{ $u->id }})" wire:confirm="{{ __('Are you sure you want to delete this user?') }}"/>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="h-8 w-8 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                        </svg>
                                        <p class="text-zinc-400 dark:text-zinc-500">{{ __('No data found.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="border-t border-zinc-100 p-4 dark:border-zinc-800">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</section>
