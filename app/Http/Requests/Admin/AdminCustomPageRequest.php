<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaPurpose;
use App\Models\CustomPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCustomPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => strtolower(trim($slug))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $page = $this->route('customPage');

        return [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('custom_pages', 'slug')->ignore($page),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && CustomPage::isReservedSlug($value)) {
                        $fail('This slug is reserved by the application.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:250000'],
            'state' => ['required', Rule::in(['draft', 'published'])],
            'placement' => ['nullable', Rule::in(['header', 'footer'])],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'og_media_id' => ['nullable', 'integer', Rule::exists('content_media', 'id')->where(fn ($query) => $query
                ->where('purpose', MediaPurpose::PlatformAsset->value)
                ->whereNull('workspace_id')->whereNull('deleted_at')->whereNull('expires_at')
                ->where('visibility', 'private')
                ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
                ->whereIn('scan_state', ['clean', 'not_required']))],
        ];
    }
}
