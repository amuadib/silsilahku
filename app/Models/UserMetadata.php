<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserMetadata extends Model
{
    use HasUuids;

    protected $table = 'user_metadata';
    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
