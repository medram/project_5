<?php

namespace App\Http\Requests;

use App\Models\CustomDomain;
use App\Services\Platform\AdminContentAccess;
use Illuminate\Validation\Rule;

class UpdateCustomDomainRequest extends StoreCustomDomainRequest
{
    public function authorize(): bool
    {
        $domain = $this->route('customDomain');
        if (! $domain instanceof CustomDomain) {
            return false;
        }

        app(AdminContentAccess::class)->authorize($this, 'update', $domain);

        return true;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'confirmation' => ['required', 'string', Rule::in([$this->route('customDomain')->hostname])],
        ];
    }
}
