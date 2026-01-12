<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'id';

    // Tu tabla no tiene created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'rfc',
        'razon_social',
        'calle',
        'no_ext',
        'no_int',
        'colonia',
        'municipio',
        'localidad',
        'estado',
        'codigo_postal',
        'pais',
        'telefono',
        'nombre_contacto',
        'email',
        'users_id',
        'regimen_fiscal',
    ];

    protected $casts = [
        'users_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('users_id', $userId);
    }
}
