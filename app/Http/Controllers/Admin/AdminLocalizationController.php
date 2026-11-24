<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Models\Translation;
use App\Services\Localization\TranslationRepository;
use App\Services\Platform\AdminAudit;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminLocalizationController extends Controller
{
    public function __construct(
        private readonly TranslationRepository $translations,
        private readonly AdminAudit $audit,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'locale' => ['nullable', 'string', 'max:12'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $locales = Locale::query()->withCount('translations')->orderBy('name')->get();
        $selected = $locales->firstWhere('code', $filters['locale'] ?? '') ?? $locales->firstWhere('is_default', true) ?? $locales->first();
        $rows = Translation::query()
            ->when($selected, fn ($query) => $query->where('locale_id', $selected->id))
            ->when(trim((string) ($filters['q'] ?? '')) !== '', function ($query) use ($filters): void {
                $search = '%'.trim((string) $filters['q']).'%';
                $query->where(fn ($nested) => $nested->where('key', 'like', $search)->orWhere('value', 'like', $search));
            })
            ->orderBy('key')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/localizations/index', [
            'locales' => $locales->map(fn (Locale $locale): array => [
                'code' => $locale->code,
                'name' => $locale->name,
                'direction' => $locale->direction,
                'isPublished' => $locale->is_published,
                'isDefault' => $locale->is_default,
                'translationsCount' => (int) $locale->translations_count,
            ])->values()->all(),
            'selected' => $selected?->code,
            'translations' => $rows->through(fn (Translation $translation): array => [
                'key' => $translation->key,
                'value' => $translation->value,
            ]),
            'filters' => ['locale' => $selected?->code, 'q' => (string) ($filters['q'] ?? '')],
        ]);
    }

    public function storeLocale(Request $request): RedirectResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtolower($request->input('code'))]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', 'unique:locales,code'],
            'name' => ['required', 'string', 'max:80'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'is_published' => ['required', 'boolean'],
        ]);

        Locale::query()->create($validated);
        $actor = $request->user();
        abort_if($actor === null, 403);
        $this->audit->record($actor, PlatformPermission::AdminLocalizationsManage, 'create_locale', null, null, ['code' => $validated['code']]);

        return back();
    }

    public function updateLocale(Request $request, Locale $locale): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'is_published' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ]);

        if ($locale->code === 'en' && (! $validated['is_published'] || ! $validated['is_default'])) {
            throw ValidationException::withMessages(['locale' => 'English remains published and default.']);
        }

        DB::transaction(function () use ($locale, $validated, $request): void {
            if ($validated['is_default']) {
                Locale::query()->whereKeyNot($locale->id)->update(['is_default' => false]);
                $validated['is_published'] = true;
            }

            $locale->forceFill($validated)->save();
            $actor = $request->user();
            abort_if($actor === null, 403);
            $this->audit->record($actor, PlatformPermission::AdminLocalizationsManage, 'update_locale', $locale, null, [
                'code' => $locale->code,
                'published' => $locale->is_published,
                'default' => $locale->is_default,
            ]);
        });

        return back();
    }

    public function destroyLocale(Request $request, Locale $locale): RedirectResponse
    {
        $platformDefault = strtolower((string) (app(PlatformSettings::class)->resolved()['localization']['default_locale'] ?? 'en'));
        if ($locale->code === 'en' || $locale->is_default || $locale->code === $platformDefault) {
            throw ValidationException::withMessages(['locale' => 'The English/default locale cannot be deleted.']);
        }

        $code = $locale->code;
        $this->translations->flush($locale);
        $locale->delete();
        $actor = $request->user();
        abort_if($actor === null, 403);
        $this->audit->record($actor, PlatformPermission::AdminLocalizationsManage, 'delete_locale', null, null, ['code' => $code]);

        return back();
    }

    public function updateTranslation(Request $request, Locale $locale): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:10000'],
        ]);

        Translation::query()->updateOrCreate(
            ['locale_id' => $locale->id, 'key' => trim($validated['key'])],
            ['value' => $validated['value']],
        );
        $this->translations->flush($locale);
        $actor = $request->user();
        abort_if($actor === null, 403);
        $this->audit->record($actor, PlatformPermission::AdminLocalizationsManage, 'update_translation', $locale, null, ['key' => $validated['key']]);

        return back();
    }

    public function resetTranslation(Request $request, Locale $locale): RedirectResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'max:255']]);
        $key = trim($validated['key']);
        if ($locale->code === 'en') {
            throw ValidationException::withMessages(['key' => 'English translations cannot be reset.']);
        }

        $actor = $request->user();
        abort_if($actor === null, 403);
        DB::transaction(function () use ($locale, $key, $actor): void {
            if (Translation::query()->where('locale_id', $locale->id)->where('key', $key)->delete() === 0) {
                throw ValidationException::withMessages(['key' => 'This translation has no override to reset.']);
            }
            $this->audit->record($actor, PlatformPermission::AdminLocalizationsManage, 'reset_translation', $locale, null, ['key' => $key]);
        });
        $this->translations->flush($locale);

        return back();
    }

    public function import(Request $request, Locale $locale): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimetypes:application/json,text/plain']]);
        $contents = (string) file_get_contents($request->file('file')->getRealPath());
        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw ValidationException::withMessages(['file' => 'The import must be a flat JSON object of string values.']);
        }

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || $key === '' || mb_strlen($key) > 255 || ! is_string($value) || mb_strlen($value) > 10000 || ! mb_check_encoding($key.$value, 'UTF-8')) {
                throw ValidationException::withMessages(['file' => 'The import contains an invalid key or value.']);
            }
        }

        DB::transaction(function () use ($decoded, $locale, $request): void {
            foreach ($decoded as $key => $value) {
                Translation::query()->updateOrCreate(['locale_id' => $locale->id, 'key' => $key], ['value' => $value]);
            }
            $this->translations->flush($locale);
            $actor = $request->user();
            abort_if($actor === null, 403);
            $this->audit->record($actor, PlatformPermission::AdminLocalizationsManage, 'import_translations', $locale, null, ['count' => count($decoded)]);
        });

        return back();
    }

    public function export(Locale $locale): JsonResponse
    {
        $map = Translation::query()->where('locale_id', $locale->id)->orderBy('key')->pluck('value', 'key')->all();

        return response()->json($map, 200, [
            'Content-Disposition' => 'attachment; filename="'.$locale->code.'.json"',
            'Content-Type' => 'application/json; charset=utf-8',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
