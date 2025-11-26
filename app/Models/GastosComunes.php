<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastosComunes extends Model
{
    protected $table = 'gastoscomunes';

    protected $fillable = [
        'email',
        'nlote',
        // agregá campos que quieras usar
    ];

    public $timestamps = true; // si tu tabla no tiene created_at/updated_at
}
