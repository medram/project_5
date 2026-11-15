<?php

namespace App\Rules;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlatformAssetRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $valid = ContentMedia::query()
            ->whereKey($value)
            ->where('purpose', MediaPurpose::PlatformAsset)
            ->whereNull('workspace_id')
            ->where('visibility', 'private')
            ->whereIn('scan_state', ['not_required', 'clean'])
            ->whereNull('expires_at')
            ->exists();

        if (! $valid) {
            $fail('Choose an uploaded platform asset.');
        }
    }
}
