<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Pernikahan;

new class extends Component {
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
                'manager_id' => Auth::user()->id,
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
                'manager_id' => Auth::user()->id,
            ]);
        } elseif ($this->activeForm === 'istri') {
            Pernikahan::create([
                'suami_id' => $this->user->id,
                'istri_id' => $id,
                'manager_id' => Auth::user()->id,
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
                    'manager_id' => Auth::user()->id,
                ]);
            }
        }
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
};
?>
@include('livewire.pengguna._detail', ['user' => $user])
