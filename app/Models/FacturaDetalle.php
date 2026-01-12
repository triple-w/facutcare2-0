<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $table = 'factura_detalles';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'users_facturas_id',
        'clave','unidad','precio','cantidad','importe','descripcion',
        'desglosado','observaciones',
        'nuevoPrecio','iva',
        'numero_clave_prod','numero_clave_unidad',
    ];

    protected $casts = [
        'users_facturas_id' => 'integer',
        'precio' => 'decimal:2',
        'importe' => 'decimal:2',
        'nuevoPrecio' => 'decimal:2',
        'iva' => 'decimal:2',
        'cantidad' => 'integer',
        'desglosado' => 'boolean',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'users_facturas_id');
    }
}
