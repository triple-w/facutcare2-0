<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaveProdServ extends Model
{
    protected $table = 'clave_prod_serv';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['clave', 'descripcion'];
}
