<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; 
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pernikahan extends Model
{
    use HasUuids;
    protected $table = 'pernikahan';

    protected $fillable = [
        'suami_id',
        'istri_id',
        'tanggal_menikah',
        'tempat_menikah',
        'tanggal_cerai',
    ];

    public function suami()
    {
        return $this->belongsTo(User::class, 'suami_id', 'id');
    }

    public function istri()
    {
        return $this->belongsTo(User::class, 'istri_id', 'id');
    }
    
    public function anak()
    {
        return $this->hasMany(User::class,'ortu_id', 'id')->orderBy('anak_ke', 'asc');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id', 'id');
    }
}
