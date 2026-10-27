<?php

namespace App\Providers;

use App\Models\User;
use App\Models\WorkspaceApiToken;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
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
        Gate::define('admin.search', fn (User $user): bool => (bool) $user->is_admin);
        Gate::define('viewApiDocs', fn (?User $user): bool => app()->environment(['local', 'testing']));
        RateLimiter::for('api-v1', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();
            $key = $token instanceof WorkspaceApiToken
                ? 'token:'.$token->getKey()
                : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('linkpilot.api.rate_limit_per_minute', 60))->by($key);
        });
        Sanctum::usePersonalAccessTokenModel(WorkspaceApiToken::class);
        ResetPassword::createUrlUsing(function (CanResetPassword $user, string $token): string {
            $baseUrl = rtrim((string) config('app.url'), '/');
            $email = rawurlencode($user->getEmailForPasswordReset());

            return "{$baseUrl}/reset-password/{$token}?email={$email}";
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
