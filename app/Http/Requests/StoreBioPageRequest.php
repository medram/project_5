<?php

namespace App\Http\Requests;

class StoreBioPageRequest extends BioPageRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->bioPageRules();
    }
}
