<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanMetric;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPlanUpdateRequest;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PlanCatalogPresenter;
use App\Services\Billing\StripeReadiness;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    public function __construct(
        private readonly StripeReadiness $readiness,
        private readonly PlanCatalogPresenter $catalog,
        private readonly AdminAudit $audit,
        private readonly PlatformAuthorization $authorization,
    ) {}

    public function index(): Response
    {
        $referencedPrices = DB::table('subscriptions')->whereNotNull('stripe_price')->pluck('stripe_price')
            ->merge(DB::table('subscription_items')->whereNotNull('stripe_price')->pluck('stripe_price'))
            ->unique()
            ->flip();
        $plans = $this->catalog
            ->ordered(Plan::query()->with('stripePrices')->withCount('overrides')->get())
            ->map(fn (Plan $plan): array => $this->present($plan, $referencedPrices))
            ->values()
            ->all();

        return Inertia::render('admin/plans/index', [
            'plans' => $plans,
            'subscriptionCurrency' => config('cashier.currency', 'usd'),
            'readiness' => [
                'checkoutReady' => $this->readiness->checkoutReady(),
                'missing' => $this->readiness->missing(),
            ],
        ]);
    }

    public function store(AdminPlanUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($request, $validated): void {
            $attributes = [
                'code' => $validated['code'],
                'name' => trim($validated['name']),
                'currency' => strtolower((string) config('cashier.currency', 'usd')),
                'monthly_price' => 0,
                'annual_price' => 0,
                'is_active' => (bool) $validated['is_active'],
                'display_metadata' => [
                    'description' => $validated['description'] ?? null,
                    'featured' => (bool) $validated['featured'],
                    'sort_order' => (int) $validated['sort_order'],
                ],
            ];
            foreach (PlanMetric::cases() as $metric) {
                $attributes[$metric->planColumn()] = $validated['limits'][$metric->value];
            }
            $plan = Plan::query()->create($attributes);
            $this->audit->record($request->user(), PlatformPermission::AdminBillingManage, 'create_plan', $plan, null, [
                'code' => $plan->code,
                'limits' => $validated['limits'],
            ]);
        });

        return back(fallback: route('admin.plans.index'));
    }

    public function update(AdminPlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validated();
        if ($plan->code === 'free' && ! $validated['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'The free plan must remain active.']);
        }
        $plan = DB::transaction(function () use ($plan, $validated, $request): Plan {
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->getKey());
            /** @var Plan $locked */
            $metadata = $locked->display_metadata ?? [];
            $metadata['description'] = $validated['description'] ?? null;
            $metadata['featured'] = (bool) $validated['featured'];
            $metadata['sort_order'] = (int) $validated['sort_order'];

            $attributes = [
                'name' => trim($validated['name']),
                'display_metadata' => $metadata,
                'is_active' => (bool) $validated['is_active'],
            ];
            foreach (PlanMetric::cases() as $metric) {
                $attributes[$metric->planColumn()] = $validated['limits'][$metric->value];
            }
            $locked->forceFill($attributes)->save();

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
                    'limits' => $validated['limits'],
                ],
            );

            return $locked;
        });

        return back(fallback: route('admin.plans.index'));
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        DB::transaction(function () use ($request, $plan): void {
            $this->authorization->lockSuperAdmin();
            $actor = User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(
                $this->authorization->allows($actor, PlatformPermission::AdminAccess)
                && $this->authorization->allows($actor, PlatformPermission::AdminBillingManage),
                403,
            );

            $locked = Plan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            /** @var Plan $locked */
            if (in_array($locked->code, ['free', 'pro', 'business'], true)) {
                throw ValidationException::withMessages([
                    'plan' => 'Built-in plans cannot be deleted. Deactivate the plan instead.',
                ]);
            }

            if ($locked->overrides()->exists()) {
                throw ValidationException::withMessages([
                    'plan' => 'This plan is referenced by workspace plan history and cannot be deleted. Deactivate it instead.',
                ]);
            }

            $priceIds = $locked->stripePrices()->pluck('stripe_price_id')
                ->push($locked->stripe_monthly_price_id, $locked->stripe_yearly_price_id)
                ->filter()
                ->unique();

            $inUse = $priceIds->isNotEmpty() && (
                DB::table('subscriptions')->whereIn('stripe_price', $priceIds)->exists()
                || DB::table('subscription_items')->whereIn('stripe_price', $priceIds)->exists()
            );
            if ($inUse) {
                throw ValidationException::withMessages([
                    'plan' => 'A provider subscription still uses this plan. Cancel or migrate it first.',
                ]);
            }

            $this->audit->record($actor, PlatformPermission::AdminBillingManage, 'delete_plan', $locked, null, ['code' => $locked->code]);
            $locked->delete();
        });

        return back(fallback: route('admin.plans.index'));
    }

    /**
     * @param  Collection<string, int>  $referencedPrices
     * @return array<string, mixed>
     */
    private function present(Plan $plan, Collection $referencedPrices): array
    {
        $metadata = $plan->display_metadata ?? [];

        $isBuiltIn = in_array($plan->code, ['free', 'pro', 'business'], true);
        $priceIds = $plan->stripePrices->pluck('stripe_price_id')
            ->push($plan->stripe_monthly_price_id, $plan->stripe_yearly_price_id)
            ->filter()
            ->unique();
        $reason = match (true) {
            $isBuiltIn => 'Built-in plans cannot be deleted.',
            (int) $plan->overrides_count > 0 => 'Referenced by workspace plan history.',
            $priceIds->contains(fn (string $id): bool => $referencedPrices->has($id)) => 'A provider subscription still uses this plan.',
            default => null,
        };

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
            'isBuiltIn' => $isBuiltIn,
            'canDelete' => $reason === null,
            'deleteBlockedReason' => $reason,
            'stripeMonthlyPriceId' => $plan->stripe_monthly_price_id,
            'stripeYearlyPriceId' => $plan->stripe_yearly_price_id,
            'prices' => $plan->stripePrices->map(fn ($price): array => [
                'id' => $price->stripe_price_id,
                'interval' => $price->interval,
                'currency' => $price->currency,
                'amount' => $price->unit_amount,
                'verified' => $price->verified_at !== null,
            ])->values()->all(),
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
