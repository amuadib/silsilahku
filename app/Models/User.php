<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'gelar_depan',
        'gelar_belakang',
        'email',
        'password',
        'jenis_kelamin',
        'tanggal_lahir',
        'tanggal_wafat',
        'wafat',
        'anak_ke',
        'ayah_id',
        'ibu_id',
        'ortu_id',
        'foto',
        'address_id',
        'memorial_address_id',
        'hp',
        'manager_id'
    ];

    /**
     * Boot model events.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (!empty($user->name) && empty($user->gelar_depan)) {
                if (preg_match('/^(KH\.|K\.H\.|KH)\s+(.+)$/i', $user->name, $m)) {
                    $user->gelar_depan = 'KH.';
                    $user->name = trim($m[2]);
                } elseif (preg_match('/^(Hj\.|Hj)\s+(.+)$/i', $user->name, $m)) {
                    $user->gelar_depan = 'Hj.';
                    $user->name = trim($m[2]);
                } elseif (preg_match('/^(H\.|H)\s+(.+)$/i', $user->name, $m)) {
                    $user->gelar_depan = 'H.';
                    $user->name = trim($m[2]);
                }
            }
        });
    }

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
            'tanggal_wafat' => 'date',
            'wafat' => 'boolean',
        ];
    }

    /**
     * Accessor & Mutator untuk atribut name.
     * Otomatis menyertakan gelar_depan dan gelar_belakang saat dipanggil ($user->name),
     * namun kolom database 'name' tetap menyimpan nama asli tanpa gelar sehingga
     * pengurutan (sorting ->orderBy('name')) tetap berdasarkan nama asli.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes) {
                $rawName = $value ?? ($attributes['name'] ?? '');
                $gelarDepan = $attributes['gelar_depan'] ?? $this->gelar_depan ?? null;
                $gelarBelakang = $attributes['gelar_belakang'] ?? $this->gelar_belakang ?? null;

                $parts = [];
                if (!empty($gelarDepan)) {
                    $parts[] = trim($gelarDepan);
                }
                if (!empty($rawName)) {
                    $parts[] = trim($rawName);
                }

                $fullName = implode(' ', $parts);

                if (!empty($gelarBelakang)) {
                    $gb = trim($gelarBelakang);
                    if (str_starts_with($gb, ',')) {
                        $fullName .= $gb;
                    } else {
                        $fullName .= ($fullName !== '' ? ', ' : '') . $gb;
                    }
                }

                return $fullName !== '' ? $fullName : $rawName;
            },
            set: fn (?string $value) => $value,
        );
    }

    /**
     * Ambil nama asli tanpa gelar.
     */
    public function getNamaAsliAttribute(): string
    {
        return $this->attributes['name'] ?? '';
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $baseName = $this->attributes['name'] ?? $this->name;
        return Str::of($baseName)
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

    public function anak()
    {
        return $this->hasMany(User::class, 'ortu_id', 'id')->where('id', '!=', $this->id);
    }
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id', 'id');
    }
    public function alamat()
    {
        return $this->belongsTo(Address::class,'address_id', 'id');
    }
    public function alamatMakam()
    {
        return $this->belongsTo(Address::class,'memorial_address_id', 'id');
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
     * Get an array of IDs for all users in the full lineage (all ancestors and all descendants).
     * Also includes spouses and siblings of everyone in the lineage.
     */
    public function getRelatedIds(): array
    {
        $relatedIds = [$this->id];
        
        // 1. All Ancestors (Iterative walk up)
        // $currentLayer = [$this->id];
        // while (!empty($currentLayer)) {
        //     $parents = self::whereIn('id', $currentLayer)
        //         ->where(function($q) {
        //             $q->whereNotNull('ayah_id')->orWhereNotNull('ibu_id');
        //         })
        //         ->get(['ayah_id', 'ibu_id'])
        //         ->flatMap(fn($u) => [$u->ayah_id, $u->ibu_id])
        //         ->filter()
        //         ->unique()
        //         ->toArray();
                
        //     $newParents = array_diff($parents, $relatedIds);
        //     if (empty($newParents)) break;
            
        //     $relatedIds = array_merge($relatedIds, $newParents);
        //     $currentLayer = $newParents;
        // }

        // 2. All Descendants (Iterative walk down)
        $currentLayer = [$this->id];
        while (!empty($currentLayer)) {
            $children = self::whereIn('ayah_id', $currentLayer)
                ->orWhereIn('ibu_id', $currentLayer)
                ->pluck('id')
                ->toArray();
                
            $newChildren = array_diff($children, $relatedIds);
            if (empty($newChildren)) break;
            
            $relatedIds = array_merge($relatedIds, $newChildren);
            $currentLayer = $newChildren;
        }
        
        // 3. Spouses and Siblings of everyone currently in relatedIds
        // Using subqueries for better performance on larger sets
        // $siblings = self::whereIn('ortu_id', function($q) use ($relatedIds) {
        //         $q->select('ortu_id')->from('users')->whereIn('id', $relatedIds)->whereNotNull('ortu_id');
        //     })
        //     ->pluck('id')
        //     ->toArray();
        // $relatedIds = array_merge($relatedIds, $siblings);
        
        $spouseIds = Pernikahan::whereIn('suami_id', $relatedIds)->pluck('istri_id')
            ->merge(Pernikahan::whereIn('istri_id', $relatedIds)->pluck('suami_id'))
            ->toArray();
        $relatedIds = array_merge($relatedIds, $spouseIds);
        
        // 4. Managed users (always visible to manager)
        $managedIds = self::where('manager_id', $this->id)->pluck('id')->toArray();
        $relatedIds = array_merge($relatedIds, $managedIds);

        return array_values(array_unique(array_filter($relatedIds)));
    }

    public function isRelatedTo(User $other): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($other->id, $this->getRelatedIds());
    }
}
