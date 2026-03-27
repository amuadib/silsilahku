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
        $this->name = $this->user->name;
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
        if ($value) $this->address_id = null;
    }

    public function showFormTambahAlamatMakam($value)
    {
        $this->formTambahAlamatMakamVisible = $value;
    }

    public function update()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
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
            'alamat_makam' => 'nullable',
            'alamat_makam_baru' => 'nullable|string',
        ]);

        if (!empty($this->alamat_makam_baru)) {
            $address = Address::create(['alamat' => $this->alamat_makam_baru]);
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
        <div class="relative my-6 flex w-full justify-between items-center">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
                <flux:subheading size="lg">{{ __('Edit') }}</flux:subheading>
            </div>
            <flux:button color="sky" href="{{ route('pengguna.show', $user->id) }}" wire:navigate variant="primary">
                {{ __('Detail') }}
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        <div class="mt-4 flex flex-col md:flex-row gap-8">
            {{-- Profile Section --}}
            <div class="flex-1 space-y-6">
                <div class="border dark:border-zinc-700 rounded-lg overflow-hidden bg-white dark:bg-zinc-800 shadow-sm">
                    <flux:heading size="xl" class="border-b dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/50">{{ __('Profile') }}</flux:heading>
                    
                    <div class="p-6 space-y-4">
                        <x-profile-photo :user="$user" :newFoto="$newFoto" />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input wire:model="name" :label="__('Name')" required autofocus />
                            
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
                                            <flux:select wire:model.live="address_id" :placeholder="__('Select existing Address')">
                                                <option value="">{{ __('Select existing Address') }}</option>
                                                @foreach ($alamat_list as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <flux:button wire:click="showFormTambahAlamat(true)" icon="plus" variant="subtle" />
                                    </div>
                                @endif
                            </div>
                        </flux:field>
                    </div>

                    <div class="p-4 bg-gray-50 dark:bg-zinc-800/50 border-t dark:border-zinc-700 flex justify-end">
                        <flux:button wire:click="update" color="yellow" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </div>
            </div>

            {{-- Sidebar Sections --}}
            <div class="w-full md:w-1/3 space-y-6">
                {{-- Account Section --}}
                @if(!$user->wafat)
                <div class="border rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/50">
                        <flux:heading size="lg">{{ __('Account') }}</flux:heading>
                        @if (!$accountIsActive)
                            <flux:button size="xs" wire:click="$set('showFormAkun', true)" color="green">{{ __('Activate') }}</flux:button>
                        @else
                            <flux:badge color="sky" size="sm">{{ __('Active') }}</flux:badge>
                        @endif
                    </div>
                    
                    @if ($showFormAkun)
                    <div class="p-4 space-y-4">
                        <flux:input wire:model="email" type="email" :label="__('Email')" />
                        <flux:input wire:model="password" type="password" :label="__('Password')" />
                        <div class="flex justify-end gap-2">
                            <flux:button wire:click="$set('showFormAkun', false)" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                            <flux:button wire:click="updateAkun" size="sm" color="blue">{{ __('Save') }}</flux:button>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Death Section --}}
                @if($user->wafat)
                <div class="border rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/50">
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
                                    <flux:button wire:click="showFormTambahAlamatMakam(false)" size="sm" variant="subtle">{{ __('Cancel') }}</flux:button>
                                    <flux:textarea wire:model="alamat_makam_baru" rows="2" />
                                @else
                                    <div class="flex gap-2 items-center">
                                        <div class="flex-1 w-full">
                                            <flux:select wire:model.live="alamat_makam" :placeholder="__('Select existing Address')">
                                                <option value="">{{ __('Select existing') }}</option>
                                                @foreach ($alamat_list as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <flux:button wire:click="showFormTambahAlamatMakam(true)" icon="plus" variant="subtle" />
                                    </div>
                                @endif
                            </div>
                        </flux:field>

                        <div class="flex justify-end gap-2">
                            <flux:button wire:click="$set('showFormKematian', false)" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                            <flux:button wire:click="updateKematian" size="sm" color="zinc">{{ __('Save') }}</flux:button>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Delete Action --}}
                @can('delete', $user)
                <div class="p-4 border border-red-200 dark:border-red-900/50 rounded-lg bg-red-50 dark:bg-red-900/20">
                    <flux:heading size="sm" class="text-red-700 dark:text-red-400 mb-2">{{ __('Danger Zone') }}</flux:heading>
                    <flux:button color="red" class="w-full" variant="ghost"
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
