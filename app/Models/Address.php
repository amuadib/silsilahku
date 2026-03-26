<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Address extends Model
{
    use HasUuids;

    protected $fillable = [
        'alamat',
        'kode_desa',
        'nama_desa',
        'nama_kecamatan',
        'nama_kabupaten',
        'nama_provinsi',
        'kode_pos',
        'lat',
        'lng'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function memorialUsers()
    {
        return $this->hasMany(User::class, 'memorial_address_id');
    }

    public function getFullAddressAttribute()
    {
        $parts = [];
        if ($this->alamat) $parts[] = $this->alamat;
        if ($this->nama_desa) $parts[] = $this->nama_desa;
        if ($this->nama_kecamatan) $parts[] = $this->nama_kecamatan;
        if ($this->nama_kabupaten) $parts[] = $this->nama_kabupaten;
        if ($this->nama_provinsi) $parts[] = $this->nama_provinsi;
        return implode(', ', $parts);
    }
}
