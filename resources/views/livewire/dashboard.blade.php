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

<section class="item-center mx-auto flex w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="w-full">
        <flux:main>
            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading size="xl" level="1">{{ __('Individual List') }}</flux:heading>
                    <flux:subheading>{{ __('List of all individuals in the family tree') }}</flux:subheading>
                </div>
                {{-- <flux:button variant="primary" icon="plus" href="{{ route('profil') }}" wire:navigate>
                    {{ __('Add User') }}
                </flux:button> --}}
            </div>

            {{-- Filter --}}
            <div class="mb-4 flex flex-wrap gap-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name / email...') }}"
                    icon="magnifying-glass" class="min-w-48 flex-1" />

                <flux:select wire:model.live="jenis_kelamin" class="w-44">
                    <flux:select.option value="">{{ __('All Gender') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Male') }}</flux:select.option>
                    <flux:select.option value="2">{{ __('Female') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="status" class="w-44">
                    <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Alive') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Deceased') }}</flux:select.option>
                </flux:select>
            </div>

            {{-- Tabel --}}
            <div class="w-full overflow-auto border">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 dark:bg-zinc-800">
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Name') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Gender') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">
                                {{ __('Address') }}</th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Phone') }}
                            </th>
                            <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $u)
                            <tr class="border-b hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                wire:key="user-{{ $u->id }}">
                                <td class="px-4 py-2 font-medium">
                                    <div class="flex items-center gap-2">
                                        @if ($u->foto)
                                            <img src="{{ asset('storage/' . $u->foto) }}" alt="{{ $u->name }}"
                                                class="size-8 rounded-full border bg-zinc-100 object-cover" />
                                        @else
                                            <div
                                                class="flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                                                <span
                                                    class="text-xs font-bold text-zinc-500">{{ $u->initials() }}</span>
                                            </div>
                                        @endif
                                        <div class="flex flex-col">
                                            <span class="font-semibold">
                                                {{ $u->name }}
                                                @if ($u->wafat)
                                                    <span class="text-xs">(Alm)</span>
                                                @endif
                                            </span>
                                            <span class="text-xs text-zinc-400">{{ $u->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <flux:badge size="sm"
                                        color="{{ $u->jenis_kelamin == '1' ? 'blue' : 'pink' }}">
                                        {{ $u->jenis_kelamin == '1' ? 'L' : 'P' }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-2 text-zinc-500 dark:text-zinc-400">
                                    {{ $u->alamat ?? '-' }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ $u->hp ?? '-' }}
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <flux:button size="sm" variant="ghost" icon="eye"
                                            href="{{ route('pengguna.show', $u->id) }}" wire:navigate />
                                        @can('update', $u)
                                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                                href="{{ route('pengguna.edit', $u->id) }}" wire:navigate />
                                        @endcan
                                        <flux:button size="sm" variant="ghost" icon="share"
                                            href="{{ route('pengguna.pohon', $u->id) }}" wire:navigate
                                            title="{{ __('View Tree') }}" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-zinc-400">
                                    {{ __('No data found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </flux:main>
    </div>
</section>
