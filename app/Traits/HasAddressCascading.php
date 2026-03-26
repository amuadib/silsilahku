<?php

namespace App\Traits;

use App\Models\Provinsi;
use App\Models\KabupatenKota;
use App\Models\Kecamatan;
use App\Models\DesaKelurahan;

trait HasAddressCascading
{
    public $selectedProvinsi = '';
    public $selectedKabupaten = '';
    public $selectedKecamatan = '';
    public $selectedDesa = '';

    public $provinsis = [];
    public $kabupatens = [];
    public $kecamatans = [];
    public $desas = [];

    public function loadProvinsis()
    {
        $this->provinsis = Provinsi::orderBy('nama')->get();
    }

    public function updatedSelectedProvinsi($value)
    {
        $this->kabupatens = KabupatenKota::where('kode_provinsi', $value)->orderBy('nama')->get();
        $this->resetAddressDropdowns(['selectedKabupaten', 'selectedKecamatan', 'selectedDesa']);
        $this->kecamatans = [];
        $this->desas = [];
    }

    public function updatedSelectedKabupaten($value)
    {
        $this->kecamatans = Kecamatan::where('kode_kabupaten_kota', $value)->orderBy('nama')->get();
        $this->resetAddressDropdowns(['selectedKecamatan', 'selectedDesa']);
        $this->desas = [];
    }

    public function updatedSelectedKecamatan($value)
    {
        $this->desas = DesaKelurahan::where('kode_kecamatan', $value)->orderBy('nama')->get();
        $this->resetAddressDropdowns(['selectedDesa']);
    }

    protected function resetAddressDropdowns(array $fields)
    {
        foreach ($fields as $field) {
            $this->{$field} = '';
        }
    }
}
