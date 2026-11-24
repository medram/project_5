<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\Translation;
use App\Models\User;
use App\Services\Localization\TranslationRepository;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_publish_and_edit_locales_and_translations(): void
    {
        $administrator = User::factory()->admin()->create();
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/localizations')->assertForbidden();

        $this->actingAs($administrator)->post('/admin/localizations', [
            'code' => 'fr',
            'name' => 'French',
            'direction' => 'ltr',
            'is_published' => true,
        ])->assertRedirect();

        $locale = Locale::query()->where('code', 'fr')->sole();
        $this->actingAs($administrator)->put("/admin/localizations/{$locale->code}/translations", [
            'key' => "Let's get started",
            'value' => 'Commençons',
        ])->assertRedirect();

        $this->assertDatabaseHas('translations', [
            'locale_id' => $locale->id,
            'key' => "Let's get started",
            'value' => 'Commençons',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'create_locale']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'update_translation']);

        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'localization', [
            'translations_enabled' => true,
        ], $settings->version('localization'));

        $this->withoutVite();
        $this->withCookie('locale', 'fr')->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.code', 'fr')
                ->where('translations.Let\'s get started', 'Commençons'));
    }

    public function test_rtl_locale_sets_direction_and_english_cannot_be_deleted(): void
    {
        $administrator = User::factory()->admin()->create();
        $this->actingAs($administrator)->post('/admin/localizations', [
            'code' => 'ar',
            'name' => 'Arabic',
            'direction' => 'rtl',
            'is_published' => true,
        ])->assertRedirect();

        $this->withCookie('locale', 'ar')->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false);

        $english = Locale::query()->where('code', 'en')->sole();
        $this->actingAs($administrator)
            ->delete("/admin/localizations/{$english->code}")
            ->assertSessionHasErrors('locale');
    }

    public function test_non_default_locale_delete_removes_translations_and_audits(): void
    {
        $administrator = User::factory()->admin()->create();
        $this->actingAs($administrator)->post('/admin/localizations', [
            'code' => 'fr',
            'name' => 'French',
            'direction' => 'ltr',
            'is_published' => true,
        ])->assertRedirect();

        $locale = Locale::query()->where('code', 'fr')->sole();
        Translation::query()->create([
            'locale_id' => $locale->id,
            'key' => 'Welcome',
            'value' => 'Bienvenue',
        ]);

        $this->actingAs($administrator)
            ->delete('/admin/localizations/fr')
            ->assertRedirect();

        $this->assertDatabaseMissing('locales', ['code' => 'fr']);
        $this->assertDatabaseMissing('translations', ['locale_id' => $locale->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'delete_locale',
            'actor_user_id' => $administrator->id,
        ]);

        $this->withoutVite();
        $this->actingAs($administrator)->get('/admin/localizations')
            ->assertInertia(fn (Assert $page) => $page->where('selected', 'en'));
    }

    public function test_locale_delete_rejects_platform_default_even_when_not_flagged(): void
    {
        $administrator = User::factory()->admin()->create();
        Locale::query()->create([
            'code' => 'fr',
            'name' => 'French',
            'direction' => 'ltr',
            'is_published' => true,
        ]);

        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'localization', [
            'default_locale' => 'fr',
        ], $settings->version('localization'));

        $this->actingAs($administrator)
            ->delete('/admin/localizations/fr')
            ->assertSessionHasErrors('locale');

        $this->assertDatabaseHas('locales', ['code' => 'fr']);
    }

    public function test_user_preference_beats_cookie_and_accept_language(): void
    {
        $administrator = User::factory()->admin()->create();
        foreach ([['es', 'Spanish'], ['de', 'German']] as [$code, $name]) {
            $administratorLocale = Locale::query()->create(['code' => $code, 'name' => $name, 'direction' => 'ltr', 'is_published' => true]);
            Translation::query()->create(['locale_id' => $administratorLocale->id, 'key' => 'Welcome', 'value' => strtoupper($code)]);
        }

        $user = User::factory()->create(['locale' => 'de']);
        $this->actingAs($user)
            ->withCookie('locale', 'es')
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('locale.code', 'de'));
    }

    public function test_locale_codes_are_normalized_and_accept_language_falls_back_to_primary_language(): void
    {
        $administrator = User::factory()->admin()->create();
        $this->actingAs($administrator)->post('/admin/localizations', [
            'code' => 'PT-BR',
            'name' => 'Brazilian Portuguese',
            'direction' => 'ltr',
            'is_published' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('locales', ['code' => 'pt-br']);
        $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9')
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('locale.code', 'pt-br'));

        Locale::query()->create(['code' => 'fr', 'name' => 'French', 'direction' => 'ltr', 'is_published' => true]);
        $this->withHeader('Accept-Language', 'fr-CA,fr;q=0.9')
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('locale.code', 'fr'));
    }

    public function test_import_rejects_nested_or_invalid_maps_and_export_is_sorted(): void
    {
        $administrator = User::factory()->admin()->create();
        $locale = Locale::query()->create(['code' => 'fr', 'name' => 'French', 'direction' => 'ltr', 'is_published' => true]);

        $nested = UploadedFile::fake()->createWithContent('nested.json', json_encode(['ok' => ['no' => 'nested']]));
        $this->actingAs($administrator)
            ->post("/admin/localizations/{$locale->code}/import", ['file' => $nested])
            ->assertSessionHasErrors('file');

        $invalid = UploadedFile::fake()->createWithContent('invalid.json', '{"key":"'."\xFF".'"}');
        $this->actingAs($administrator)
            ->post("/admin/localizations/{$locale->code}/import", ['file' => $invalid])
            ->assertSessionHasErrors('file');

        $valid = UploadedFile::fake()->createWithContent('valid.json', json_encode(['b' => 'Two', 'a' => 'One']));
        $this->actingAs($administrator)
            ->post("/admin/localizations/{$locale->code}/import", ['file' => $valid])
            ->assertRedirect();

        $this->actingAs($administrator)
            ->get("/admin/localizations/{$locale->code}/export")
            ->assertOk()
            ->assertSee('"a": "One"', false)
            ->assertSee('"b": "Two"', false);
    }

    public function test_platform_locale_timezone_and_translation_switch_are_applied(): void
    {
        $administrator = User::factory()->admin()->create();
        $locale = Locale::query()->create([
            'code' => 'fr',
            'name' => 'French',
            'direction' => 'ltr',
            'is_published' => true,
        ]);
        Translation::query()->create([
            'locale_id' => $locale->id,
            'key' => "Let's get started",
            'value' => 'Commençons',
        ]);
        app(TranslationRepository::class)->flush($locale);

        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'localization', [
            'translations_enabled' => true,
            'default_locale' => 'fr',
            'timezone' => 'Pacific/Auckland',
        ], $settings->version('localization'));

        $originalPhpTimezone = date_default_timezone_get();
        $originalAppTimezone = config('app.timezone');

        try {
            $this->withoutVite()
                ->withHeader('Accept-Language', 'zz')
                ->get('/')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('locale.code', 'fr')
                    ->where("translations.Let's get started", 'Commençons'));

            $this->assertSame('Pacific/Auckland', config('app.timezone'));
            $this->assertSame('Pacific/Auckland', date_default_timezone_get());

            $settings->updateSection($administrator, 'localization', [
                'translations_enabled' => false,
                'default_locale' => 'fr',
                'timezone' => 'Pacific/Auckland',
            ], $settings->version('localization'));

            $this->withHeader('Accept-Language', 'zz')
                ->get('/')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('locale.code', 'fr')
                    ->where('translations', []));
        } finally {
            date_default_timezone_set($originalPhpTimezone);
            config(['app.timezone' => $originalAppTimezone]);
        }
    }
}
