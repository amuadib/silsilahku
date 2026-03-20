<section class="item-center mx-auto flex w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="w-full">
        <div class="relative my-6 flex w-full justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
                <flux:subheading size="lg">{{ __('Profile') }}</flux:subheading>
            </div>
            <div>
                <a href="{{ route('pengguna.pohon', $user->id) }}">
                    <flux:button color="zinc" class="cursor-pointer" variant="outline">{{ __('Family Tree') }}
                    </flux:button>
                </a>
                @can('update', $user)
                    <a href="{{ route('pengguna.edit', $user->id) }}">
                        <flux:button color="yellow" class="cursor-pointer" variant="primary">{{ __('Edit') }}
                        </flux:button>
                    </a>
                @endcan
            </div>
        </div>
        <flux:separator variant="subtle" />
        <flux:heading class="sr-only">Informasi Pribadi</flux:heading>
        <div class="mt-4 flex items-start gap-5 max-md:flex-col">
            <div class="flex-1 self-stretch max-md:pt-6">
                <div class="w-full border">
                    <flux:heading size="xl" level="1" class="border-b p-4">{{ __('Profile') }}
                    </flux:heading>

                    <table class="w-full text-left">
                        <tr>
                            <td colspan="2" class="p-4">
                                @if ($user->foto)
                                    <img src="{{ asset('storage/' . $user->foto) }}" alt="Foto {{ $user->name }}"
                                        class="mx-auto w-32 object-cover">
                                @elseif ($user->jenis_kelamin == '1')
                                    <img src="{{ asset('img/avatar-m.jpg') }}" alt="Foto {{ $user->name }}"
                                        class="mx-auto w-32 object-cover">
                                @else
                                    <img src="{{ asset('img/avatar-f.jpg') }}" alt="Foto {{ $user->name }}"
                                        class="mx-auto w-32 object-cover">
                                @endif
                            </td>
                        </tr>
                        <tr class="border-b">
                            <th class="w-40 px-4 py-2 text-gray-500">{{ __('Name') }}</th>
                            <td class="py-2">{{ $user->name }} {{ $user->wafat == true ? '(Alm)' : '' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Gender') }}</th>
                            <td class="py-2">{{ $user->jenis_kelamin == '1' ? 'Laki-laki' : 'Perempuan' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Birthday') }}</th>
                            <td class="py-2">
                                @if ($user->tanggal_lahir)
                                    {{ $user->tanggal_lahir->format('d M Y') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Birth order') }}</th>
                            <td class="py-2">{{ $user->anak_ke ?? '' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Age') }}</th>
                            <td class="py-2">
                                @if ($user->tanggal_lahir)
                                    {{ $user->tanggal_lahir->diffForHumans(null, true, true) }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Email') }}</th>
                            <td class="py-2">{{ $user->email ?? '' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Phone') }}</th>
                            <td class="py-2">{{ $user->hp ?? '' }}</td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-gray-500">{{ __('Address') }}</th>
                            <td class="py-2">{{ $user->alamat ?? '' }}</td>
                        </tr>

                        @if ($user->wafat)
                            <tr class="border-b">
                                <th class="px-4 py-2 text-gray-500">{{ __('Deathday') }}</th>
                                <td class="py-2">
                                    @if ($user->tanggal_wafat)
                                        {{ $user->tanggal_wafat->format('d M Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endif
                        @can('delete', $user)
                            <tr>
                                <td colspan="2" class="py-2 text-center px-4">
                                    <flux:button color="red" class="cursor-pointer w-full" variant="primary"
                                        wire:click="delete('{{ $user->id }}')"
                                        wire:confirm="{{ __('Are you sure?') }}">{{ __('Delete') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endcan
                    </table>
                </div>
            </div>
            <flux:separator class="md:hidden" />

            <div class="flex-2 self-stretch max-md:pt-6">
                <div class="w-full border">
                    <flux:heading size="xl" level="1" class="border-b p-4">{{ __('Family') }}
                    </flux:heading>
                    <table class="w-full text-left">
                        <tr class="border-b">
                            <th class="w-40 px-4 text-gray-500">{{ __('Father') }}</th>
                            <td class="py-2">
                                @if ($user->ayah)
                                    <a href="{{ route('pengguna.show', $user->ayah->id) }}"
                                        class="text-blue-600 hover:underline">
                                        {{ $user->ayah->name }} {{ $user->ayah->wafat == true ? '(Alm)' : '' }}
                                    </a>
                                @else
                                    -
                                @endif
                                @if ($activeForm === 'ayah')
                                    @include('livewire.pengguna.form', [
                                        'userList' => $userLakiLaki,
                                    ])
                                @else
                                    @can('update', $user)
                                        <div class="float-right text-sm">
                                            <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                wire:click="setHubungan('ayah')">Set
                                                {{ __('Father') }}</button>
                                        </div>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 text-gray-500">{{ __('Mother') }}</th>
                            <td class="py-2">
                                @if ($user->ibu)
                                    <a href="{{ route('pengguna.show', $user->ibu->id) }}"
                                        class="text-blue-600 hover:underline">
                                        {{ $user->ibu->name }} {{ $user->ibu->wafat == true ? '(Alm)' : '' }}
                                    </a>
                                @else
                                    -
                                @endif
                                @if ($activeForm === 'ibu')
                                    @include('livewire.pengguna.form', [
                                        'userList' => $userPerempuan,
                                    ])
                                @else
                                    @can('update', $user)
                                        <div class="float-right text-sm">
                                            <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                wire:click="setHubungan('ibu')">Set
                                                {{ __('Mother') }}</button>
                                        </div>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                        <tr class="border-b">
                            <th class="px-4 text-gray-500">{{ __('Parent') }}</th>
                            <td class="py-2">
                                @if ($user->orangTua)
                                    <a href="{{ route('pengguna.show', $user->orangTua->suami->id) }}"
                                        class="text-blue-600 hover:underline">
                                        {{ $user->orangTua->suami->name }}
                                        {{ $user->orangTua->suami->wafat == true ? '(Alm)' : '' }}
                                    </a> -
                                    <a href="{{ route('pengguna.show', $user->orangTua->istri->id) }}"
                                        class="text-blue-600 hover:underline">
                                        {{ $user->orangTua->istri->name }}
                                        {{ $user->orangTua->istri->wafat == true ? '(Alm)' : '' }}
                                    </a>
                                @else
                                    -
                                @endif
                                @if ($activeForm === 'orangtua')
                                    @include('livewire.pengguna.form', ['userList' => $pasanganPernikahan])
                                @else
                                    @can('update', $user)
                                        <div class="float-right text-sm">
                                            <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                wire:click="setHubungan('orangtua')">Set
                                                {{ __('Parent') }}</button>
                                        </div>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                        @if ($user->jenis_kelamin == '1')
                            <tr class="">
                                <th class="px-4 text-gray-500">{{ __('Wife') }}</th>
                                <td class="py-2">
                                    @if ($user->istri)
                                        @foreach ($user->istri as $istri)
                                            <a href="{{ route('pengguna.show', $istri->id) }}"
                                                class="text-blue-600 hover:underline">
                                                {{ $istri->name }} {{ $istri->wafat == true ? '(Alm)' : '' }}
                                            </a>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                    @if ($activeForm === 'istri')
                                        @include('livewire.pengguna.form', ['userList' => $userPerempuan])
                                    @else
                                        @can('update', $user)
                                            <div class="float-right text-sm">
                                                <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                    wire:click="setHubungan('istri')">
                                                    {{ __('Add Wife') }}</button>
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @else
                            <tr class="">
                                <th class="px-4 text-gray-500">{{ __('Husband') }}</th>
                                <td class="py-2">
                                    @if ($user->suami)
                                        @foreach ($user->suami as $suami)
                                            <a href="{{ route('pengguna.show', $suami->id) }}"
                                                class="text-blue-600 hover:underline">
                                                {{ $suami->name }} {{ $suami->wafat == true ? '(Alm)' : '' }}
                                            </a>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                    @if ($activeForm === 'suami')
                                        @include('livewire.pengguna.form', ['userList' => $userLakiLaki])
                                    @else
                                        @can('update', $user)
                                            <div class="float-right text-sm">
                                                <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                    wire:click="setHubungan('suami')">
                                                    {{ __('Add Husband') }}</button>
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
                <div class="mt-4 w-full border">
                    <div class="flex items-center justify-between border-b">
                        <flux:heading size="lg" level="1" class="p-4">{{ __('Children') }}
                        </flux:heading>
                        @can('update', $user)
                            <button wire:click="setHubungan('anak')"
                                class="float-right m-2 cursor-pointer rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700">{{ __('Add') }}</button>
                        @endcan
                    </div>
                    @if ($user->pernikahan)
                        <table class="w-full text-left">
                            @foreach ($user->pernikahan as $pernikahan)
                                @if ($pernikahan->anak)
                                    @foreach ($pernikahan->anak as $anak)
                                        <tr class="border-b">
                                            <td class="px-4 py-2">
                                                <a href="{{ route('pengguna.show', $anak->id) }}"
                                                    class="text-blue-600 hover:underline">
                                                    {{ $anak->anak_ke }}. {{ $anak->name }}
                                                    {{ $anak->wafat == true ? '(Alm)' : '' }}
                                                </a>
                                                ({{ $anak->jenis_kelamin == '1' ? 'L' : 'P' }})
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </table>
                    @endif
                    @if ($activeForm === 'anak')
                        @include('livewire.pengguna.form', [
                            'userList' => $pasanganPernikahanUser,
                            'userWithoutParentList' => $userWithoutParentList,
                        ])
                    @endif
                </div>
                <div class="mt-4 w-full border">
                    <div class="flex items-center justify-between border-b">
                        <flux:heading size="lg" level="1" class="p-4">{{ __('Siblings') }}
                        </flux:heading>
                    </div>
                    @if ($user->orangTua && $user->orangTua->anak)
                        <table class="w-full text-left">
                            @foreach ($user->orangTua->anak as $saudara)
                                @if ($saudara->id !== $user->id)
                                    <tr class="border-b">
                                        <td class="px-4 py-2">
                                            <a href="{{ route('pengguna.show', $saudara->id) }}"
                                                class="text-blue-600 hover:underline">
                                                {{ $saudara->name }} {{ $saudara->wafat == true ? '(Alm)' : '' }}
                                            </a>
                                            ({{ $saudara->jenis_kelamin == '1' ? 'L' : 'P' }})
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
