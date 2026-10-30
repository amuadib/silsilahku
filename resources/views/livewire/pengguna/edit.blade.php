<?php

use Livewire\Volt\Component;
use App\Models\User;
use Livewire\WithFileUploads;
use App\Traits\HasToast;
use App\Traits\HasAddressCascading;
use App\Models\Provinsi;
use App\Models\KabupatenKota;
use App\Models\Kecamatan;
use App\Models\DesaKelurahan;
use App\Models\Address;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithFileUploads, HasToast, HasAddressCascading;

    public $user;
    public $name;
    public $gelar_depan;
    public $gelar_belakang;
    public $newFoto;
    public $jenis_kelamin;
    public $tanggal_lahir;
    public $anak_ke;
    public $alamat;
    public $alamat_list = [];
    public $address_id;
    public $hp;
    public $email;
    public $password;
    public $wafat;
    public $showFormKematian = false;
    public $formTambahAlamatVisible = false;
    public $accountIsActive = false;
    public $showFormAkun = false;
    public $formTambahAlamatMakamVisible = false;
    public $alamat_makam;
    public $tanggal_wafat;
    public $alamat_makam_baru;

    public function mount($id)
    {
        $this->user = User::findOrFail($id);
        $this->name = $this->user->getRawOriginal('name');
        $this->gelar_depan = $this->user->gelar_depan;
        $this->gelar_belakang = $this->user->gelar_belakang;
        $this->jenis_kelamin = $this->user->jenis_kelamin;
        $this->tanggal_lahir = $this->user->tanggal_lahir?->format('Y-m-d');
        $this->tanggal_wafat = $this->user->tanggal_wafat?->format('Y-m-d');
        $this->anak_ke = $this->user->anak_ke;
        $this->hp = $this->user->hp;
        $this->email = $this->user->email;
        $this->address_id = $this->user->address_id;
        $this->alamat_makam = $this->user->memorial_address_id;
        $this->wafat = $this->user->wafat ? 1 : 0;
        
        $this->showFormKematian = (bool) $this->wafat;

        $this->accountIsActive = !is_null($this->user->password);
        $this->showFormAkun = $this->accountIsActive;

        $this->loadProvinsis();
        $this->loadAlamatList();
    }

    protected function loadAlamatList()
    {
        $this->alamat_list = Address::all()->mapWithKeys(function ($address) {
            return [$address->id => $address->full_address];
        })->toArray();
    }

    public function showFormTambahAlamat($value)
    {
        $this->formTambahAlamatVisible = $value;
        if ($value) {
            $this->address_id = null;
            $this->resetAddressForm();
        }
    }

    public function showFormTambahAlamatMakam($value)
    {
        $this->formTambahAlamatMakamVisible = $value;
        if ($value) {
            $this->alamat_makam = null;
            $this->resetAddressForm();
        }
    }

    protected function resetAddressForm()
    {
        $this->selectedProvinsi = '';
        $this->selectedKabupaten = '';
        $this->selectedKecamatan = '';
        $this->selectedDesa = '';
        $this->alamat = '';
        $this->alamat_makam_baru = '';
        $this->kabupatens = [];
        $this->kecamatans = [];
        $this->desas = [];
    }

    public function update()
    {
        $validated = $this->validate([
            'gelar_depan' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'gelar_belakang' => 'nullable|string|max:100',
            'newFoto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_kelamin' => 'required|in:1,2',
            'tanggal_lahir' => 'nullable|date',
            'anak_ke' => 'nullable|numeric',
            'hp' => 'nullable|string|max:255',
            'wafat' => 'nullable|boolean',
        ]);

        if ($this->newFoto) {
            $validated['foto'] = $this->newFoto->store('foto', 'public');
        }

        $validated['address_id'] = $this->resolveAddressId();
        $validated['manager_id'] = Auth::id();

        $this->user->update($validated);
        $this->toastSuccess(__('Data saved successfully.'));
        $this->redirect(route('pengguna.show', $this->user->id));
    }

    protected function resolveAddressId()
    {
        if ($this->address_id) {
            return $this->address_id;
        }

        if ($this->selectedDesa || $this->selectedKecamatan || $this->selectedKabupaten || $this->selectedProvinsi || $this->alamat) {
            $prov = Provinsi::where('kode', $this->selectedProvinsi)->first();
            $kab = KabupatenKota::where('kode', $this->selectedKabupaten)->first();
            $kec = Kecamatan::where('kode', $this->selectedKecamatan)->first();
            $desa = DesaKelurahan::where('kode', $this->selectedDesa)->first();

            $address = Address::create([
                'alamat' => $this->alamat ?: null,
                'kode_desa' => $desa?->kode,
                'nama_desa' => $desa?->nama,
                'nama_kecamatan' => $kec?->nama,
                'nama_kabupaten' => $kab?->nama,
                'nama_provinsi' => $prov?->nama,
                'lat' => $desa?->lat,
                'lng' => $desa?->lng,
            ]);
            return $address->id;
        }

        return null;
    }

    public function updateAkun()
    {
        $validated = $this->validate([
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'password' => 'nullable|string|min:8',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $this->user->update($validated);
        $this->toastSuccess(__('Data saved successfully.'));
        $this->redirect(route('pengguna.show', $this->user->id));
    }

    public function updateKematian()
    {
        $validated = $this->validate([
            'tanggal_wafat' => 'nullable|date',
        ]);

        if ($this->formTambahAlamatMakamVisible && ($this->selectedDesa || $this->selectedKecamatan || $this->selectedKabupaten || $this->selectedProvinsi || $this->alamat_makam_baru)) {
            $prov = Provinsi::where('kode', $this->selectedProvinsi)->first();
            $kab = KabupatenKota::where('kode', $this->selectedKabupaten)->first();
            $kec = Kecamatan::where('kode', $this->selectedKecamatan)->first();
            $desa = DesaKelurahan::where('kode', $this->selectedDesa)->first();

            $address = Address::create([
                'alamat' => $this->alamat_makam_baru ?: null,
                'kode_desa' => $desa?->kode,
                'nama_desa' => $desa?->nama,
                'nama_kecamatan' => $kec?->nama,
                'nama_kabupaten' => $kab?->nama,
                'nama_provinsi' => $prov?->nama,
                'lat' => $desa?->lat,
                'lng' => $desa?->lng,
            ]);
            $validated['memorial_address_id'] = $address->id;
        } else {
            $validated['memorial_address_id'] = $this->alamat_makam;
        }

        $this->user->update($validated);
        $this->toastSuccess(__('Data saved successfully.'));
        $this->redirect(route('pengguna.show', $this->user->id));
    }

    public function delete(User $user)
    {
        $this->authorize('delete', $user);
        $user->delete();
        $this->toastSuccess(__('Data deleted successfully.'));
        return $this->redirect(route('dashboard'), navigate: true);
    }
};
?>
<section class="mx-auto w-full px-6 lg:px-8 max-w-7xl">
    <div class="w-full">
        <div class="relative my-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
                <flux:subheading size="lg" class="mt-1">{{ __('Edit') }}</flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button color="blue" icon="eye" href="{{ route('pengguna.show', $user->id) }}" wire:navigate variant="outline">
                    {{ __('Detail') }}
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="mt-4 flex flex-col md:flex-row gap-8">
            {{-- Profile Section --}}
            <div class="flex-1 space-y-6">
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="xl" class="border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">{{ __('Profile') }}</flux:heading>
                    
                    <div class="p-6 space-y-4">
                        <x-profile-photo :user="$user" :newFoto="$newFoto" />

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-1">
                                <flux:input wire:model="gelar_depan" :label="__('Gelar Depan')" placeholder="Contoh: KH., H., Hj." />
                            </div>
                            <div class="md:col-span-2">
                                <flux:input wire:model="name" :label="__('Name')" required autofocus />
                            </div>
                            <div class="md:col-span-1">
                                <flux:input wire:model="gelar_belakang" :label="__('Gelar Belakang')" placeholder="Contoh: Lc., M.A." />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ __('Gender') }}</flux:label>
                                <div class="flex gap-4 mt-2">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" wire:model="jenis_kelamin" value="1" class="accent-blue-600"> {{ __('Male') }}
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" wire:model="jenis_kelamin" value="2" class="accent-pink-500"> {{ __('Female') }}
                                    </label>
                                </div>
                            </flux:field>

                            <flux:input wire:model="tanggal_lahir" type="date" :label="__('Birthday')" />
                            <flux:input wire:model="anak_ke" type="number" :label="__('Birth order')" />
                            <flux:input wire:model="hp" :label="__('Phone')" />
                            
                            <flux:field>
                                <flux:label>{{ __('Deceased') }}</flux:label>
                                <div class="flex gap-4 mt-2">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" wire:model="wafat" value="1" class="accent-blue-600"> {{ __('Yes') }}
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" wire:model="wafat" value="0" class="accent-gray-500"> {{ __('No') }}
                                    </label>
                                </div>
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Address') }}</flux:label>
                            <div class="mt-2 space-y-2">
                                @if($formTambahAlamatVisible)
                                    <flux:button wire:click="showFormTambahAlamat(false)" size="sm" variant="subtle">{{ __('Select existing') }}</flux:button>
                                    <x-address-picker :provinsis="$provinsis" :kabupatens="$kabupatens" :kecamatans="$kecamatans" :desas="$desas" />
                                    <flux:textarea wire:model="alamat" rows="2" :placeholder="__('Full Address Detail')" />
                                @else
                                    <div class="flex gap-2 items-center">
                                        <div class="flex-1 w-full">
                                            <x-searchable-select wire:model.live="address_id" :placeholder="__('Select existing Address')">
                                                <option value="">{{ __('Select existing Address') }}</option>
                                                @foreach ($alamat_list as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </x-searchable-select>
                                        </div>
                                        <flux:button wire:click="showFormTambahAlamat(true)" icon="plus" variant="subtle" />
                                    </div>
                                @endif
                            </div>
                        </flux:field>
                    </div>

                    <div class="p-4 border-t border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50 flex justify-end">
                        <flux:button wire:click="update" color="yellow" variant="primary" style="cursor: pointer;">{{ __('Update') }}</flux:button>
                    </div>
                </div>
            </div>

            {{-- Sidebar Sections --}}
            <div class="w-full md:w-1/3 space-y-6">
                {{-- Account Section --}}
                @can('update', $user)
                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                            <flux:heading size="lg">
                                {{ __('Account') }}
                                @if($accountIsActive)
                                    <flux:icon name="lock-open" class="text-green-500 inline-block" variant="micro"/>
                                @else
                                    <flux:icon name="lock-closed" class="text-red-500 inline-block" variant="micro"/>
                                @endif
                            </flux:heading>
                                @if(!$accountIsActive)
                                    <flux:button size="xs" wire:click="$set('showFormAkun', true)" color="green" class="cursor-pointer" variant="primary">{{ __('Activate') }}</flux:button>
                                @else
                                    @if($showFormAkun)
                                        <flux:button size="xs" wire:click="$set('showFormAkun', false)" class="cursor-pointer" variant="ghost">{{ __('Cancel') }}</flux:button>
                                    @else
                                        <flux:button size="xs" wire:click="$set('showFormAkun', true)" class="cursor-pointer" variant="primary" color="yellow">{{ __('Edit') }}</flux:button>
                                    @endif
                                @endif
                        </div>
                        
                        @if ($showFormAkun)
                        <div class="p-4 space-y-4">
                            <flux:input wire:model="email" type="email" :label="__('Email')" />
                            <flux:input wire:model="password" type="password" :label="__('Password')" />
                            <div class="flex justify-end gap-2">
                                <flux:button wire:click="$set('showFormAkun', false)" size="sm" variant="ghost" class="cursor-pointer">{{ __('Cancel') }}</flux:button>
                                @if($accountIsActive)
                                    <flux:button wire:click="updateAkun" size="sm" color="yellow" class="cursor-pointer" variant="primary">{{ __('Update') }}</flux:button>
                                @else
                                    <flux:button wire:click="updateAkun" size="sm" color="blue" class="cursor-pointer" variant="primary">{{ __('Save') }}</flux:button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                @endcan

                {{-- Death Section --}}
                @if($user->wafat)
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                        <flux:heading size="lg">{{ __('Death') }}</flux:heading>
                        @if (!$showFormKematian)
                            <flux:button size="xs" wire:click="$set('showFormKematian', true)" variant="subtle">{{ __('Edit') }}</flux:button>
                        @endif
                    </div>

                    @if ($showFormKematian)
                    <div class="p-4 space-y-4">
                        <flux:input wire:model="tanggal_wafat" type="date" :label="__('Deathday')" />
                        
                        <flux:field>
                            <flux:label>{{ __('Memorial Address') }}</flux:label>
                            <div class="mt-2 space-y-2">
                                @if($formTambahAlamatMakamVisible)
                                    <flux:button wire:click="showFormTambahAlamatMakam(false)" size="sm" variant="subtle">{{ __('Select existing Address') }}</flux:button>
                                    <x-address-picker :provinsis="$provinsis" :kabupatens="$kabupatens" :kecamatans="$kecamatans" :desas="$desas" />
                                    <flux:textarea wire:model="alamat_makam_baru" rows="2" :placeholder="__('Full Address Detail')" />
                                @else
                                    <div class="flex gap-2 items-center">
                                        <div class="flex-1 w-full">
                                            <x-searchable-select wire:model.live="alamat_makam" :placeholder="__('Select existing Address')">
                                                <option value="">{{ __('Select existing Address') }}</option>
                                                @foreach ($alamat_list as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </x-searchable-select>
                                        </div>
                                        <flux:button wire:click="showFormTambahAlamatMakam(true)" icon="plus" variant="subtle" />
                                    </div>
                                @endif
                            </div>
                        </flux:field>

                        <div class="flex justify-end gap-2">
                            <flux:button wire:click="$set('showFormKematian', false)" size="sm" variant="ghost" style="cursor: pointer;">{{ __('Cancel') }}</flux:button>
                            <flux:button wire:click="updateKematian" size="sm" color="blue" variant="primary" style="cursor: pointer;">{{ __('Save') }}</flux:button>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Delete Action --}}
                @can('delete', $user)
                <div class="p-4 border border-red-200 dark:border-red-900/50 rounded-lg bg-red-50 dark:bg-red-900/20">
                    <flux:heading size="sm" class="text-red-700 dark:text-red-400 mb-2">{{ __('Danger Zone') }}</flux:heading>
                    <flux:button color="red" class="w-full" variant="ghost" style="cursor: pointer;"
                        wire:click="delete('{{ $user->id }}')"
                        wire:confirm="{{ __('Are you sure?') }}">
                        {{ __('Delete User') }}
                    </flux:button>
                </div>
                @endcan
            </div>
        </div>
    </div>
</section>
