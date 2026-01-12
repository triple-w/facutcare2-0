<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Factura extends Model
{
    protected $table = 'facturas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'users_id',
        'rfc','razon_social','calle','no_ext','no_int','colonia','municipio','localidad','estado','codigo_postal','pais',
        'telefono','nombre_contacto',
        'estatus','id_cancelar',
        'fecha','fecha_factura',
        'xml','pdf','solicitud_timbre','acuse',
        'descuento','uuid',
        'nombre_comprobante','tipo_comprobante',
        'comentarios_pdf',
    ];

    protected $casts = [
        'users_id' => 'integer',
        'descuento' => 'decimal:2',
        'fecha' => 'datetime',
        'fecha_factura' => 'datetime',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class, 'users_facturas_id');
    }

    public function impuestos(): HasMany
    {
        return $this->hasMany(FacturaImpuesto::class, 'users_facturas_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('users_id', $userId);
    }
}
