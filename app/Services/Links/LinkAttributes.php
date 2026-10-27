<?php

namespace App\Services\Links;

use App\Http\Requests\LinkRequest;
use App\Models\Link;
use Illuminate\Support\Facades\Hash;

class LinkAttributes
{
    /** @return array<string, mixed> */
    public function fromRequest(LinkRequest $request, ?Link $link = null): array
    {
        return $this->fromValidated(
            $request->validated(),
            $request->boolean('clear_access_code'),
            $link,
        );
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function fromValidated(array $attributes, bool $clearAccessCode = false, ?Link $link = null): array
    {
        $accessCode = $attributes['access_code'] ?? null;
        unset($attributes['access_code'], $attributes['clear_access_code'], $attributes['tag_ids'], $attributes['routing_rules']);

        if (is_string($accessCode) && $accessCode !== '') {
            $attributes['access_code'] = Hash::make($accessCode);

            if ($link !== null) {
                $attributes['access_code_version'] = $link->access_code_version + 1;
            }
        } elseif ($link !== null && $clearAccessCode && $link->requiresAccessCode()) {
            $attributes['access_code'] = null;
            $attributes['access_code_version'] = $link->access_code_version + 1;
        }

        return $attributes;
    }
}
