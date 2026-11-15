<?php

namespace App\Http\Requests;

use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBioContentBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identifier'))) {
            $this->merge(['identifier' => trim($this->input('identifier'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $type = $this->input('type');
        $rules = [
            'type' => ['required', Rule::in(['text', 'image', 'social', 'embed'])],
            'is_active' => ['required', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => [$type === 'image' ? 'required' : 'prohibited', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'text' => [$type === 'text' ? 'required' : 'prohibited', 'string', 'max:5000'],
            'alt_text' => [$type === 'image' ? 'nullable' : 'prohibited', 'string', 'max:250'],
            'provider' => [$type === 'social' || $type === 'embed' ? 'required' : 'prohibited', Rule::in($type === 'social' ? ['instagram', 'x', 'tiktok', 'youtube', 'linkedin', 'github'] : ['youtube', 'soundcloud', 'vimeo', 'spotify', 'twitch', 'tiktok'])],
            'identifier' => [$type === 'social' || $type === 'embed' ? 'required' : 'prohibited', 'string', 'max:128'],
            'provider_type' => [$type === 'embed' ? 'nullable' : 'prohibited', Rule::in(['track', 'album', 'playlist', 'episode', 'show'])],
            'embed_url' => ['prohibited'], 'image_url' => ['prohibited'], 'media_id' => ['prohibited'],
            'content_media_id' => ['prohibited'], 'disk' => ['prohibited'], 'path' => ['prohibited'],
            'content' => ['prohibited'],
        ];
        foreach (['embed_url', 'image_url', 'media_id', 'content_media_id', 'disk', 'path'] as $field) {
            $rules['content.'.$field] = ['prohibited'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $provider = $this->input('provider');
            $identifier = (string) $this->input('identifier', '');
            $allowed = app(PlatformSettings::class)->resolved()['biolinks']['available_blocks'] ?? ['link', 'text', 'image', 'social', 'embed'];

            if (is_string($type) && is_array($allowed) && ! in_array($type, $allowed, true)) {
                $validator->errors()->add('type', 'This content type is unavailable on this platform.');
            }

            $text = $this->input('text');
            if ($type === 'text' && is_string($text) && preg_match('/<\/?[a-z][^>]*>/i', $text)) {
                $validator->errors()->add('text', 'HTML markup is not supported.');
            }
            if ($type === 'social' && is_string($provider)) {
                $pattern = $provider === 'youtube' ? '/\A[A-Za-z0-9._-]{1,30}\z/' : '/\A[A-Za-z0-9._-]{1,50}\z/';
                if (! preg_match($pattern, $identifier)) {
                    $validator->errors()->add('identifier', 'Enter a valid provider handle.');
                }
            }
            if ($type === 'embed') {
                $patterns = ['youtube' => '/\A[A-Za-z0-9_-]{11}\z/', 'soundcloud' => '/\A[0-9]+\z/', 'vimeo' => '/\A[0-9]+\z/', 'spotify' => '/\A[A-Za-z0-9]{22}\z/', 'twitch' => '/\A[a-z0-9_]{1,25}\z/', 'tiktok' => '/\A[0-9]+\z/'];
                if (isset($patterns[$provider]) && ! preg_match($patterns[$provider], $identifier)) {
                    $validator->errors()->add('identifier', 'Enter a valid canonical embed identifier.');
                }
                if ($provider === 'spotify' && ! in_array($this->input('provider_type'), ['track', 'album', 'playlist', 'episode', 'show'], true)) {
                    $validator->errors()->add('provider_type', 'Choose a Spotify item type.');
                }
                if ($provider !== 'spotify' && $this->filled('provider_type')) {
                    $validator->errors()->add('provider_type', 'This embed provider does not accept an item type.');
                }
            }
        });
    }
}
