<?php

namespace Tests\Feature\Admin;

use App\Models\Locale;
use App\Models\Translation;
use App\Models\User;
use App\Services\Localization\TranslationRepository;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRecordEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resetting_a_translation_restores_english_fallback_and_flushes_cache(): void
    {
        $admin = User::factory()->admin()->create();
        $english = Locale::query()->where('code', 'en')->firstOrFail();
        $this->actingAs($admin)->post('/admin/localizations', [
            'code' => 'fr', 'name' => 'French', 'direction' => 'ltr', 'is_published' => true,
        ])->assertRedirect();
        $french = Locale::query()->where('code', 'fr')->firstOrFail();
        Translation::query()->updateOrCreate(['locale_id' => $english->id, 'key' => 'Greeting'], ['value' => 'Hello']);
        $this->actingAs($admin)->put('/admin/localizations/fr/translations', ['key' => 'Greeting', 'value' => 'Bonjour'])->assertRedirect();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($admin, 'localization', ['translations_enabled' => true], $settings->version('localization'));
        $dictionary = app(TranslationRepository::class);
        $this->assertSame('Bonjour', $dictionary->dictionary($french)['Greeting']);

        $this->actingAs($admin)->delete('/admin/localizations/fr/translations', ['key' => 'Greeting'])->assertRedirect();

        $this->assertDatabaseMissing('translations', ['locale_id' => $french->id, 'key' => 'Greeting']);
        $this->assertSame('Hello', $dictionary->dictionary($french)['Greeting']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'reset_translation', 'actor_user_id' => $admin->id]);
    }

    public function test_reset_rejects_english_missing_override_and_unauthorized_actor(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/localizations', [
            'code' => 'fr', 'name' => 'French', 'direction' => 'ltr', 'is_published' => true,
        ])->assertRedirect();
        $this->actingAs($admin)->put('/admin/localizations/fr/translations', ['key' => 'Greeting', 'value' => 'Bonjour'])->assertRedirect();

        $this->actingAs(User::factory()->support()->create())
            ->delete('/admin/localizations/fr/translations', ['key' => 'Greeting'])->assertForbidden();
        $this->actingAs($admin)->from('/admin/localizations')
            ->delete('/admin/localizations/en/translations', ['key' => 'Greeting'])->assertSessionHasErrors('key');
        $this->actingAs($admin)->from('/admin/localizations')
            ->delete('/admin/localizations/fr/translations', ['key' => 'Missing'])->assertSessionHasErrors('key');
        $this->assertDatabaseHas('translations', ['key' => 'Greeting', 'value' => 'Bonjour']);
    }
}
