<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Services\Analytics\RequestDimensions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrScanRecorder
{
    public function __construct(
        private readonly QrCampaignDelivery $delivery,
        private readonly QrAccessUnlocks $unlocks,
        private readonly RequestDimensions $dimensions,
    ) {}

    public function record(Request $request, QrCode $qrCode): ?string
    {
        $dimensions = $this->dimensions->forRequest($request);

        return DB::transaction(function () use ($request, $qrCode, $dimensions): ?string {
            $locked = QrCode::query()->lockForUpdate()->find($qrCode->getKey());

            if ($locked === null
                || ! $this->delivery->isAvailable($locked)
                || ($locked->requiresAccessCode() && ! $this->unlocks->allows($request, $locked))) {
                return null;
            }

            $targetUrl = $this->delivery->targetUrl($locked);
            $now = now();
            $updated = DB::table('qr_codes')
                ->where('id', $locked->getKey())
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('available_from')->orWhere('available_from', '<=', $now))
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->where(fn ($query) => $query->whereNull('max_scans')->orWhereColumn('scan_count', '<', 'max_scans'))
                ->increment('scan_count');

            if ($updated !== 1) {
                return null;
            }

            QrScan::query()->create([
                'qr_code_id' => $locked->getKey(),
                'scanned_at' => $now,
                ...$dimensions,
            ]);

            return $targetUrl;
        });
    }
}
