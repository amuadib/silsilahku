<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Pernikahan;
use App\Traits\HasToast;

new class extends Component {
    use HasToast;
    public User $user;
    public ?string $activeForm = null;
    public $userLakiLaki;
    public $userPerempuan;
    public $userFromSelection;
    public $userFromInput;
    public $userList;
    public $userWithoutParentList;
    public $name;
    public $jenis_kelamin;
    public $pasanganPernikahan;
    public $pasanganPernikahanUser;
    public $ortu_id;
    public $anak_ke;
    public $userExist = false;

    public function mount($id)
    {
        $this->user = User::findOrFail($id);
        $this->getData();
    }

    private function getData()
    {
        $this->userLakiLaki = User::where('jenis_kelamin', '1')->orderBy('name', 'asc')->get();
        $this->userPerempuan = User::where('jenis_kelamin', '2')->orderBy('name', 'asc')->get();
        foreach (Pernikahan::with('suami', 'istri')->get() as $pernikahan) {
            $this->pasanganPernikahan[$pernikahan->id] = $pernikahan->suami->name . ' & ' . $pernikahan->istri->name;
        }
        foreach (Pernikahan::with('suami', 'istri')->where('suami_id', $this->user->id)->orWhere('istri_id', $this->user->id)->get() as $pernikahan) {
            $this->pasanganPernikahanUser[$pernikahan->id] = $pernikahan->suami->name . ' & ' . $pernikahan->istri->name;
        }

        $this->userWithoutParentList = User::where('ortu_id', null)->orderBy('name', 'asc')->get();
    }
    public function setHubungan($hubungan)
    {
        $this->getData();
        $this->activeForm = $hubungan;
    }

    public function save()
    {
        $this->authorize('update', $this->user);
        if ($this->userFromSelection !== null) {
            $id = $this->userFromSelection;
        } elseif ($this->userFromInput !== null) {
            $id = $this->addUser([
                'name' => $this->userFromInput,
                'jenis_kelamin' => $this->activeForm === 'ayah' || $this->activeForm === 'suami' ? '1' : '2',
                'manager_id' => Auth::id(),
            ]);
        }
        if ($this->activeForm === 'ayah') {
            $this->user->update([
                'ayah_id' => $id,
            ]);
        } elseif ($this->activeForm === 'ibu') {
            $this->user->update([
                'ibu_id' => $id,
            ]);
        } elseif ($this->activeForm === 'suami') {
            Pernikahan::create([
                'suami_id' => $id,
                'istri_id' => $this->user->id,
                'manager_id' => Auth::id(),
            ]);
        } elseif ($this->activeForm === 'istri') {
            Pernikahan::create([
                'suami_id' => $this->user->id,
                'istri_id' => $id,
                'manager_id' => Auth::id(),
            ]);
        } elseif ($this->activeForm === 'orangtua') {
            $this->user->update([
                'ortu_id' => $this->ortu_id,
            ]);
        } elseif ($this->activeForm === 'anak') {
            $pernikahan = Pernikahan::find($this->ortu_id);
            if ($this->userFromSelection !== null) {
                $id = $this->userFromSelection;
                User::find($id)->update([
                    'ortu_id' => $this->ortu_id,
                    'ayah_id' => $pernikahan->suami_id,
                    'ibu_id' => $pernikahan->istri_id,
                    'anak_ke' => $this->anak_ke,
                ]);
            } else {
                $id = $this->addUser([
                    'name' => $this->name,
                    'jenis_kelamin' => $this->jenis_kelamin,
                    'anak_ke' => $this->anak_ke,
                    'ortu_id' => $this->ortu_id,
                    'ayah_id' => $pernikahan->suami_id,
                    'ibu_id' => $pernikahan->istri_id,
                    'manager_id' => Auth::id(),
                ]);
            }
        }
        $this->resetAll();
        $this->toastSuccess(__('Data saved successfully.'));
        $this->resetAll();
    }

    private function resetAll()
    {
        $this->activeForm = null;
        $this->userFromSelection = null;
        $this->userFromInput = null;
        $this->name = null;
        $this->jenis_kelamin = null;
        $this->anak_ke = null;
        $this->ortu_id = null;
    }

    private function addUser(array $data)
    {
        $user = User::create($data);
        return $user->id;
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);
        if ($user->pernikahan()->count() > 0) {
            Pernikahan::where('suami_id', $user->id)->orWhere('istri_id', $user->id)->delete();
        }
        $user->delete();
        $this->toastSuccess(__('Data deleted successfully.'));
        return $this->redirect(route('dashboard'), navigate: true);
    }
};
?>
<section class="item-center mx-auto flex w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="w-full">
        <div class="relative my-6 flex w-full justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
                <flux:subheading size="lg">{{ __('Profile') }}</flux:subheading>
            </div>
            <div>
                <flux:button color="zinc" href="{{ route('pengguna.pohon', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline">{{ __('Family Tree') }}
                </flux:button>
                @can('update', $user)
                    <flux:button color="yellow" href="{{ route('pengguna.edit', $user->id) }}" wire:navigate class="cursor-pointer" variant="primary">{{ __('Edit') }}
                    </flux:button>
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
                            <td class="py-2">
                                {{ $user->alamat->alamat ?? '' }}
                                {{ $user->alamat->nama_desa ?? '' }}
                                {{ $user->alamat->nama_kecamatan ?? '' }}
                                {{ $user->alamat->nama_kabupaten ?? '' }}
                                {{ $user->alamat->nama_provinsi ?? '' }}
                            </td>
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
                                    <div class="flex justify-between">
                                        <div class="flex flex-col">
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
                                        </div>
                                        @if($activeForm !== 'istri')
                                        @can('update', $user)
                                            <div class="float-right text-sm">
                                                <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                    wire:click="setHubungan('istri')">
                                                    {{ __('Add Wife') }}</button>
                                            </div>
                                        @endcan
                                        @endif
                                    </div>
                                    
                                    @if ($activeForm === 'istri')
                                        @include('livewire.pengguna.form', ['userList' => $userPerempuan])                                        
                                    @endif
                                </td>
                            </tr>
                        @else
                            <tr class="">
                                <th class="px-4 text-gray-500">{{ __('Husband') }}</th>
                                <td class="py-2">
                                    <div class="flex justify-between">
                                    <div class="flex flex-col">
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
                                    </div>
                                    @if($activeForm !== 'suami')
                                    @can('update', $user)
                                            <div class="float-right text-sm">
                                                <button class="mr-2 cursor-pointer text-blue-600 hover:underline"
                                                    wire:click="setHubungan('suami')">
                                                    {{ __('Add Husband') }}</button>
                                            </div>
                                        @endcan
                                        @endif
                                    </div>
                                    @if ($activeForm === 'suami')
                                        @include('livewire.pengguna.form', ['userList' => $userLakiLaki])
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
    @include('partials.toast-bridge')
    </div>
</section>
