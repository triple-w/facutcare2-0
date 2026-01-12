<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaveUnidad extends Model
{
    protected $table = 'clave_unidad';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['clave', 'descripcion'];
}
