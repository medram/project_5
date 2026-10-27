<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnlockQrCodeRequest;
use App\Models\QrCode;
use App\Services\QrCodes\QrAccessUnlocks;
use App\Services\QrCodes\QrCampaignDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class QrAccessController extends Controller
{
    public function __construct(
        private readonly QrCampaignDelivery $delivery,
        private readonly QrAccessUnlocks $unlocks,
    ) {}

    public function __invoke(UnlockQrCodeRequest $request, string $code): RedirectResponse
    {
        $qrCode = QrCode::query()->where('code', $code)->firstOrFail();
        abort_unless($this->delivery->isAvailable($qrCode), 404);
        $this->delivery->targetUrl($qrCode);
        abort_unless($qrCode->requiresAccessCode(), 404);

        $unlocked = $this->unlocks->attempt($request, $qrCode, $request->validated('access_code'));
        abort_if($unlocked === null, 429);
        if (! $unlocked) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code is incorrect.',
            ]);
        }

        return to_route('qr-codes.public', $qrCode->code);
    }
}
