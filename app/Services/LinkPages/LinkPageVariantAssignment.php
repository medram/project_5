<?php

namespace App\Services\LinkPages;

use App\Models\LinkPage;
use App\Models\LinkPageVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LinkPageVariantAssignment
{
    public const COOKIE_NAME = 'linkpilot_link_page_variant';

    public function forPageView(Request $request, LinkPage $linkPage): LinkPageVariant
    {
        if ($request->cookies->has(self::COOKIE_NAME)) {
            return $this->assigned($request, $linkPage);
        }

        return $this->choose($linkPage);
    }

    public function assigned(Request $request, LinkPage $linkPage): LinkPageVariant
    {
        $control = $this->control($linkPage);
        $value = $request->cookie(self::COOKIE_NAME);
        $variantId = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        if (! is_int($variantId) || $variantId < 1) {
            return $control;
        }

        return $linkPage->variants()
            ->whereKey($variantId)
            ->where('is_active', true)
            ->first() ?? $control;
    }

    public function queueCookie(LinkPageVariant $variant): void
    {
        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            (string) $variant->getKey(),
            30 * 24 * 60,
            '/',
            null,
            null,
            true,
            false,
            'lax',
        ));
    }

    private function choose(LinkPage $linkPage): LinkPageVariant
    {
        $variants = $linkPage->variants()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
        $roll = random_int(1, 100);
        $cumulativeWeight = 0;

        foreach ($variants as $variant) {
            $cumulativeWeight += $variant->weight;
            if ($roll <= $cumulativeWeight) {
                return $variant;
            }
        }

        return $this->control($linkPage);
    }

    private function control(LinkPage $linkPage): LinkPageVariant
    {
        return $linkPage->controlVariant()
            ->where('is_active', true)
            ->firstOrFail();
    }
}
