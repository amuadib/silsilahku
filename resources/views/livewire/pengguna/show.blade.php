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

        $this->pasanganPernikahan = Pernikahan::with('suami', 'istri')->get()->mapWithKeys(fn($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])->toArray();

        $this->pasanganPernikahanUser = Pernikahan::where('suami_id', $this->user->id)->orWhere('istri_id', $this->user->id)->with('suami', 'istri')->get()->mapWithKeys(fn($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])->toArray();
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
        if ($this->userFromSelection) {
            return $this->userFromSelection;
        }

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

    public function removeHubungan(string $hubungan): void
    {
        $this->authorize('update', $this->user);

        match ($hubungan) {
            'ayah'     => $this->user->update(['ayah_id' => null]),
            'ibu'      => $this->user->update(['ibu_id' => null]),
            'orangtua' => $this->user->update(['ortu_id' => null, 'ayah_id' => null, 'ibu_id' => null]),
            default    => null,
        };

        $this->user->refresh();
        $this->toastSuccess(__('Relationship removed successfully.'));
    }

    public function removePernikahan(string $spouseId): void
    {
        $this->authorize('update', $this->user);

        if ($this->user->jenis_kelamin == '1') {
            // user = suami, spouse = istri
            Pernikahan::where('suami_id', $this->user->id)
                ->where('istri_id', $spouseId)
                ->delete();
        } else {
            // user = istri, spouse = suami
            Pernikahan::where('istri_id', $this->user->id)
                ->where('suami_id', $spouseId)
                ->delete();
        }

        $this->user->refresh();
        $this->toastSuccess(__('Relationship removed successfully.'));
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

<section class="mx-auto w-full max-w-7xl px-6 lg:px-8">
    <div class="w-full">
        <div class="relative my-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl" level="1">{{ $user->name }} {{ $user->wafat ? '(Alm)' : '' }}
                </flux:heading>
                <flux:subheading size="lg" class="mt-1">{{ __('Profile Detail') }}</flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button color="zinc" href="{{ route('pengguna.daftar', $user->id) }}" wire:navigate
                    variant="outline" icon="users">
                    {{ __('List of Family members') }}
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate
                    variant="outline" icon="share">
                    {{ __('Family Tree') }}
                </flux:button>
                <flux:button color="cyan" href="{{ route('pengguna.hubungan') }}" wire:navigate variant="outline"
                    icon="arrows-right-left">
                    {{ __('Find Relationship') }}
                </flux:button>
                @can('update', $user)
                    <flux:button color="yellow" href="{{ route('pengguna.edit', $user->id) }}" wire:navigate
                        variant="primary" icon="pencil">
                        {{ __('Edit') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-3">
            {{-- Profile Card --}}
            <div class="space-y-6 lg:col-span-1">
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div
                        class="flex flex-col items-center border-b bg-gray-50 p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <x-profile-photo :user="$user" :showUpload="false" />
                        <flux:heading size="lg" class="mt-4">{{ $user->name }}
                            {{ $user->wafat ? '(Alm)' : '' }}</flux:heading>
                        <flux:badge color="{{ $user->jenis_kelamin == '1' ? 'blue' : 'pink' }}" size="sm"
                            class="mt-1">
                            {{ $user->jenis_kelamin == '1' ? __('Male') : __('Female') }}
                        </flux:badge>
                    </div>

                    <div class="space-y-3 p-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">{{ __('Birthday') }}</span>
                            <span>{{ $user->tanggal_lahir?->format('d M Y') ?: '-' }}</span>
                        </div>
                        @if ($user->wafat)
                            <div class="flex justify-between border-t pt-3 dark:border-zinc-700">
                                <span class="mb-1 block text-gray-500 dark:text-zinc-400">{{ __('Deathday') }}</span>
                                <span>{{ $user->tanggal_wafat?->format('d M Y') ?: '-' }}</span>
                            </div>
                            <div class="border-t pt-3 dark:border-zinc-700">
                                <span
                                    class="mb-1 block text-gray-500 dark:text-zinc-400">{{ __('Memorial Address') }}</span>
                                <span class="text-xs">{{ $user->alamatMakam?->full_address ?: '-' }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between border-t pt-3 dark:border-zinc-700">
                            <span class="text-gray-500 dark:text-zinc-400">{{ __('Age') }}</span>
                            <span>
                                @if ($user->wafat)
                                    {{ $user->tanggal_lahir?->diffForHumans($user->tanggal_wafat, true, true) ?: '-' }}
                                @else
                                    {{ $user->tanggal_lahir?->diffForHumans(null, true, true) ?: '-' }}
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between border-t pt-3 dark:border-zinc-700">
                            <span class="text-gray-500 dark:text-zinc-400">{{ __('Birth order') }}</span>
                            <span>{{ $user->anak_ke ?: '-' }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-3 dark:border-zinc-700">
                            <span class="mb-1 block text-gray-500 dark:text-zinc-400">{{ __('Email') }}</span>
                            <span>{{ $user->email ?: '-' }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-3 dark:border-zinc-700">
                            <span class="mb-1 block text-gray-500 dark:text-zinc-400">{{ __('Phone') }}</span>
                            <span>{{ $user->hp ?: '-' }}</span>
                        </div>
                        <div class="border-t pt-3 dark:border-zinc-700">
                            <span class="mb-1 block text-gray-500 dark:text-zinc-400">{{ __('Address') }}</span>
                            <span class="text-xs">{{ $user->alamat?->full_address ?: '-' }}</span>
                        </div>
                    </div>

                    @can('delete', $user)
                        <div class="border-t bg-red-50 p-4 dark:border-zinc-700 dark:bg-red-900/20">
                            <flux:button color="red" variant="ghost" class="w-full cursor-pointer"
                                wire:click="delete('{{ $user->id }}')" wire:confirm="{{ __('Are you sure?') }}">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    @endcan
                </div>
            </div>

            {{-- Family Section --}}
            <div class="space-y-8 lg:col-span-2">
                {{-- Relationships --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="xl"
                        class="border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                        {{ __('Family Relationships') }}
                    </flux:heading>

                    <div class="divide-y dark:divide-zinc-700">
                        {{-- Father --}}
                        <div class="flex flex-col justify-between gap-4 p-4 sm:flex-row sm:items-center">
                            <div class="w-32 font-medium text-gray-500 dark:text-zinc-400">{{ __('Father') }}</div>
                            <div class="flex-1">
                                @if ($user->ayah)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('pengguna.show', $user->ayah->id) }}"
                                            class="font-semibold text-blue-600 hover:underline">
                                            {{ $user->ayah->name }} {{ $user->ayah->wafat ? '(Alm)' : '' }}
                                        </a>
                                        @can('update', $user)
                                            <flux:button icon="x-mark" size="xs" variant="ghost" color="red"
                                                class="cursor-pointer"
                                                wire:click="removeHubungan('ayah')"
                                                wire:confirm="{{ __('Remove father relationship?') }}"
                                                title="{{ __('Remove') }}" />
                                        @endcan
                                    </div>
                                @endif

                                @if ($activeForm === 'ayah')
                                    <div
                                        class="mt-4 rounded border bg-gray-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', ['userList' => $userLakiLaki])
                                    </div>
                                @elseif (!$user->ayah && Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('ayah')"
                                        class="cursor-pointer">
                                        {{ __('Set') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Mother --}}
                        <div class="flex flex-col justify-between gap-4 p-4 sm:flex-row sm:items-center">
                            <div class="w-32 font-medium text-gray-500 dark:text-zinc-400">{{ __('Mother') }}</div>
                            <div class="flex-1">
                                @if ($user->ibu)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('pengguna.show', $user->ibu->id) }}"
                                            class="font-semibold text-blue-600 hover:underline">
                                            {{ $user->ibu->name }} {{ $user->ibu->wafat ? '(Alm)' : '' }}
                                        </a>
                                        @can('update', $user)
                                            <flux:button icon="x-mark" size="xs" variant="ghost" color="red"
                                                class="cursor-pointer"
                                                wire:click="removeHubungan('ibu')"
                                                wire:confirm="{{ __('Remove mother relationship?') }}"
                                                title="{{ __('Remove') }}" />
                                        @endcan
                                    </div>
                                @endif

                                @if ($activeForm === 'ibu')
                                    <div
                                        class="mt-4 rounded border bg-gray-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', ['userList' => $userPerempuan])
                                    </div>
                                @elseif (!$user->ibu && Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('ibu')"
                                        class="cursor-pointer">
                                        {{ __('Set') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Parents (Couple) --}}
                        <div class="flex flex-col justify-between gap-4 p-4 sm:flex-row sm:items-center">
                            <div class="w-32 font-medium text-gray-500 dark:text-zinc-400">{{ __('Parent') }}</div>
                            <div class="flex-1">
                                @if ($user->orangTua)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('pengguna.show', $user->orangTua->suami->id) }}"
                                            class="text-blue-600 hover:underline">
                                            {{ $user->orangTua->suami->name }}
                                            {{ $user->orangTua->suami->wafat ? '(Alm)' : '' }}
                                        </a>
                                        <span class="text-gray-400 dark:text-zinc-500">&</span>
                                        <a href="{{ route('pengguna.show', $user->orangTua->istri->id) }}"
                                            class="text-blue-600 hover:underline">
                                            {{ $user->orangTua->istri->name }}
                                            {{ $user->orangTua->istri->wafat ? '(Alm)' : '' }}
                                        </a>
                                        @can('update', $user)
                                            <flux:button icon="x-mark" size="xs" variant="ghost" color="red"
                                                class="cursor-pointer"
                                                wire:click="removeHubungan('orangtua')"
                                                wire:confirm="{{ __('Remove parent relationship? This will also clear father and mother.') }}"
                                                title="{{ __('Remove') }}" />
                                        @endcan
                                    </div>
                                @endif

                                @if ($activeForm === 'orangtua')
                                    <div
                                        class="mt-4 rounded border bg-gray-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', [
                                            'userList' => $pasanganPernikahan,
                                        ])
                                    </div>
                                @elseif (!$user->orangTua && Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs" wire:click="setHubungan('orangtua')"
                                        class="cursor-pointer">
                                        {{ __('Set') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Spouses --}}
                        <div class="sm:items-top flex flex-col justify-between gap-4 p-4 sm:flex-row">
                            <div class="w-32 font-medium text-gray-500 dark:text-zinc-400">
                                {{ $user->jenis_kelamin == '1' ? __('Wife') : __('Husband') }}</div>
                            <div class="flex-1 space-y-1">
                                @php $spouses = $user->jenis_kelamin == '1' ? $user->istri : $user->suami; @endphp
                                @foreach ($spouses as $spouse)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('pengguna.show', $spouse->id) }}"
                                            class="font-medium text-blue-600 hover:underline">
                                            {{ $spouse->name }} {{ $spouse->wafat ? '(Alm)' : '' }}
                                        </a>
                                        @can('update', $user)
                                            <flux:button icon="x-mark" size="xs" variant="ghost" color="red"
                                                class="cursor-pointer"
                                                wire:click="removePernikahan('{{ $spouse->id }}')"
                                                wire:confirm="{{ __('Remove this marriage relationship?') }}"
                                                title="{{ __('Remove') }}" />
                                        @endcan
                                    </div>
                                @endforeach

                                @if ($activeForm === ($user->jenis_kelamin == '1' ? 'istri' : 'suami'))
                                    <div
                                        class="mt-4 rounded border bg-gray-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        @include('livewire.pengguna.form', [
                                            'userList' =>
                                                $user->jenis_kelamin == '1' ? $userPerempuan : $userLakiLaki,
                                        ])
                                    </div>
                                @elseif (Gate::allows('update', $user))
                                    <flux:button icon="plus" size="xs"
                                        wire:click="setHubungan('{{ $user->jenis_kelamin == '1' ? 'istri' : 'suami' }}')"
                                        class="cursor-pointer">
                                        {{ __('Add') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Children --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div
                        class="flex items-center justify-between border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                        <flux:heading size="xl">{{ __('Children') }}</flux:heading>
                        @can('update', $user)
                            <flux:button size="xs" wire:click="setHubungan('anak')" color="green" icon="plus"
                                class="cursor-pointer">{{ __('Add') }}</flux:button>
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
                                            @if ($user->pernikahan->count() > 1)
                                                <div
                                                    class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500">
                                                    {{ __('With') }}
                                                    {{ $user->id === $pernikahan->suami_id ? $pernikahan->istri->name : $pernikahan->suami->name }}
                                                </div>
                                            @endif
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                @foreach ($pernikahan->anak as $anak)
                                                    <a href="{{ route('pengguna.show', $anak->id) }}"
                                                        class="flex items-center gap-3 rounded border p-3 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                                                        <flux:avatar size="xs"
                                                            :src="$anak->foto ? asset('storage/'.$anak->foto) : ($anak->jenis_kelamin == '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg'))" />
                                                        <div class="min-w-0 flex-1">
                                                            <div class="truncate text-sm font-medium">
                                                                {{ $anak->anak_ke }}. {{ $anak->name }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-zinc-400">
                                                                {{ $anak->jenis_kelamin == '1' ? __('Male') : __('Female') }}
                                                                {{ $anak->wafat ? '(Alm)' : '' }}</div>
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
                            <div class="py-8 text-center italic text-gray-400 dark:text-zinc-500">
                                {{ __('No children listed') }}</div>
                        @endif

                        @if ($activeForm === 'anak')
                            <div
                                class="mt-6 rounded-lg border-2 border-dashed bg-gray-50 p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
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
                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="xl"
                            class="border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                            {{ __('Siblings') }}</flux:heading>
                        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                            @foreach ($user->orangTua->anak as $saudara)
                                @if ($saudara->id !== $user->id)
                                    <a href="{{ route('pengguna.show', $saudara->id) }}"
                                        class="flex items-center gap-3 rounded border p-3 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                                        <flux:avatar size="xs"
                                            :src="$saudara->foto ? asset('storage/'.$saudara->foto) : ($saudara->jenis_kelamin == '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg'))" />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-medium">{{ $saudara->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-zinc-400">
                                                {{ $saudara->jenis_kelamin == '1' ? __('Male') : __('Female') }}
                                                {{ $saudara->wafat ? '(Alm)' : '' }}</div>
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
