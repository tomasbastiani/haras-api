<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaqueteriaOperario extends Model
{
    protected $table = 'paqueteria_operarios';

    protected $fillable = ['user_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function esOperario(int $userId): bool
    {
        return static::where('user_id', $userId)->where('activo', true)->exists();
    }
}
