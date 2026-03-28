<div>
    @if ($activeForm === 'anak')
        <div class="px-4 py-2">
            @if (!$userExist)
                <flux:button wire:click="$set('userExist', true)" class="mb-2 cursor-pointer" size="sm" color="green"
                    variant="primary">
                    {{ __('Search existing name') }}</flux:button>
            @else
                <flux:button wire:click="$set('userExist', false)" class="mb-2 cursor-pointer" size="sm" color="blue"
                    variant="primary">
                    {{ __('Add new name') }}</flux:button>
            @endif
            @if ($userExist)
                <div class="mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ __('Search Name') }}</label>
                    <x-searchable-select wire:model="userFromSelection">
                        <option value="">{{ __('Select from list') }}</option>
                        @foreach ($userWithoutParentList as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </x-searchable-select>
                </div>
            @else
                <div class="mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ __('Child Name') }}</label>
                    <flux:input wire:model="name" type="text" placeholder="{{ __('Enter Child Name') }}" />
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ __('Gender') }}</label>
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input type="radio" wire:model="jenis_kelamin" value="1" class="accent-blue-600">
                        {{ __('Male') }}
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input type="radio" wire:model="jenis_kelamin" value="2" class="accent-pink-500">
                        {{ __('Female') }}
                    </label>
                </div>
            @endif
            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ __('Couple') }}</label>
                <x-searchable-select wire:model="ortu_id">
                    <option value="">{{ __('Select from Couple list') }}</option>
                    @foreach ($userList as $id => $pasangan)
                        <option value="{{ $id }}">{{ $pasangan }}</option>
                    @endforeach
                </x-searchable-select>
            </div>
            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ __('Birth order') }}</label>
                <flux:input wire:model="anak_ke" type="number" placeholder="{{ __('Enter birth order') }}" />
            </div>
        @elseif ($activeForm === 'orangtua')
            <div class="mb-2">
                <x-searchable-select wire:model="ortu_id">
                    <option value="">{{ __('Select from Couple list') }}</option>
                    @foreach ($userList as $id => $pasangan)
                        <option value="{{ $id }}">{{ $pasangan }}</option>
                    @endforeach
                </x-searchable-select>
            </div>
        @else
            @if ($activeForm === 'ayah' || $activeForm === 'suami')
                <x-searchable-select wire:model="userFromSelection">
                    <option value="">{{ __('Select from Male list') }}</option>
                    @foreach ($userList as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </x-searchable-select>
            @elseif ($activeForm === 'ibu' || $activeForm === 'istri')
                <x-searchable-select wire:model="userFromSelection">
                    <option value="">{{ __('Select from Female list') }}</option>
                    @foreach ($userList as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </x-searchable-select>
            @endif
            <flux:input wire:model="userFromInput" type="text" placeholder="{{ __('Enter new Name') }}" />
    @endif
    <flux:button wire:click="save" class="cursor-pointer" color="blue" variant="primary">{{ __('Save') }}
    </flux:button>
    <flux:button wire:click="$set('activeForm', null)" class="cursor-pointer" color="gray" variant="filled">
        {{ __('Cancel') }}
    </flux:button>
</div>
