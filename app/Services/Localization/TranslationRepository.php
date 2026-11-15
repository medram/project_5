<?php

namespace App\Services\Localization;

use App\Models\Locale;
use App\Models\Translation;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class TranslationRepository
{
    /**
     * @return list<Locale>
     */
    public function published(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return array_values(Locale::query()->where('is_published', true)->orderBy('name')->get()->all());
    }

    public function resolve(Request $request, ?User $user): Locale
    {
        if (! $this->tableExists()) {
            return new Locale(['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'is_published' => true, 'is_default' => true]);
        }

        $published = Locale::query()->where('is_published', true);
        $candidates = [];

        if ($user?->locale !== null) {
            $candidates[] = strtolower($user->locale);
        }

        $cookie = $request->cookie('locale');
        if (is_string($cookie) && $cookie !== '') {
            $candidates[] = strtolower($cookie);
        }

        foreach ($request->getLanguages() as $language) {
            $language = str_replace('_', '-', strtolower($language));
            $candidates[] = $language;
            $candidates[] = explode('-', $language, 2)[0];
            $candidates[] = substr($language, 0, 5);
        }

        $platform = app(PlatformSettings::class)->resolved()['localization']['default_locale'] ?? 'en';
        $candidates[] = is_string($platform) ? strtolower($platform) : 'en';

        foreach ($candidates as $candidate) {
            $locale = (clone $published)->where('code', $candidate)->first();

            if ($locale !== null) {
                return $locale;
            }
        }

        return (clone $published)->where('code', 'en')->first()
            ?? $published->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    public function dictionary(Locale $locale): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        if (! (bool) (app(PlatformSettings::class)->resolved()['localization']['translations_enabled'] ?? false)) {
            return [];
        }

        return Cache::remember("translations.{$locale->code}", 300, function () use ($locale): array {
            $english = Translation::query()
                ->whereHas('locale', fn ($query) => $query->where('code', 'en'))
                ->pluck('value', 'key')
                ->all();

            $selected = Translation::query()
                ->where('locale_id', $locale->id)
                ->pluck('value', 'key')
                ->all();

            return array_merge($english, $selected);
        });
    }

    public function flush(Locale $locale): void
    {
        if ($locale->code === 'en') {
            Locale::query()->pluck('code')->each(fn (string $code) => Cache::forget("translations.{$code}"));

            return;
        }

        Cache::forget("translations.{$locale->code}");
    }

    public function tableExists(): bool
    {
        return Schema::hasTable('locales');
    }
}
