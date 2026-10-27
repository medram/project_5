<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkTagRequest;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkTagController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaceContext) {}

    public function store(StoreLinkTagRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $filters = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'tag' => ['nullable', 'integer', Rule::exists('link_tags', 'id')->where('workspace_id', $workspace->id)],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:created_at,title,clicks_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ])->validate();
        $attributes = $request->validated();
        $base = Str::slug($attributes['name']);
        $base = $base === '' ? 'tag' : Str::limit($base, 70, '');
        $slug = $base;
        $suffix = 2;

        while ($workspace->linkTags()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 70 - strlen((string) $suffix), '').'-'.$suffix;
            $suffix++;
        }

        $workspace->linkTags()->create([
            'name' => $attributes['name'],
            'slug' => $slug,
            'color' => $attributes['color'] ?? null,
        ]);

        return to_route('links.index', $filters);
    }
}
