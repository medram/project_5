<?php

namespace App\Http\Requests;

use App\Models\BioContentBlock;

class UpdateBioContentBlockRequest extends StoreBioContentBlockRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->input('type') === 'image') {
            $existing = $this->route('bioBlock');
            $hasStoredImage = $existing instanceof BioContentBlock && $existing->contentMedia()->exists();
            $rules['image'] = [$hasStoredImage ? 'sometimes' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
        } else {
            $rules['image'] = ['prohibited'];
        }

        return $rules;
    }
}
