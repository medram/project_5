<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->refuseSuspendedAccounts();
        $this->allowSuspendedSessionsToLogOut();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Reject suspended accounts in every authentication path.
     */
    private function refuseSuspendedAccounts(): void
    {
        // Password authentication (including the step that arms the two-factor
        // challenge) refuses suspended accounts like any other invalid login.
        Fortify::authenticateUsing(function (Request $request): ?Authenticatable {
            $provider = Auth::guard(config('fortify.guard'))->getProvider();

            $user = $provider->retrieveByCredentials([
                Fortify::username() => $request->input(Fortify::username()),
                'password' => $request->input('password'),
            ]);

            if (! $user || ! $provider->validateCredentials($user, ['password' => $request->input('password')])) {
                return null;
            }

            if (config('hashing.rehash_on_login', true) && method_exists($provider, 'rehashPasswordIfRequired')) {
                $provider->rehashPasswordIfRequired($user, ['password' => $request->input('password')]);
            }

            return EnsureActiveAccount::isSuspended($user) ? null : $user;
        });

        // Passkey login consults this authorization before creating the session.
        Passkeys::authorizeLoginUsing(
            fn (Request $request, PasskeyUser $user, Passkey $passkey): bool => ! EnsureActiveAccount::isSuspended($user),
        );

        // A two-factor challenge armed before suspension must not complete the
        // login; the challenged identity is refused before any code is checked.
        $this->app->bind(TwoFactorLoginRequest::class, fn (): TwoFactorLoginRequest => new class extends TwoFactorLoginRequest
        {
            public function challengedUser(): mixed
            {
                $user = parent::challengedUser();

                abort_if(EnsureActiveAccount::isSuspended($user), 403);

                return $user;
            }
        });
    }

    /**
     * The active-account guard covers Fortify's authenticated management
     * routes; logging out must stay possible for a stale suspended session.
     */
    private function allowSuspendedSessionsToLogOut(): void
    {
        $this->app->booted(function (): void {
            foreach (Route::getRoutes() as $route) {
                if ($route->getName() !== 'logout') {
                    continue;
                }

                $action = $route->getAction();
                $action['middleware'] = array_values(array_diff($action['middleware'] ?? [], ['active-account']));
                $route->setAction($action);
            }
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
