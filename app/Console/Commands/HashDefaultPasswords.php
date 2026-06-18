<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HashDefaultPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:hash-default';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hashea todas las contraseñas que sean 12345678';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $defaultPassword = '12345678';
        $users = \App\Models\User::where('password', $defaultPassword)->get();
        $count = $users->count();

        if ($count === 0) {
            $this->info('No se encontraron usuarios con la contraseña por defecto.');
            return Command::SUCCESS;
        }

        $this->info("Hasheando $count contraseñas...");

        foreach ($users as $user) {
            $user->password = \Illuminate\Support\Facades\Hash::make($defaultPassword);
            $user->save();
        }

        $this->info('Proceso completado exitosamente.');
        return Command::SUCCESS;
    }
}
