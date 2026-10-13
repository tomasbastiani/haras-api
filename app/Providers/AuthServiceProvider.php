<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Un token con habilidades acotadas (hoy sólo el del MCP, `mcp:read`)
        // vale únicamente en /admin/mcp/*. Sin esto Sanctum lo aceptaría en
        // cualquier ruta que no chequee habilidades, que son casi todas: un
        // token filtrado de la config de Claude Desktop serviría para borrar
        // períodos de expensas o mandar mails masivos. Los tokens de login
        // (`spa`) tienen '*' y no se ven afectados.
        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid) {
            if (! $isValid || $token->can('*')) {
                return $isValid;
            }

            return request()->is('api/admin/mcp/*');
        });
    }
}
