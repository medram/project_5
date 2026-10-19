<?php

namespace App\Http\Requests;

use App\Models\Link;

class UpdateLinkRequest extends LinkRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $link = $this->route('link');

        return $this->linkRules($link instanceof Link ? $link : null, true);
    }
}
