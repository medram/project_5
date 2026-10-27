<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\QrCodes\QrAccessUnlocks;
use App\Services\QrCodes\QrCampaignDelivery;
use App\Services\QrCodes\QrScanRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicQrCodeController extends Controller
{
    public function __construct(
        private readonly QrCampaignDelivery $delivery,
        private readonly QrAccessUnlocks $unlocks,
        private readonly QrScanRecorder $scans,
    ) {}

    public function show(Request $request, string $code): RedirectResponse|Response
    {
        $qrCode = QrCode::query()->where('code', $code)->firstOrFail();
        abort_unless($this->delivery->isAvailable($qrCode), 404);
        $this->delivery->targetUrl($qrCode);

        if ($qrCode->requiresAccessCode() && ! $this->unlocks->allows($request, $qrCode)) {
            return Inertia::render('qr-codes/unlock', [
                'code' => $qrCode->code,
                'title' => $qrCode->share_title,
                'description' => $qrCode->share_description,
            ]);
        }

        $targetUrl = $this->scans->record($request, $qrCode);
        abort_if($targetUrl === null, 404);

        return redirect()->away($targetUrl, 302);
    }
}
