<?php

namespace App\Http\Middleware;

use App\Services\Localization\TranslationRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly TranslationRepository $translations) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->translations->resolve($request, $request->user());
        $request->attributes->set('locale_model', $locale);
        app()->setLocale($locale->code);
        config(['app.locale' => $locale->code]);

        return $next($request);
    }
}
