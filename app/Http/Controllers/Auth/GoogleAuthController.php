<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Accounts\GoogleConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleConnection $google) {}

    public function redirect(): RedirectResponse
    {
        $this->ensureEnabled();

        return $this->driver()
            ->redirectUrl(route('google.callback'))
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $googleUser = $this->googleUser(route('google.callback'));

        if (! $googleUser instanceof SocialiteUser) {
            return redirect()->route('login')->withErrors(['google' => 'Google sign-in could not be completed.']);
        }

        $account = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_user_id', (string) $googleUser->getId())
            ->first();

        if ($account !== null) {
            $user = $account->user;

            if ($user->is_suspended || $user->deletion_requested_at !== null) {
                return redirect()->route('login')->withErrors(['google' => 'This account is not available.']);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        $email = $googleUser->getEmail();
        $verified = (bool) ($googleUser->getRaw()['email_verified'] ?? false);

        if (! is_string($email) || $email === '' || ! $verified) {
            return redirect()->route('login')->withErrors(['google' => 'Google did not provide a verified email address.']);
        }

        if (User::query()->where('email', $email)->exists()) {
            return redirect()->route('login')->withErrors([
                'google' => 'Sign in with your password, then connect Google from account settings.',
            ]);
        }

        if (! Features::enabled(Features::registration())) {
            return redirect()->route('login')->withErrors(['google' => 'Account registration is disabled.']);
        }

        $user = User::query()->create([
            'name' => $googleUser->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::random(40),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => (string) $googleUser->getId(),
            'email' => $email,
            'email_verified' => true,
            'linked_at' => now(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function connect(): RedirectResponse
    {
        $this->ensureEnabled();

        return $this->driver()
            ->redirectUrl(route('google.connect.callback'))
            ->redirect();
    }

    public function connectCallback(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $googleUser = $this->googleUser(route('google.connect.callback'));
        $user = $request->user();

        if (! $googleUser instanceof SocialiteUser || $user === null) {
            return to_route('profile.edit')->withErrors(['google' => 'Google connection could not be completed.']);
        }

        $email = $googleUser->getEmail();
        $verified = (bool) ($googleUser->getRaw()['email_verified'] ?? false);

        if (! $verified || ! is_string($email) || $email === '') {
            return to_route('profile.edit')->withErrors(['google' => 'Google did not provide a verified email address.']);
        }

        $providerUserId = (string) $googleUser->getId();

        $linkedElsewhere = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->where('user_id', '!=', $user->getKey())
            ->exists();

        if ($linkedElsewhere) {
            return to_route('profile.edit')->withErrors(['google' => 'That Google account is already connected to another LinkPilot account.']);
        }

        if ($user->socialAccounts()->where('provider', 'google')->exists()) {
            return to_route('profile.edit')->withErrors(['google' => 'A Google account is already connected. Disconnect it first.']);
        }

        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => $providerUserId,
            'email' => $email,
            'email_verified' => true,
            'linked_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Google account connected.')]);

        return to_route('profile.edit');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $request->user()?->socialAccounts()->where('provider', 'google')->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Google account disconnected.')]);

        return to_route('profile.edit');
    }

    private function driver(): mixed
    {
        return Socialite::driver('google');
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->google->enabled(), 404);
    }

    private function googleUser(string $redirectUrl): ?SocialiteUser
    {
        try {
            $user = $this->driver()
                ->redirectUrl($redirectUrl)
                ->user();

            return $user instanceof SocialiteUser ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }
}
