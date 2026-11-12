<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaPurpose;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminFileActionRequest;
use App\Models\ContentMedia;
use App\Services\Content\ContentMediaStorage;
use App\Services\Platform\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminFileController extends Controller
{
    public function __construct(
        private readonly AdminAudit $audit,
        private readonly ContentMediaStorage $storage,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'purpose' => ['nullable', Rule::in(array_map(fn (MediaPurpose $purpose): string => $purpose->value, MediaPurpose::cases()))],
            'scan' => ['nullable', Rule::in(['not_required', 'clean', 'infected', 'failed'])],
            'state' => ['nullable', Rule::in(['active', 'trashed'])],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'byte_size'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $query = ContentMedia::query()
            ->withTrashed()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name', 'uploader:id,name,email']);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($media) => $media
                ->where('original_name', 'like', $like)
                ->orWhere('sha256', 'like', $like)
                ->orWhereHas('workspace', fn ($workspace) => $workspace->where('name', 'like', $like))
                ->orWhereHas('uploader', fn ($uploader) => $uploader->where('name', 'like', $like)));
        }

        if (($filters['purpose'] ?? null) !== null) {
            $query->where('purpose', $filters['purpose']);
        }

        if (($filters['scan'] ?? null) !== null) {
            $query->where('scan_state', $filters['scan']);
        }

        if (($filters['state'] ?? null) === 'trashed') {
            $query->onlyTrashed();
        } elseif (($filters['state'] ?? null) === 'active') {
            $query->whereNull('deleted_at');
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $rows = $query
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ContentMedia $media): array => [
                'id' => $media->id,
                'originalName' => $media->original_name,
                'workspace' => $media->workspace?->name,
                'owner' => $media->workspace?->owner?->name,
                'uploader' => $media->uploader?->name,
                'uploaderEmail' => $media->uploader?->email,
                'purpose' => $media->purpose->label(),
                'mimeType' => $media->mime_type,
                'byteSize' => $media->byte_size,
                'sha256' => $media->sha256,
                'visibility' => $media->visibility,
                'scanState' => $media->scan_state,
                'expiresAt' => $media->expires_at?->toISOString(),
                'deletedAt' => $media->deleted_at?->toISOString(),
                'deletionReason' => $media->getAttribute('deletion_reason'),
                'createdAt' => $media->created_at?->toISOString(),
                'updatedAt' => $media->updated_at?->toISOString(),
            ]);

        return Inertia::render('admin/files/index', [
            'rows' => $rows,
            'filters' => [
                'q' => $search,
                'purpose' => $filters['purpose'] ?? null,
                'scan' => $filters['scan'] ?? null,
                'state' => $filters['state'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'purposes' => array_map(fn (MediaPurpose $purpose): array => ['value' => $purpose->value, 'label' => $purpose->label()], MediaPurpose::cases()),
        ]);
    }

    public function destroy(AdminFileActionRequest $request, int $media): RedirectResponse
    {
        $file = ContentMedia::query()->findOrFail($media);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $file->forceFill(['deletion_reason' => trim($request->validated()['reason'])])->save();
        $this->storage->delete($file);

        $this->audit->record(
            $actor,
            PlatformPermission::AdminFilesManage,
            'soft_delete_file',
            $file,
            trim($request->validated()['reason']),
            ['purpose' => $file->purpose->value, 'bytes' => $file->byte_size],
        );

        return back(fallback: route('admin.files.index'));
    }

    public function restore(AdminFileActionRequest $request, int $media): RedirectResponse
    {
        $file = ContentMedia::query()->withTrashed()->findOrFail($media);
        $actor = $request->user();
        abort_if($actor === null, 403);

        if ($file->trashed()) {
            $file->restore();
        }

        $file->forceFill(['deletion_reason' => null])->save();

        $this->audit->record(
            $actor,
            PlatformPermission::AdminFilesManage,
            'restore_file',
            $file,
            trim($request->validated()['reason']),
            ['purpose' => $file->purpose->value, 'bytes' => $file->byte_size],
        );

        return back(fallback: route('admin.files.index'));
    }
}
