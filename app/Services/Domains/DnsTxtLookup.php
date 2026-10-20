<?php

namespace App\Services\Domains;

class DnsTxtLookup
{
    /**
     * @return list<string>
     */
    public function recordsFor(string $hostname): array
    {
        $records = dns_get_record($hostname, DNS_TXT);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): ?string => isset($record['txt']) && is_string($record['txt'])
                ? $record['txt']
                : null,
            $records,
        )));
    }
}
