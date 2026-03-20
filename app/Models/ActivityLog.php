<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Pengguna yang melakukan aksi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Nama model yang mudah dibaca (tanpa namespace).
     */
    public function modelLabel(): string
    {
        return class_basename($this->model_type);
    }

    /**
     * Label aksi dalam Bahasa Indonesia.
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'created' => 'Tambah',
            'updated' => 'Ubah',
            'deleted' => 'Hapus',
            default   => ucfirst($this->action),
        };
    }

    /**
     * Warna badge untuk aksi.
     */
    public function actionColor(): string
    {
        return match ($this->action) {
            'created' => 'green',
            'updated' => 'yellow',
            'deleted' => 'red',
            default   => 'zinc',
        };
    }
}
