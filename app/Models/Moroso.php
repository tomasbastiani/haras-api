<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Moroso extends Model
{
    // Laravel asume tabla "morosos", así que no hace falta $table
    protected $fillable = [
        'email',
        'nlote',
        'monto',
        // otros campos si los tenés
    ];

    public $timestamps = false;
}
