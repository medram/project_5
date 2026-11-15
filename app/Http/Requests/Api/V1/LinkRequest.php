<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Links\FirstPartyDestinationGuard;
use App\Services\Links\LinkPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Url;

class LinkRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $slug = strtolower(trim($this->input('slug')));
            $this->merge(['slug' => $slug === '' ? null : $slug]);
        }

        if ($this->isMethod('POST') && ! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $slug = Rule::unique('links', 'slug');
        if ($this->route('link') !== null) {
            $slug->ignore($this->route('link'));
        }

        return [
            'destination_url' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'max:2048',
                (new Url)->protocols(['http', 'https']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if (app(FirstPartyDestinationGuard::class)->rejects($value)) {
                        $fail('The destination URL must not target this application.');

                        return;
                    }

                    $rejection = app(LinkPolicy::class)->rejection(
                        $value,
                        is_string($this->input('slug')) ? $this->input('slug') : null,
                        is_string($this->input('title')) ? $this->input('title') : null,
                    );

                    if ($rejection !== null) {
                        $fail($rejection);
                    }
                },
            ],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/\A[a-z0-9-]{4,32}\z/', Rule::notIn((array) config('linkpilot.reserved_slugs', [])), $slug],
            'is_active' => ['sometimes', 'boolean'],
            'workspace_id' => ['prohibited'],
        ];
    }
}
