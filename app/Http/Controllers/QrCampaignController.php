<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Middleware\ScopedAdminContent;
use App\Http\Requests\StoreQrCodeRequest;
use App\Http\Requests\UpdateQrCodeRequest;
use App\Models\QrCode;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Platform\AdminContentAccess;
use App\Services\Platform\AdminContentMutation;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class QrCampaignController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly EnforcesPlanLimits $planLimits,
    ) {}

    public function store(StoreQrCodeRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $data = $request->validated();
        $linkId = isset($data['link_id']) ? (int) $data['link_id'] : null;
        $destination = $data['destination_url'] ?? null;
        $logoPath = $this->storeLogo($data['logo'] ?? null, $workspace);
        $create = function (Workspace $targetWorkspace) use ($request, $data, $linkId, $destination, $logoPath): QrCode {
            $attributes = collect($data)->except([
                'access_code', 'link_id', 'destination_url', 'logo', 'clear_logo', 'remove_access_code',
            ])->all();
            $attributes['access_code'] = filled($data['access_code'] ?? null)
                ? Hash::make($data['access_code'])
                : null;
            $attributes['logo_path'] = $logoPath;

            return $targetWorkspace->qrCodes()->create([
                ...$attributes,
                'created_by' => $request->user()->id,
                'source' => 'campaign',
                'link_id' => $linkId,
                'destination_url' => $destination,
            ]);
        };

        try {
            if ($destination !== null) {
                $this->planLimits->create($workspace, PlanMetric::QrCampaigns, $create);
            } else {
                $create($workspace);
            }
        } catch (Throwable $exception) {
            $this->deleteLogo($workspace, $logoPath);
            throw $exception;
        }

        return to_route('qr-codes.index');
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): RedirectResponse
    {
        $workspace = $this->workspaceContext->forContentRequest($request);
        $qrCode = $workspace->qrCodes()->findOrFail($qrCode->getKey());
        $data = $request->validated();
        $newLogoPath = $this->storeLogo($data['logo'] ?? null, $workspace);
        $isAdmin = is_array($request->attributes->get(ScopedAdminContent::ATTRIBUTE));
        if ($isAdmin && $newLogoPath !== null) {
            app(AdminContentMutation::class)->onRollback($request, fn () => $this->deleteLogo($workspace, $newLogoPath));
        }
        $oldLogoPath = null;
        $attributes = collect($data)->except([
            'access_code', 'remove_access_code', 'link_id', 'destination_url', 'logo', 'clear_logo',
        ])->all();

        if (filled($data['access_code'] ?? null)) {
            $attributes['access_code'] = Hash::make($data['access_code']);
            $attributes['access_code_version'] = $qrCode->access_code_version + 1;
        } elseif ($request->boolean('remove_access_code')) {
            $attributes['access_code'] = null;
            $attributes['access_code_version'] = $qrCode->access_code_version + 1;
        }

        if (array_key_exists('link_id', $data) || array_key_exists('destination_url', $data)) {
            $attributes['link_id'] = array_key_exists('link_id', $data)
                ? (filled($data['link_id']) ? (int) $data['link_id'] : null)
                : $qrCode->link_id;
            $attributes['destination_url'] = array_key_exists('destination_url', $data)
                ? (filled($data['destination_url']) ? $data['destination_url'] : null)
                : $qrCode->destination_url;
        }

        if ($newLogoPath !== null) {
            $oldLogoPath = $qrCode->logo_path;
            $attributes['logo_path'] = $newLogoPath;
        } elseif ($request->boolean('clear_logo')) {
            $oldLogoPath = $qrCode->logo_path;
            $attributes['logo_path'] = null;
        }

        $persist = fn () => DB::transaction(fn () => $qrCode->update($attributes));
        $becomingDirectTarget = $qrCode->destination_url === null
            && isset($attributes['destination_url'])
            && $attributes['destination_url'] !== null;

        try {
            if ($becomingDirectTarget) {
                $this->planLimits->create($workspace, PlanMetric::QrCampaigns, fn (Workspace $lockedWorkspace) => $persist());
            } else {
                $persist();
            }
        } catch (Throwable $exception) {
            $this->deleteLogo($workspace, $newLogoPath);
            throw $exception;
        }

        if ($isAdmin) {
            DB::afterCommit(fn () => $this->deleteLogo($workspace, $oldLogoPath));
        } else {
            $this->deleteLogo($workspace, $oldLogoPath);
        }

        return to_route('qr-codes.index');
    }

    public function destroy(Request $request, QrCode $qrCode): RedirectResponse
    {
        $workspace = $this->workspaceContext->forContentRequest($request);
        $isAdmin = is_array($request->attributes->get(ScopedAdminContent::ATTRIBUTE));
        $query = $workspace->qrCodes();
        if (! $isAdmin) {
            $query->where('source', 'campaign');
        }
        $qrCode = $query->findOrFail($qrCode->getKey());
        app(AdminContentAccess::class)->authorize($request, 'delete', $qrCode);
        $logoPath = $qrCode->logo_path;
        $qrCode->delete();
        if ($request->attributes->get(ScopedAdminContent::ATTRIBUTE)) {
            DB::afterCommit(fn () => $this->deleteLogo($workspace, $logoPath));
        } else {
            $this->deleteLogo($workspace, $logoPath);
        }

        return to_route('qr-codes.index');
    }

    private function storeLogo(mixed $file, Workspace $workspace): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $path = Storage::disk('local')->putFileAs(
            "qr-logos/{$workspace->id}",
            $file,
            Str::uuid().'.'.$file->extension(),
        );

        if (! is_string($path)) {
            throw new \RuntimeException('QR logo could not be stored on the private local disk.');
        }

        return $path;
    }

    private function deleteLogo(Workspace $workspace, ?string $path): void
    {
        if ($path !== null && str_starts_with($path, "qr-logos/{$workspace->id}/")) {
            Storage::disk('local')->delete($path);
        }
    }
}
