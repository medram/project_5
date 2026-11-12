<?php

namespace App\Services\Accounts;

use DateTimeZone;
use Symfony\Component\Intl\Countries;

final class AccountProfileOptions
{
    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function locales(): array
    {
        return collect((array) config('linkpilot.supported_locales', []))
            ->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function countries(): array
    {
        return collect(Countries::getNames('en'))
            ->map(fn (string $name, string $code): array => ['code' => strtoupper($code), 'name' => $name])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
    }

    public function isSupportedLocale(?string $locale): bool
    {
        return is_string($locale)
            && array_key_exists($locale, (array) config('linkpilot.supported_locales', []));
    }

    public function isCountry(?string $countryCode): bool
    {
        return is_string($countryCode)
            && $countryCode !== ''
            && array_key_exists(strtoupper($countryCode), Countries::getNames('en'));
    }

    public function isTimezone(?string $timezone): bool
    {
        return is_string($timezone) && in_array($timezone, $this->timezones(), true);
    }
}
