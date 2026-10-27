<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

class SuspendedAccountAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_accounts_cannot_authenticate_with_a_password(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
            'suspended_at' => now(),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_non_suspended_accounts_still_authenticate_with_a_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_accounts_cannot_complete_a_two_factor_login_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create([
            'is_suspended' => true,
            'suspended_at' => now(),
        ]);

        $response = $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1']);

        $response->assertForbidden();

        $this->assertGuest();
        $this->assertStringContainsString(
            'recovery-code-1',
            decrypt($user->refresh()->two_factor_recovery_codes),
        );
    }

    public function test_non_suspended_accounts_can_complete_a_two_factor_login_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_accounts_are_refused_by_the_passkey_login_flow(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        $user = User::factory()->create([
            'is_suspended' => true,
            'suspended_at' => now(),
        ]);
        $passkey = $this->createPasskeyFor($user, 'suspended-credential');
        $this->stubPasskeyVerification($passkey);

        $response = $this->withSession([
            'passkey.verification_options' => $this->verificationOptionsJson(),
        ])->post(route('passkey.login'), $this->passkeyLoginPayload('suspended-credential'));

        $response->assertSessionHasErrors(['credential' => 'Unable to sign in with this account.']);
        $this->assertGuest();
    }

    public function test_non_suspended_accounts_can_authenticate_with_a_passkey(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        $user = User::factory()->create();
        $passkey = $this->createPasskeyFor($user, 'active-credential');
        $this->stubPasskeyVerification($passkey);

        $this->withSession([
            'passkey.verification_options' => $this->verificationOptionsJson(),
        ])->post(route('passkey.login'), $this->passkeyLoginPayload('active-credential'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_passkey_login_authorization_refuses_suspended_identities_before_login(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        $suspended = User::factory()->create(['is_suspended' => true]);
        $active = User::factory()->create();
        $suspendedPasskey = $this->createPasskeyFor($suspended, 'suspended-gate-credential');
        $activePasskey = $this->createPasskeyFor($active, 'active-gate-credential');

        $request = Request::create('/passkeys/login', 'POST');

        $this->assertFalse(Passkeys::allowsLogin($request, $suspendedPasskey));
        $this->assertTrue(Passkeys::allowsLogin($request, $activePasskey));
    }

    public function test_suspended_sessions_receive_forbidden_on_fortify_management_surfaces(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
            'suspended_at' => now(),
        ]);

        $this->actingAs($user)->get(route('password.confirm'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertForbidden();

        if (Features::enabled(Features::twoFactorAuthentication())) {
            $this->actingAs($user)->post(route('two-factor.enable'))->assertForbidden();
            $this->actingAs($user)->get(route('two-factor.qr-code'))->assertForbidden();
            $this->actingAs($user)->get(route('two-factor.secret-key'))->assertForbidden();
            $this->actingAs($user)->get(route('two-factor.recovery-codes'))->assertForbidden();
        }

        if (Features::enabled(Features::passkeys())) {
            $passkey = $this->createPasskeyFor($user, 'management-credential');

            $this->actingAs($user)->get(route('passkey.registration-options'))->assertForbidden();
            $this->actingAs($user)->post(route('passkey.store'), [])->assertForbidden();
            $this->actingAs($user)
                ->delete(route('passkey.destroy', ['passkey' => $passkey->id]))
                ->assertForbidden();
        }
    }

    public function test_non_suspended_sessions_can_still_render_the_password_confirmation_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('password.confirm'))->assertOk();
    }

    public function test_suspended_sessions_can_still_log_out(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
            'suspended_at' => now(),
        ]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    private function createPasskeyFor(User $user, string $credentialId): Passkey
    {
        $model = Passkeys::passkeyModel();

        return $model::query()->forceCreate([
            'user_id' => $user->id,
            'name' => 'Test Passkey',
            'credential_id' => Base64UrlSafe::encodeUnpadded($credentialId),
            'credential' => ['aaguid' => null],
        ]);
    }

    /**
     * Replace only the WebAuthn signature verification step of the passkey
     * login flow; the login authorization decision remains production code.
     */
    private function stubPasskeyVerification(Passkey $passkey): void
    {
        $this->app->instance(VerifyPasskey::class, new class($passkey) extends VerifyPasskey
        {
            public function __construct(private readonly Passkey $passkey)
            {
            }

            public function __invoke(
                PublicKeyCredential $credential,
                PublicKeyCredentialRequestOptions $options,
                ?PasskeyUser $user = null,
            ): Passkey {
                return $this->passkey;
            }
        });
    }

    private function verificationOptionsJson(): string
    {
        return WebAuthn::toJson(PublicKeyCredentialRequestOptions::create(challenge: 'test-challenge'));
    }

    /**
     * @return array<string, mixed>
     */
    private function passkeyLoginPayload(string $credentialId): array
    {
        return [
            'credential' => [
                'id' => Base64UrlSafe::encodeUnpadded($credentialId),
                'rawId' => base64_encode($credentialId),
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => Base64UrlSafe::encodeUnpadded((string) json_encode([
                        'type' => 'webauthn.get',
                        'challenge' => Base64UrlSafe::encodeUnpadded('test-challenge'),
                        'origin' => 'http://localhost',
                    ])),
                    'authenticatorData' => base64_encode(str_repeat("\0", 32)."\x01".pack('N', 0)),
                    'signature' => base64_encode('test-signature'),
                    'userHandle' => base64_encode('test-handle'),
                ],
            ],
            'remember' => false,
        ];
    }
}
