<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeRenderer;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class QrCodeAssetController extends Controller
{
    public function __construct(
        private readonly QrCodeRenderer $renderer,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function preview(Request $request, QrCode $qrCode): Response
    {
        $qrCode = $this->workspaceContext->currentFor($request->user())
            ->qrCodes()
            ->findOrFail($qrCode->getKey());

        return $this->renderer->render($qrCode, 'svg', download: false);
    }

    public function draftPreview(Request $request): Response
    {
        $settings = $request->validate([
            'foreground_color' => ['sometimes', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'background_color' => ['sometimes', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'error_correction' => ['sometimes', Rule::in(['low', 'medium', 'quartile', 'high'])],
            'margin' => ['sometimes', 'integer', 'between:0,20'],
            'size' => ['sometimes', 'integer', 'between:128,1024'],
        ]);
        $qrCode = new QrCode([
            'workspace_id' => $this->workspaceContext->currentFor($request->user())->id,
            'code' => 'preview-only',
            'source' => 'campaign',
            'foreground_color' => $settings['foreground_color'] ?? '#000000',
            'background_color' => $settings['background_color'] ?? '#FFFFFF',
            'error_correction' => $settings['error_correction'] ?? 'medium',
            'margin' => $settings['margin'] ?? 4,
            'size' => $settings['size'] ?? 320,
        ]);

        return $this->renderer->renderDraftPreview($qrCode);
    }

    public function download(Request $request, QrCode $qrCode, string $format): Response
    {
        $qrCode = $this->workspaceContext->currentFor($request->user())
            ->qrCodes()
            ->findOrFail($qrCode->getKey());

        return $this->renderer->render($qrCode, $format);
    }
}
