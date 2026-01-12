<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Folio extends Model
{
    protected $table = 'folios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'users_id',
        'tipo',
        'serie',
        'folio',
    ];

    protected $casts = [
        'users_id' => 'integer',
        'folio' => 'integer',
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
