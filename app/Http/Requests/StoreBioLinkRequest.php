<?php

namespace App\Http\Requests;

class StoreBioLinkRequest extends BioLinkRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->bioLinkRules();
    }
}
