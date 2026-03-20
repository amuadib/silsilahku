<?php

use Livewire\Volt\Component;
use App\Models\User;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    public $user;
    public $name;
    public $newFoto; // hanya untuk upload baru
    public $jenis_kelamin;
    public $tanggal_lahir;
    public $anak_ke;
    public $alamat;
    public $hp;
    public $email;
    public $password;
    public $wafat;
    public $showFormKematian = false;
    public $accountIsActive=false;

    public function mount($id)
    {
        $this->user = User::findOrFail($id);
        $this->name = $this->user->name;
        $this->jenis_kelamin = $this->user->jenis_kelamin;
        $this->tanggal_lahir = $this->user->tanggal_lahir?->format('Y-m-d');
        $this->anak_ke = $this->user->anak_ke;
        $this->alamat = $this->user->alamat;
        $this->hp = $this->user->hp;
        $this->email = $this->user->email;
        $this->wafat = $this->user->wafat;
        if ($this->wafat) {
            $this->showFormKematian = true;
        }
        if ($this->user->password != null) {
            $this->accountIsActive = true;
        }
    }

    public function update()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'newFoto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_kelamin' => 'required|in:1,2',
            'tanggal_lahir' => 'nullable|date',
            'anak_ke' => 'nullable|numeric',
            'alamat' => 'nullable|string|max:255',
            'hp' => 'nullable|string|max:255',
            'wafat' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'tanggal_lahir' => $validated['tanggal_lahir'],
            'anak_ke' => $validated['anak_ke'],
            'manager_id' => Auth::user()->id,
            'alamat' => $validated['alamat'],
            'hp' => $validated['hp'],
            'wafat' => $validated['wafat'],
        ];

        if ($this->newFoto) {
            $data['foto'] = $this->newFoto->store('foto', 'public');
        }

        $this->user->update($data);

        $this->dispatch('user-updated', name: $this->user->name);
        $this->redirect(route('pengguna.show', $this->user->id));
    }

    public function updateAkun()
    {
        $validated = $this->validate([
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'password' => 'nullable|string|min:8',
        ]);

        if ($validated['password']) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $this->user->update($validated);

        $this->dispatch('user-updated', name: $this->user->name);
        $this->redirect(route('pengguna.show', $this->user->id));
    }

    public function updateKematian()
    {
        $validated = $this->validate([
            'tanggal_wafat' => 'nullable|date',
        ]);

        $this->user->update($validated);

        $this->dispatch('user-updated', name: $this->user->name);
        $this->redirect(route('pengguna.show', $this->user->id));
    }
};
?>
<section class="item-center mx-auto flex w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="w-full">
        <div class="relative my-6 flex w-full justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
                <flux:subheading size="lg">{{ __('Edit') }}</flux:subheading>
            </div>
            <div>
                <a href="{{ route('pengguna.show', $user->id) }}">
                    <flux:button color="sky" class="cursor-pointer" variant="primary">{{ __('Detail') }}
                    </flux:button>
                </a>
            </div>
        </div>
        <flux:separator variant="subtle" />
        <flux:heading class="sr-only">{{ __('Edit') }}</flux:heading>
        <div class="mt-4 flex items-start gap-5 max-md:flex-col">
            <div class="flex-2 self-stretch border max-md:pt-6">
                <flux:heading size="xl" level="1" class="border-b p-4">{{ __('Profile') }}
                </flux:heading>

                <table class="w-full text-left">
                    <tr class="border-b">
                        <td colspan="2" class="px-4 py-2">
                            @if ($newFoto)
                                {{-- Preview foto baru yang baru saja diupload --}}
                                <img src="{{ $newFoto->temporaryUrl() }}" alt="Foto {{ $user->name }}"
                                    class="mx-auto mb-2 w-64 object-cover">
                            @elseif ($user->foto)
                                {{-- Tampilkan foto lama dari storage --}}

                                <img src="{{ asset('storage/' . $user->foto) }}" alt="Foto {{ $user->name }}"
                                    class="mx-auto mb-2 w-64 object-cover">
                            @else
                                {{-- Foto default berdasarkan jenis kelamin --}}
                                <img src="{{ $user->jenis_kelamin == '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg') }}"
                                    alt="Foto {{ $user->name }}" class="mx-auto mb-2 w-64 object-cover">
                            @endif
                            <flux:input wire:model="newFoto" type="file" accept="image/*" />
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="w-40 px-4 py-2 text-gray-500">{{ __('Name') }}</th>
                        <td class="px-4 py-2">
                            <flux:input wire:model="name" type="text" required autofocus autocomplete="name" />
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-gray-500">{{ __('Gender') }}</th>
                        <td class="px-4 py-2">
                            <div class="flex gap-4">
                                <label class="inline-flex cursor-pointer items-center gap-2">
                                    <input type="radio" wire:model="jenis_kelamin" value="1"
                                        class="accent-blue-600"> {{ __('Male') }}
                                </label>
                                <label class="inline-flex cursor-pointer items-center gap-2">
                                    <input type="radio" wire:model="jenis_kelamin" value="2"
                                        class="accent-pink-500"> {{ __('Female') }}
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-gray-500">{{ __('Birthday') }}</th>
                        <td class="px-4 py-2">
                            {{-- tanggal lahir tidak terisi --}}
                            <flux:input wire:model="tanggal_lahir" type="date" autocomplete="tanggal_lahir" />
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-gray-500">{{ __('Birth order') }}</th>
                        <td class="px-4 py-2">
                            <flux:input wire:model="anak_ke" type="number" />
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="w-40 px-4 py-2 text-gray-500">{{ __('Address') }}</th>
                        <td class="px-4 py-2">
                            <textarea wire:model="alamat" rows="3" class="w-full rounded border p-2"></textarea>
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-gray-500">{{ __('Phone') }}</th>
                        <td class="px-4 py-2">
                            <flux:input wire:model="hp" type="text" autocomplete="hp" />
                        </td>
                    </tr>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-gray-500">{{ __('Deceased') }}</th>
                        <td class="px-4 py-2">
                            <div class="flex gap-4">
                                <label class="inline-flex cursor-pointer items-center gap-2">
                                    <input type="radio" wire:model="wafat" value="1" class="accent-blue-600">
                                    {{ __('Yes') }}
                                </label>
                                <label class="inline-flex cursor-pointer items-center gap-2">
                                    <input type="radio" wire:model="wafat" value="0" class="accent-gray-500">
                                    {{ __('No') }}
                                </label>
                            </div>
                            @error('wafat')
                                <span class="text-red-500">{{ $message }}</span>
                            @enderror
                        </td>
                    </tr>
                </table>
                <button wire:click="update"
                    class="float-right m-2 cursor-pointer rounded bg-yellow-600 px-4 py-2 text-white">{{ __('Update') }}</button>
            </div>
            <flux:separator class="md:hidden" />

            <div class="flex-1 self-stretch max-md:pt-6">
                @if(!$user->wafat)
                <div class="w-full border mb-4">
                    <div class="flex items-center justify-between border-b">
                        <flux:heading size="xl" level="1" class="p-4">{{ __('Account') }}
                        </flux:heading>
                        @if (!$accountIsActive)
                            <button wire:click="$set('accountIsActive', true)"
                                class="float-right m-2 cursor-pointer rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700">{{ __('Activate') }}</button>
                        @else
                            <span class="float-right m-2 cursor-pointer rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">{{ __('Account is Active') }}</span>
                        @endif
                    </div>
                    @if (!$accountIsActive)
                        <table class="w-full text-left">
                            <tr class="border-b">
                                <th class="w-40 px-4 py-2 text-gray-500">{{ __('Email') }}</th>
                                <td class="px-4 py-2">
                                    <flux:input wire:model="email" type="email" autocomplete="email" />
                                </td>
                            </tr>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-gray-500">{{ __('Password') }}</th>
                                <td class="px-4 py-2">
                                    <flux:input wire:model="password" type="password" autocomplete="password" />
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <button wire:click="updateAkun"
                                        class="float-right m-2 cursor-pointer rounded bg-blue-600 px-4 py-2 text-white">{{ __('Save') }}</button>
                                    <button wire:click="$set('showFormAkun', false)"
                                        class="float-right my-2 cursor-pointer rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700">{{ __('Cancel') }}</button>
                                </td>
                            </tr>
                        </table>
                    @endif
                </div>
@endif
                <div class="w-full border">
                    <div class="flex items-center justify-between border-b">
                        <flux:heading size="lg" level="1" class="p-4">{{ __('Death') }}
                        </flux:heading>
                        @if (!$showFormKematian)
                            <button wire:click="$set('showFormKematian', true)"
                                class="float-right m-2 cursor-pointer rounded bg-gray-600 px-2 py-1 text-xs text-white hover:bg-gray-700">{{ __('Add') }}</button>
                        @endif
                    </div>
                    @if ($showFormKematian)
                        <table class="w-full text-left">
                            <tr class="border-b">
                                <th class="w-40 px-4 text-gray-500">{{ __('Deathday') }}</th>
                                <td class="px-4 py-2">
                                    <flux:input wire:model="tanggal_wafat" type="date"
                                        autocomplete="tanggal_wafat" />
                                </td>
                            </tr>
                            <tr class="border-b">
                                <th class="w-40 px-4 text-gray-500">{{ __('Memorial') }}</th>
                                <td class="px-4 py-2">
                                    <flux:input wire:model="lokasi_makam" type="text"
                                        autocomplete="lokasi_makam" />
                                </td>
                            </tr>
                            <tr class="border-b">
                                <th class="w-40 px-4 text-gray-500">{{ __('Memorial Address') }}</th>
                                <td class="px-4 py-2">
                                    <textarea wire:model="alamat_makam" rows="3" class="w-full rounded border p-2"></textarea>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2">
                                    <button wire:click="updateKematian"
                                        class="float-right m-2 cursor-pointer rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700">{{ __('Save') }}</button>
                                    <button wire:click="$set('showFormKematian', false)"
                                        class="float-right my-2 cursor-pointer rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700">{{ __('Cancel') }}</button>
                                </td>
                            </tr>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
