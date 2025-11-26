<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoPago extends Model
{
    // Laravel asume tabla "info_pagos" para "InfoPago"
    protected $table = 'info_pagos';

    protected $fillable = [
        'nlote',
        'cvu',
        'alias',
        // otros campos si los tenés
    ];

    public $timestamps = false;
}
