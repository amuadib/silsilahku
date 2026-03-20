<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public $user;
    public ?string $activeForm = null;
    public $userLakiLaki;
    public $userPerempuan;

    public function mount()
    {
        $this->user = auth()->user();
        $this->userLakiLaki = User::where('jenis_kelamin', '1')->get();
        $this->userPerempuan = User::where('jenis_kelamin', '0')->get();
    }

    public function setHubungan($hubungan)
    {
        $this->activeForm = $hubungan;
    }

    public function save()
    {
        dd($this->userFromSelection, $this->userFromInput, $this->hubungan);
        $this->user->update([
            'ayah_id' => $this->userFromSelection,
            'ayah_input' => $this->userFromInput,
        ]);
        $this->dispatch('notify', message: __('Profile updated successfully.'), type: 'success');
    }

    public function delete(User $user)
    {
        $this->authorize('delete', $user);
        $user->delete();
        session()->flash('success', __('Data deleted successfully.'));
        return $this->redirect(route('dashboard'), navigate: true);
    }
};

?>
@include('livewire.pengguna._detail', ['user' => $user])
