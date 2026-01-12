<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'users_id',
        'clave',
        'unidad',
        'precio',
        'descripcion',
        'observaciones',
        'clave_prod_serv_id',
        'clave_unidad_id',
    ];

    protected $casts = [
        'users_id' => 'integer',
        'clave_prod_serv_id' => 'integer',
        'clave_unidad_id' => 'integer',
        'precio' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function prodServ(): BelongsTo
    {
        return $this->belongsTo(ClaveProdServ::class, 'clave_prod_serv_id');
    }

    public function unidadSat(): BelongsTo
    {
        return $this->belongsTo(ClaveUnidad::class, 'clave_unidad_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('users_id', $userId);
    }
}
