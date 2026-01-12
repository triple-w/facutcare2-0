<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaImpuesto extends Model
{
    protected $table = 'facturas_impuestos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'users_facturas_id',
        'impuesto','tipo','tasa','monto',
    ];

    protected $casts = [
        'users_facturas_id' => 'integer',
        'tasa' => 'integer',
        'monto' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'users_facturas_id');
    }
}
