<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\QrCodes\QrCampaignDelivery;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QrCodeCsvController extends Controller
{
    public function __construct(
        private readonly QrCampaignDelivery $delivery,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create QR export stream.');
        }

        try {
            fputcsv($stream, ['title', 'code', 'source', 'target_url', 'qr_url', 'active', 'scans', 'max_scans', 'created_at'], escape: '', eol: "\r\n");

            foreach ($workspace->qrCodes()->with('link')->orderBy('id')->get() as $qrCode) {
                $targetUrl = $this->targetUrlForExport($qrCode);
                fputcsv($stream, [
                    $this->spreadsheetSafeTitle($qrCode->title),
                    $qrCode->code,
                    $qrCode->source,
                    $targetUrl,
                    route('qr-codes.public', $qrCode->code),
                    $qrCode->is_active ? 'true' : 'false',
                    $qrCode->scan_count,
                    $qrCode->max_scans,
                    $qrCode->created_at?->utc()->toIso8601String(),
                ], escape: '', eol: "\r\n");
            }

            rewind($stream);
            $csv = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if (! is_string($csv)) {
            throw new RuntimeException('Unable to read QR export stream.');
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="qr-campaigns.csv"',
        ]);
    }

    private function targetUrlForExport(QrCode $qrCode): string
    {
        if ($qrCode->destination_url === null && $qrCode->link === null) {
            return '';
        }

        try {
            return $this->delivery->targetUrl($qrCode);
        } catch (NotFoundHttpException) {
            return '';
        }
    }

    private function spreadsheetSafeTitle(?string $title): ?string
    {
        if ($title === null || preg_match('/^[\x00-\x20]*[=+\-@]/', $title) !== 1) {
            return $title;
        }

        return "'".$title;
    }
}
