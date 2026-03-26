@props(['user', 'newFoto' => null, 'showUpload' => true])

<div class="flex flex-col items-center">
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
    
    @if ($showUpload)
        <flux:input wire:model="newFoto" type="file" accept="image/*" />
    @endif
</div>
