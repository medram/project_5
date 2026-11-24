<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;

class AdminContentNavigation
{
    /**
     * @return array{q?: string, state?: string, sort?: string, direction?: string, page?: string}
     */
    public function listQuery(Request $request): array
    {
        $query = [];

        $search = $request->query('q');
        if (is_string($search) && ($search = trim($search)) !== '') {
            $query['q'] = mb_substr($search, 0, 100);
        }

        $state = $request->query('state');
        if (is_string($state) && ($state = trim($state)) !== '') {
            $query['state'] = mb_substr($state, 0, 40);
        }

        $sort = $request->query('sort');
        if (is_string($sort) && in_array($sort, ['name', 'created_at', 'updated_at'], true)) {
            $query['sort'] = $sort;
        }

        $direction = $request->query('direction');
        if (is_string($direction) && in_array($direction, ['asc', 'desc'], true)) {
            $query['direction'] = $direction;
        }

        $page = $request->query('page');
        if (is_string($page) && ctype_digit($page) && (int) $page > 0) {
            $query['page'] = (string) (int) $page;
        }

        return $query;
    }

    public function withListQuery(Request $request, string $url): string
    {
        $query = $this->listQuery($request);

        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query);
    }
}
