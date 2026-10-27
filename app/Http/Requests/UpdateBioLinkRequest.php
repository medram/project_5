<?php

namespace App\Http\Requests;

class UpdateBioLinkRequest extends BioLinkRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->bioLinkRules();

        foreach (['title', 'destination_url', 'is_active'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
