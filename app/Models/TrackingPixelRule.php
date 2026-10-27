<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'tracking_pixel_id', 'target_type', 'target_id', 'trigger'])]
class TrackingPixelRule extends Model
{
    public const TARGET_BIO_PAGE = 'bio_page';

    public const TARGET_LINK_PAGE = 'link_page';

    public const TARGET_LINK_OVERLAY = 'link_overlay';

    public const TRIGGER_PAGE_VIEW = 'page_view';

    public const TRIGGER_BUTTON_CLICK = 'button_click';

    public const TRIGGER_LEAD_SUBMIT = 'lead_submit';

    /** @return BelongsTo<TrackingPixel, $this> */
    public function trackingPixel(): BelongsTo
    {
        return $this->belongsTo(TrackingPixel::class);
    }
}
