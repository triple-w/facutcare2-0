<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Models\User;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Login legacy FactuCare:
        // - el usuario escribe RFC/usuario (columna `username`)
        // - validamos active/verified
        // - soportamos password bcrypt (y opcionalmente legacy MD5 si existiera)
        Fortify::authenticateUsing(function (Request $request) {
            $login = trim((string) $request->input('username'));
            $password = (string) $request->input('password');

            if ($login === '' || $password === '') {
                return null;
            }

            /** @var User|null $user */
            $user = User::query()
                ->where('username', $login)
                ->first();

            if (! $user) {
                return null;
            }

            // Reglas mínimas de acceso
            if ((int) $user->active !== 1 || (int) $user->verified !== 1) {
                return null;
            }

            $stored = (string) $user->password;

            // Caso normal (bcrypt)
            if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
                return Hash::check($password, $stored) ? $user : null;
            }

            // Fallback compatibilidad: si por alguna razón el legacy usaba MD5.
            // Si no lo usas, no pasa nada: nunca matchea.
            return hash('md5', $password) === $stored ? $user : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
