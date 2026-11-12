<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $blocked = (string) (app(PlatformSettings::class)->resolved()['authentication']['blocked_email_domains'] ?? '');
        $domain = strtolower((string) substr(strrchr((string) $input['email'], '@') ?: '', 1));
        $blockedDomains = array_filter(array_map('trim', preg_split('/\R/', $blocked) ?: []));

        if ($domain !== '' && in_array($domain, array_map('strtolower', $blockedDomains), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'This email domain is not allowed for registration.',
            ]);
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
