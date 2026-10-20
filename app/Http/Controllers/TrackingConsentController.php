<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TrackingConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrackingConsentController extends Controller
{
    public function __invoke(Request $request, TrackingConsent $consent): RedirectResponse
    {
        $validated = $request->validate([
            'consent' => ['required', 'string', Rule::in([TrackingConsent::GRANTED, TrackingConsent::DENIED])],
            'return_path' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! $this->isRelativePath($value)) {
                    $fail('The return path is invalid.');
                }
            }],
        ]);
        $returnPath = $validated['return_path'] ?? '/';

        return redirect()->away($returnPath)->withCookie($consent->cookie($validated['consent']));
    }

    private function isRelativePath(string $path): bool
    {
        if (
            ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\r\n]|%(?:2f|5c)/i', $path) === 1
        ) {
            return false;
        }

        $parts = parse_url($path);

        return is_array($parts) && ! isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass']);
    }
}
