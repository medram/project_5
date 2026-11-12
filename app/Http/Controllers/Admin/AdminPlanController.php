<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPlanUpdateRequest;
use App\Models\Plan;
use App\Services\Billing\StripeReadiness;
use App\Services\Platform\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    public function __construct(
        private readonly StripeReadiness $readiness,
        private readonly AdminAudit $audit,
    ) {}

    public function index(): Response
    {
        $plans = Plan::query()
            ->orderBy('monthly_price')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): array => $this->present($plan))
            ->values()
            ->all();

        return Inertia::render('admin/plans/index', [
            'plans' => $plans,
            'readiness' => [
                'checkoutReady' => $this->readiness->checkoutReady(),
                'missing' => $this->readiness->missing(),
            ],
        ]);
    }

    public function update(AdminPlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validated();
        $plan = DB::transaction(function () use ($plan, $validated, $request): Plan {
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->getKey());
            /** @var Plan $locked */
            $metadata = $locked->display_metadata ?? [];
            $metadata['description'] = $validated['description'] ?? null;
            $metadata['featured'] = (bool) $validated['featured'];
            $metadata['sort_order'] = (int) $validated['sort_order'];

            $locked->forceFill([
                'name' => trim($validated['name']),
                'display_metadata' => $metadata,
                'is_active' => (bool) $validated['is_active'],
                'stripe_monthly_price_id' => $validated['stripe_monthly_price_id'] ?: null,
                'stripe_yearly_price_id' => $validated['stripe_yearly_price_id'] ?: null,
            ])->save();

            $actor = $request->user();
            abort_if($actor === null, 403);
            $this->audit->record(
                $actor,
                PlatformPermission::AdminBillingManage,
                'update_plan',
                $locked,
                null,
                [
                    'name' => $locked->name,
                    'is_active' => $locked->is_active,
                    'featured' => (bool) $metadata['featured'],
                    'sort_order' => (int) $metadata['sort_order'],
                    'monthly_price_mapped' => $locked->stripe_monthly_price_id !== null,
                    'yearly_price_mapped' => $locked->stripe_yearly_price_id !== null,
                ],
            );

            return $locked;
        });

        return back(fallback: route('admin.plans.index'));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Plan $plan): array
    {
        $metadata = $plan->display_metadata ?? [];

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'currency' => $plan->currency,
            'monthlyPrice' => $plan->monthly_price,
            'annualPrice' => $plan->annual_price,
            'description' => is_string($metadata['description'] ?? null) ? $metadata['description'] : null,
            'featured' => (bool) ($metadata['featured'] ?? false),
            'sortOrder' => (int) ($metadata['sort_order'] ?? 0),
            'isActive' => $plan->is_active,
            'stripeMonthlyPriceId' => $plan->stripe_monthly_price_id,
            'stripeYearlyPriceId' => $plan->stripe_yearly_price_id,
            'checkout' => [
                'month' => $this->readiness->priceId($plan, 'month') !== null,
                'year' => $this->readiness->priceId($plan, 'year') !== null,
            ],
            'limits' => [
                'links' => $plan->link_limit,
                'folders' => $plan->folder_limit,
                'qr_campaigns' => $plan->qr_campaign_limit,
                'bio_pages' => $plan->bio_page_limit,
                'domains' => $plan->domain_limit,
                'pixels' => $plan->pixel_limit,
                'members' => $plan->member_limit,
                'webhooks' => $plan->webhook_limit,
                'link_pages' => $plan->link_page_limit,
                'api_keys' => $plan->api_key_limit,
                'storage_bytes' => $plan->storage_byte_limit,
            ],
        ];
    }
}
