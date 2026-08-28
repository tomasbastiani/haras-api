<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /**
     * `paqueteria` queda fuera de $fillable a propósito: es un rango, no un
     * dato del perfil. Se asigna explícitamente desde el controlador de admin
     * para que ningún create()/update() masivo pueda otorgarlo por accidente.
     *
     * `admin` no se castea: el front lo compara con === 1 y castearlo a boolean
     * cambiaría el JSON del login.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'paqueteria'        => 'boolean',
    ];

    /**
     * Cuenta dedicada de paquetería (portería): entra directo a la oficina y no
     * ve gastos comunes, lotes, turnero ni archivos.
     *
     * Distinto de PaqueteriaOperario, que es el permiso aditivo de un vecino
     * que además atiende la oficina.
     */
    public function esPaqueteria(): bool
    {
        return (bool) $this->paqueteria;
    }

    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }
}
