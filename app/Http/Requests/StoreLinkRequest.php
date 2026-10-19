<?php

namespace App\Http\Requests;

class StoreLinkRequest extends LinkRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->linkRules();
    }
}
