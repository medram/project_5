<?php

namespace App\Http\Requests;

use App\Models\BioPage;

class UpdateBioPageRequest extends BioPageRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $bioPage = $this->route('bioPage');

        return $this->bioPageRules($bioPage instanceof BioPage ? $bioPage : null);
    }
}
