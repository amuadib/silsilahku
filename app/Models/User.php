<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasUuids, LogsActivity;

    /**
     * Kolom sensitif yang tidak dicatat di activity log.
     */
    protected array $logExclude = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'jenis_kelamin',
        'tanggal_lahir',
        'wafat',
        'anak_ke',
        'ayah_id',
        'ibu_id',
        'ortu_id',
        'foto',
        'alamat',
        'hp',
        'foto',
        'manager_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
            'wafat' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function ayah()
    {
        return $this->belongsTo(User::class, 'ayah_id', 'id');
    }

    public function ibu()
    {
        return $this->belongsTo(User::class, 'ibu_id', 'id');
    }

    public function orangTua()
    {
        return $this->hasOne(Pernikahan::class, 'id', 'ortu_id');
    }

    public function istri()
    {
        return $this->hasManyThrough(
            User::class, 
            Pernikahan::class, 
            'suami_id', // Foreign key on the Pernikahan table...
            'id', // Foreign key on the Users table...
            'id', // Local key on the Users table...
            'istri_id' // Local key on the Pernikahan table...
        );
    }

    public function suami()
    {
        return $this->hasManyThrough(
            User::class, 
            Pernikahan::class, 
            'istri_id', // Foreign key on the Pernikahan table...
            'id', // Foreign key on the Users table...
            'id', // Local key on the Users table...
            'suami_id' // Local key on the Pernikahan table...
        );
    }

    public function pernikahan()
    {
        if ($this->jenis_kelamin == '1') {
            return $this->hasMany(Pernikahan::class, 'suami_id', 'id');
        } else {
            return $this->hasMany(Pernikahan::class, 'istri_id', 'id');
        }
    }

    public function saudara()
    {
            return $this->hasMany(User::class, 'ortu_id', 'id')->where('id', '!=', $this->id);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id', 'id');
    }
    public function isAdmin()
    {
        return in_array($this->email, explode(';', env('ADMIN_EMAILS')));
    }
    public function isManager()
    {
        return $this->manager_id === Auth::user()->id;
    }
    /**
     * Determine if the user has a family relationship with another user.
     */
    public function isRelatedTo(User $other): bool
    {
        // 1. Same person
        if ($this->id === $other->id) {
            return true;
        }

        // 2. Manager (the one who created/manages this entry)
        if ($this->id === $other->manager_id) {
            return true;
        }

        // 3. Direct parents
        if ($this->id === $other->ayah_id || $this->id === $other->ibu_id) {
            return true;
        }

        // 4. Children (check if other is parent of $this)
        if ($other->id === $this->ayah_id || $other->id === $this->ibu_id) {
            return true;
        }

        // 5. Siblings (share same parents/pernikahan)
        if ($this->ortu_id && $this->ortu_id === $other->ortu_id) {
            return true;
        }

        // 6. Spouse
        if ($this->jenis_kelamin == '1') {
            if ($this->istri()->where('users.id', $other->id)->exists()) {
                return true;
            }
        } else {
            if ($this->suami()->where('users.id', $other->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
