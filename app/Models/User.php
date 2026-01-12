<?php

namespace App\Models;

// En FactuCare legacy la validación de correo se maneja con el campo `verified`.
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    

    // La tabla legacy NO tiene updated_at (y la mayoría de las tablas tampoco)
    public $timestamps = false;

    protected $table = 'users';
    protected $primaryKey = 'id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'username',
        'password',
        'verified',
        'active',
        'rol',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
        'verified' => 'boolean',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Jetstream/Fortify suelen usar el atributo `name`.
     * En el esquema legacy el campo se llama `username` (RFC/Usuario).
     */
    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['username'] ?? '');
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['username'] = $value;
    }
}
