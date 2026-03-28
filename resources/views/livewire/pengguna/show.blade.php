<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Pernikahan;
use App\Traits\HasToast;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use HasToast;

    public User $user;
    public ?string $activeForm = null;
    public $userLakiLaki;
    public $userPerempuan;
    public $userFromSelection;
    public $userFromInput;
    public $userWithoutParentList;
    public $name;
    public $jenis_kelamin;
    public $pasanganPernikahan;
    public $pasanganPernikahanUser;
    public $ortu_id;
    public $anak_ke;
    public $userExist = false;
    public $newFoto;

    public function mount($id)
    {
        $this->user = User::findOrFail($id);
        $this->loadData();
    }

    private function loadData()
    {
        $this->userLakiLaki = User::where('jenis_kelamin', '1')->orderBy('name')->get();
        $this->userPerempuan = User::where('jenis_kelamin', '2')->orderBy('name')->get();
        $this->userWithoutParentList = User::whereNull('ortu_id')->orderBy('name')->get();
        
        $this->pasanganPernikahan = Pernikahan::with('suami', 'istri')->get()
            ->mapWithKeys(fn($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])
            ->toArray();

        $this->pasanganPernikahanUser = Pernikahan::where('suami_id', $this->user->id)
            ->orWhere('istri_id', $this->user->id)
            ->with('suami', 'istri')->get()
            ->mapWithKeys(fn($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])
            ->toArray();
    }

    public function setHubungan($hubungan)
    {
        $this->loadData();
        $this->activeForm = $hubungan;
    }

    public function save()
    {
        $this->authorize('update', $this->user);
        
        $id = $this->resolveTargetUserId();

        match ($this->activeForm) {
            'ayah' => $this->user->update(['ayah_id' => $id]),
            'ibu' => $this->user->update(['ibu_id' => $id]),
            'suami' => Pernikahan::create(['suami_id' => $id, 'istri_id' => $this->user->id, 'manager_id' => Auth::id()]),
            'istri' => Pernikahan::create(['suami_id' => $this->user->id, 'istri_id' => $id, 'manager_id' => Auth::id()]),
            'orangtua' => $this->user->update(['ortu_id' => $this->ortu_id]),
            'anak' => $this->handleSaveAnak($id),
            default => null,
        };

        $this->resetAll();
        $this->toastSuccess(__('Data saved successfully.'));
    }

    protected function resolveTargetUserId()
    {
        if ($this->userFromSelection) return $this->userFromSelection;
        
        if ($this->userFromInput) {
            return User::create([
                'name' => $this->userFromInput,
                'jenis_kelamin' => in_array($this->activeForm, ['ayah', 'suami']) ? '1' : '2',
                'manager_id' => Auth::id(),
            ])->id;
        }

        return null;
    }

    protected function handleSaveAnak($id)
    {
        $pernikahan = Pernikahan::findOrFail($this->ortu_id);
        
        if (!$id) {
            $id = User::create([
                'name' => $this->name,
                'jenis_kelamin' => $this->jenis_kelamin,
                'manager_id' => Auth::id(),
            ])->id;
        }

        User::find($id)->update([
            'ortu_id' => $this->ortu_id,
            'ayah_id' => $pernikahan->suami_id,
            'ibu_id' => $pernikahan->istri_id,
            'anak_ke' => $this->anak_ke,
        ]);
    }

    private function resetAll()
    {
        $this->reset(['activeForm', 'userFromSelection', 'userFromInput', 'name', 'jenis_kelamin', 'anak_ke', 'ortu_id', 'userExist']);
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

<section class="mx-auto w-full px-6 lg:px-8 max-w-7xl">
    <div class="w-full">
        <div class="relative my-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }} {{ $user->wafat ? '(Alm)' : '' }}</flux:heading>
                <flux:subheading size="lg" class="mt-1">{{ __('Profile Detail') }}</flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button color="zinc" href="{{ route('pengguna.daftar', $user->id) }}" wire:navigate variant="outline">
                    {{ __('List of Family members') }}
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate variant="outline">
                    {{ __('Family Tree') }}
                </flux:button>
                @can('update', $user)
                    <flux:button color="yellow" href="{{ route('pengguna.edit', $user->id) }}" wire:navigate variant="primary">
                        {{ __('Edit') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Profile Card --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="border rounded-lg overflow-hidden bg-white dark:bg-zinc-800 shadow-sm">
                    <div class="bg-gray-50 dark:bg-zinc-800/50 p-6 flex flex-col items-center border-b dark:border-zinc-700">
                        <x-profile-photo :user="$user" :showUpload="false" />
                        <flux:heading size="lg" class="mt-4">{{ $user->name }} {{ $user->wafat ? '(Alm)' : '' }}</flux:heading>
                        <flux:badge color="{{ $user->jenis_kelamin == '1' ? 'blue' : 'pink' }}" size="sm" class="mt-1">
                            {{ $user->jenis_kelamin == '1' ? __('Male') : __('Female') }}
                        </flux:badge>
                    </div>

                    <div class="p-4 space-y-3 text-sm">
                        <div class="flex justify-between ">
                            <span class="text-gray-500 dark:text-zinc-400">{{ __('Birthday') }}</span>
                            <span>{{ $user->tanggal_lahir?->format('d M Y') ?: '-' }}</span>
                        </div>
                        @if ($user->wafat)
                        <div class="flex justify-between border-t dark:border-zinc-700 pt-3">
                            <span class="text-gray-500 dark:text-zinc-400 block mb-1">{{ __('Deathday') }}</span>
                            <span>{{ $user->tanggal_wafat?->format('d M Y') ?: '-' }}</span>
                        </div>
                        <div class="border-t dark:border-zinc-700 pt-3">
                            <span class="text-gray-500 dark:text-zinc-400 block mb-1">{{ __('Memorial Address') }}</span>
                            <span class="text-xs">{{ $user->alamatMakam?->full_address ?: '-' }}</span>
                        </div>
                        @endif
                        <div class="border-t dark:border-zinc-700 pt-3 flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">{{ __('Age') }}</span>
                            <span>
                                @if ($user->wafat)
                                    {{ $user->tanggal_lahir?->diffForHumans($user->tanggal_wafat, true, true) ?: '-' }}
                                @else
                                    {{ $user->tanggal_lahir?->diffForHumans(null, true, true) ?: '-' }}
                                @endif
                            </span>
                        </div>
                        <div class="border-t dark:border-zinc-700 pt-3 flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">{{ __('Birth order') }}</span>
                            <span>{{ $user->anak_ke ?: '-' }}</span>
                        </div>
                        <div class="border-t dark:border-zinc-700 pt-3 flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400 block mb-1">{{ __('Email') }}</span>
                            <span>{{ $user->email ?: '-' }}</span>
                        </div>
                        <div class="border-t dark:border-zinc-700 pt-3 flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400 block mb-1">{{ __('Phone') }}</span>
                            <span>{{ $user->hp ?: '-' }}</span>
                        </div>
                        <div class="border-t dark:border-zinc-700 pt-3">
                            <span class="text-gray-500 dark:text-zinc-400 block mb-1">{{ __('Address') }}</span>
                            <span class="text-xs">{{ $user->alamat?->full_address ?: '-' }}</span>
                        </div>
                    </div>

                    @can('delete', $user)
                    <div class="p-4 bg-red-50 dark:bg-red-900/20 border-t dark:border-zinc-700">
                        <flux:button color="red" variant="ghost" class="w-full cursor-pointer"
                            wire:click="delete('{{ $user->id }}')"
                            wire:confirm="{{ __('Are you sure?') }}">
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                    @endcan
                </div>
            </div>

            {{-- Family Section --}}
            <div class="lg:col-span-2 space-y-8">
                {{-- Relationships --}}
                <div class="border rounded-lg bg-white dark:bg-zinc-800 shadow-sm overflow-hidden">
                    <flux:heading size="xl" class="border-b dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/50">{{ __('Family Relationships') }}</flux:heading>
                    
                    <div class="divide-y dark:divide-zinc-700">
                        {{-- Father --}}
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="w-32 text-gray-500 dark:text-zinc-400 font-medium">{{ __('Father') }}</div>
                            <div class="flex-1">
                                @if ($user->ayah)
                                    <a href="{{ route('pengguna.show', $user->ayah->id) }}" class="text-blue-600 hover:underline font-semibold">
                                        {{ $user->ayah->name }} {{ $user->ayah->wafat ? '(Alm)' : '' }}
                                    </a>
                                @endif
                                
                                @if ($activeForm === 'ayah')
                                    <div class="mt-4 p-4 border dark:border-zinc-700 rounded bg-gray-50 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', ['userList' => $userLakiLaki])
                                    </div>
                                @elseif (!$user->ayah && Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('ayah')" class="cursor-pointer">
                                        {{ __('Set') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Mother --}}
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="w-32 text-gray-500 dark:text-zinc-400 font-medium">{{ __('Mother') }}</div>
                            <div class="flex-1">
                                @if ($user->ibu)
                                    <a href="{{ route('pengguna.show', $user->ibu->id) }}" class="text-blue-600 hover:underline font-semibold">
                                        {{ $user->ibu->name }} {{ $user->ibu->wafat ? '(Alm)' : '' }}
                                    </a>
                                @endif
                                
                                @if ($activeForm === 'ibu')
                                    <div class="mt-4 p-4 border dark:border-zinc-700 rounded bg-gray-50 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', ['userList' => $userPerempuan])
                                    </div>
                                @elseif (!$user->ibu && Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('ibu')" class="cursor-pointer">
                                        {{ __('Set') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Parents (Couple) --}}
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="w-32 text-gray-500 dark:text-zinc-400 font-medium">{{ __('Parents') }}</div>
                            <div class="flex-1">
                                @if ($user->orangTua)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('pengguna.show', $user->orangTua->suami->id) }}" class="text-blue-600 hover:underline">
                                            {{ $user->orangTua->suami->name }} {{ $user->orangTua->suami->wafat ? '(Alm)' : '' }}
                                        </a>
                                        <span class="text-gray-400 dark:text-zinc-500">&</span>
                                        <a href="{{ route('pengguna.show', $user->orangTua->istri->id) }}" class="text-blue-600 hover:underline">
                                            {{ $user->orangTua->istri->name }} {{ $user->orangTua->istri->wafat ? '(Alm)' : '' }}
                                        </a>
                                    </div>
                                @endif

                                @if ($activeForm === 'orangtua')
                                    <div class="mt-4 p-4 border dark:border-zinc-700 rounded bg-gray-50 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', ['userList' => $pasanganPernikahan])
                                    </div>
                                @elseif (!$user->orangTua && Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('orangtua')" class="cursor-pointer">
                                        {{ __('Set') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Spouses --}}
                        <div class="p-4 flex flex-col sm:flex-row sm:items-top justify-between gap-4">
                            <div class="w-32 text-gray-500 dark:text-zinc-400 font-medium">{{ $user->jenis_kelamin == '1' ? __('Wife') : __('Husband') }}</div>
                            <div class="flex-1 space-y-1">
                                @php $spouses = $user->jenis_kelamin == '1' ? $user->istri : $user->suami; @endphp
                                @foreach ($spouses as $spouse)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('pengguna.show', $spouse->id) }}" class="text-blue-600 hover:underline font-medium">
                                            {{ $spouse->name }} {{ $spouse->wafat ? '(Alm)' : '' }}
                                        </a>
                                    </div>
                                @endforeach

                                @if ($activeForm === ($user->jenis_kelamin == '1' ? 'istri' : 'suami'))
                                    <div class="mt-4 p-4 border dark:border-zinc-700 rounded bg-gray-50 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', ['userList' => $user->jenis_kelamin == '1' ? $userPerempuan : $userLakiLaki])
                                    </div>
                                @elseif (Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('{{ $user->jenis_kelamin == '1' ? 'istri' : 'suami' }}')" class="cursor-pointer">
                                        {{ __('Add') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Children --}}
                <div class="border rounded-lg bg-white dark:bg-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between border-b dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/50">
                        <flux:heading size="xl">{{ __('Children') }}</flux:heading>
                        @can('update', $user)
                            <flux:button size="xs" wire:click="setHubungan('anak')" color="green" icon="plus" class="cursor-pointer">{{ __('Add') }}</flux:button>
                        @endcan
                    </div>

                    <div class="p-4">
                        @php $hasChildren = false; @endphp
                        @if ($user->pernikahan->count() > 0)
                            <div class="space-y-4">
                                @foreach ($user->pernikahan as $pernikahan)
                                    @if ($pernikahan->anak->count() > 0)
                                        @php $hasChildren = true; @endphp
                                        <div class="space-y-2">
                                            @if($user->pernikahan->count() > 1)
                                                <div class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider">
                                                    {{ __('With') }} {{ $user->id === $pernikahan->suami_id ? $pernikahan->istri->name : $pernikahan->suami->name }}
                                                </div>
                                            @endif
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                @foreach ($pernikahan->anak as $anak)
                                                    <a href="{{ route('pengguna.show', $anak->id) }}" class="flex items-center gap-3 p-3 border dark:border-zinc-700 rounded hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                                                        <flux:avatar size="xs" :src="$anak->foto ? asset('storage/'.$anak->foto) : ($anak->jenis_kelamin == '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg'))" />
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-sm font-medium truncate">{{ $anak->anak_ke }}. {{ $anak->name }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $anak->jenis_kelamin == '1' ? __('Male') : __('Female') }} {{ $anak->wafat ? '(Alm)' : '' }}</div>
                                                        </div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if (!$hasChildren && $activeForm !== 'anak')
                            <div class="text-center py-8 text-gray-400 dark:text-zinc-500 italic">{{ __('No children listed') }}</div>
                        @endif

                        @if ($activeForm === 'anak')
                            <div class="mt-6 p-6 border-2 dark:border-zinc-700 border-dashed rounded-lg bg-gray-50 dark:bg-zinc-800/50">
                                <flux:heading size="md" class="mb-4">{{ __('Add Child') }}</flux:heading>
                                @include('livewire.pengguna.form', [
                                    'userList' => $pasanganPernikahanUser,
                                    'userWithoutParentList' => $userWithoutParentList,
                                ])
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Siblings --}}
                @if ($user->orangTua && $user->orangTua->anak->count() > 1)
                <div class="border rounded-lg bg-white dark:bg-zinc-800 shadow-sm overflow-hidden">
                    <flux:heading size="xl" class="border-b dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/50">{{ __('Siblings') }}</flux:heading>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($user->orangTua->anak as $saudara)
                            @if ($saudara->id !== $user->id)
                                <a href="{{ route('pengguna.show', $saudara->id) }}" class="flex items-center gap-3 p-3 border dark:border-zinc-700 rounded hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                                    <flux:avatar size="xs" :src="$saudara->foto ? asset('storage/'.$saudara->foto) : ($saudara->jenis_kelamin == '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg'))" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium truncate">{{ $saudara->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $saudara->jenis_kelamin == '1' ? __('Male') : __('Female') }} {{ $saudara->wafat ? '(Alm)' : '' }}</div>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        @include('partials.toast-bridge')
    </div>
</section>
