<?php

namespace App\Http\Requests;

use App\Models\Link;
use App\Models\QrCode;
use App\Services\Links\FirstPartyDestinationGuard;
use App\Services\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Url;

abstract class QrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $qrCode = $this->route('qrCode');
        if ($qrCode instanceof QrCode) {
            $workspace = app(WorkspaceContext::class)->forContentRequest($this);
            abort_unless($workspace->qrCodes()->whereKey($qrCode->getKey())->exists(), 404);
        }

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $workspace = app(WorkspaceContext::class)->forContentRequest($this);
        $targetPresence = $this->isMethod('post')
            ? ['required_without:link_id']
            : ['sometimes'];
        $linkPresence = $this->isMethod('post')
            ? ['required_without:destination_url']
            : ['sometimes'];

        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'destination_url' => [
                ...$targetPresence, 'nullable', 'string', 'max:2048',
                (new Url)->protocols(['http', 'https']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && app(FirstPartyDestinationGuard::class)->rejects($value)) {
                        $fail('The destination URL must not target this application.');
                    }
                },
            ],
            'link_id' => [
                ...$linkPresence, 'nullable', 'integer',
                Rule::exists(Link::class, 'id')->where('workspace_id', $workspace->id),
            ],
            'share_title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'share_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'max_scans' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'access_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remove_access_code' => ['sometimes', 'boolean'],
            'foreground_color' => ['sometimes', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'background_color' => ['sometimes', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'error_correction' => ['sometimes', Rule::in(['low', 'medium', 'quartile', 'high'])],
            'margin' => ['sometimes', 'integer', 'between:0,20'],
            'size' => ['sometimes', 'integer', 'between:128,1024'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=1024,max_height=1024'],
            'logo_alt' => ['sometimes', 'nullable', 'string', 'max:160'],
            'clear_logo' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $qrCode = $this->route('qrCode') instanceof QrCode ? $this->route('qrCode') : null;
            $targetTouched = $this->isMethod('post') || $this->has('link_id') || $this->has('destination_url');

            if ($targetTouched) {
                $linkId = $this->has('link_id') ? $this->input('link_id') : $qrCode?->link_id;
                $destination = $this->has('destination_url') ? $this->input('destination_url') : $qrCode?->destination_url;
                if (filled($linkId) === filled($destination)) {
                    $validator->errors()->add('destination_url', 'Choose exactly one link or direct URL target.');
                }
                if ($qrCode?->source === 'linked') {
                    $validator->errors()->add('link_id', 'The target of a QR code linked to a short link cannot be changed.');
                }
            }

            if ($this->boolean('remove_access_code') && filled($this->input('access_code'))) {
                $validator->errors()->add('access_code', 'Set a new access code or remove the existing code, not both.');
            }
            if ($this->boolean('clear_logo') && $this->hasFile('logo')) {
                $validator->errors()->add('logo', 'Upload a new logo or remove the current logo, not both.');
            }

            $availableFrom = $this->has('available_from')
                ? $this->input('available_from')
                : $qrCode?->available_from?->toIso8601String();
            $expiresAt = $this->has('expires_at')
                ? $this->input('expires_at')
                : $qrCode?->expires_at?->toIso8601String();
            $startsAt = is_string($availableFrom) ? strtotime($availableFrom) : false;
            $endsAt = is_string($expiresAt) ? strtotime($expiresAt) : false;

            if ($startsAt !== false && $endsAt !== false && $endsAt <= $startsAt) {
                $validator->errors()->add('expires_at', 'The expiry must be after the start time.');
            }
        }];
    }
}
