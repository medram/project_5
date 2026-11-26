<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminOverrideRevokeRequest;
use App\Http\Requests\Admin\AdminWorkspaceOverrideRequest;
use App\Models\Plan;
use App\Models\Workspace;
use App\Models\WorkspacePlanOverride;
use App\Services\Billing\PayPalReadiness;
use App\Services\Billing\PayPalSubscriptions;
use App\Services\Billing\StripeReadiness;
use App\Services\Billing\WorkspacePlanOverrides;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;
use Throwable;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private readonly WorkspaceUsage $usage,
        private readonly WorkspacePlanOverrides $overrides,
        private readonly PayPalSubscriptions $paypal,
        private readonly PayPalReadiness $paypalReadiness,
        private readonly StripeReadiness $readiness,
        private readonly PlatformAuthorization $authorization,
        private readonly AdminAudit $audit,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $now = now();

        $query = Workspace::query()
            ->with([
                'owner:id,name,email',
                'planOverrides' => fn ($overrides) => $overrides
                    ->with('plan:id,code,name')
                    ->orderByRaw('expires_at IS NULL, expires_at asc')
                    ->orderByDesc('id'),
                'subscriptions' => fn ($subscriptions) => $subscriptions->orderByDesc('created_at')->orderByDesc('id'),
                'paypalSubscriptions' => fn ($subscriptions) => $subscriptions->with('price')->orderByDesc('id'),
            ])
            ->withCount('members');

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($workspaces) => $workspaces
                ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('owner', fn ($owner) => $owner
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])));
        }

        $workspaces = $query
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Workspace $workspace) use ($now): array {
                $effective = $this->usage->effectivePlanFor($workspace);
                $subscription = $workspace->subscriptions->first();
                $paypal = $workspace->paypalSubscriptions->first(fn ($item) => in_array($item->status, ['ACTIVE', 'SUSPENDED', 'PAYMENT_FAILED'], true))
                    ?? $workspace->paypalSubscriptions->first();
                $showPayPal = $paypal !== null && ($effective['source'] === 'paypal'
                    || ($effective['source'] !== 'stripe' && ($subscription === null || ! in_array($subscription->stripe_status, ['active', 'trialing'], true))));
                $activeOverrides = $workspace->planOverrides
                    ->filter(fn (WorkspacePlanOverride $override): bool => $override->revoked_at === null
                        && ($override->starts_at === null || $override->starts_at->lessThanOrEqualTo($now))
                        && ($override->expires_at === null || $override->expires_at->greaterThan($now)))
                    ->values();

                return [
                    'workspace' => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'membersCount' => (int) $workspace->members_count,
                        'owner' => $workspace->owner === null ? null : [
                            'id' => $workspace->owner->id,
                            'name' => $workspace->owner->name,
                            'email' => $workspace->owner->email,
                        ],
                    ],
                    'effectivePlan' => [
                        'code' => $effective['plan']->code,
                        'name' => $effective['plan']->name,
                        'source' => $effective['source'],
                        'overrideExpiresAt' => $effective['override']?->expires_at?->toISOString(),
                    ],
                    'provider' => [
                        'name' => $showPayPal ? 'paypal' : ($subscription === null ? null : 'stripe'),
                        'configured' => $showPayPal ? $this->paypalReadiness->configured() : $this->readiness->configured(),
                        'status' => $showPayPal ? $paypal->status : $subscription?->stripe_status,
                        'priceId' => $showPayPal ? $paypal->price?->paypal_plan_id : $subscription?->stripe_price,
                        'amount' => $showPayPal ? $paypal->price?->unit_amount : null,
                        'currency' => $showPayPal ? $paypal->price?->currency : null,
                        'interval' => $showPayPal ? $paypal->price?->interval : null,
                        'currentPeriodEnd' => $showPayPal ? $paypal->next_billing_at?->toISOString() : $subscription?->currentPeriodEnd()?->toISOString(),
                        'endsAt' => $showPayPal ? null : $subscription?->ends_at?->toISOString(),
                        'canCancel' => $showPayPal
                            ? $this->paypalReadiness->configured() && in_array($paypal->status, ['ACTIVE', 'SUSPENDED', 'PAYMENT_FAILED'], true)
                            : ($subscription !== null && $subscription->ends_at === null && $this->readiness->configured()),
                        'canResume' => ! $showPayPal && $subscription !== null
                            && $subscription->ends_at !== null
                            && $subscription->ends_at->isFuture()
                            && $this->readiness->configured(),
                    ],
                    'overrides' => $activeOverrides
                        ->map(fn (WorkspacePlanOverride $override): array => [
                            'id' => $override->id,
                            'plan' => $override->plan === null ? null : [
                                'code' => $override->plan->code,
                                'name' => $override->plan->name,
                            ],
                            'source' => $override->source,
                            'reason' => $override->reason,
                            'startsAt' => $override->starts_at?->toISOString(),
                            'expiresAt' => $override->expires_at?->toISOString(),
                        ])
                        ->all(),
                ];
            });

        return Inertia::render('admin/subscriptions/index', [
            'workspaces' => $workspaces,
            'filters' => ['q' => $search],
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get(['code', 'name'])
                ->map(fn (Plan $plan): array => ['code' => $plan->code, 'name' => $plan->name])
                ->all(),
            'sources' => WorkspacePlanOverrides::SOURCES,
            'readiness' => [
                'configured' => $this->readiness->configured(),
                'missing' => $this->readiness->missing(),
            ],
        ]);
    }

    public function cancel(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->assertProviderManagement($request);
        $provider = $request->validate(['provider' => ['nullable', 'in:stripe,paypal']])['provider'] ?? 'stripe';
        if ($provider === 'paypal') {
            $this->paypal->cancel($workspace);
            $actor = $request->user();
            abort_if($actor === null, 403);
            $this->audit->record($actor, PlatformPermission::AdminBillingManage, 'cancel_subscription', $workspace, null, [
                'provider' => 'paypal',
                'provider_status' => 'CANCELLED',
            ]);

            return back(fallback: route('admin.subscriptions.index'));
        }
        $this->assertStripeReady();
        $subscription = $this->latestSubscription($workspace);

        if ($subscription === null) {
            throw ValidationException::withMessages(['provider' => 'This workspace has no provider subscription.']);
        }

        if ($subscription->ends_at !== null) {
            throw ValidationException::withMessages(['provider' => 'This subscription is already scheduled to end.']);
        }

        try {
            $subscription->cancel();
        } catch (Throwable) {
            throw ValidationException::withMessages(['provider' => 'Stripe did not accept the cancellation. Check provider readiness and try again.']);
        }

        $actor = $request->user();
        abort_if($actor === null, 403);
        $this->audit->record(
            $actor,
            PlatformPermission::AdminBillingManage,
            'cancel_subscription',
            $workspace,
            null,
            ['provider_status' => $subscription->stripe_status],
        );

        return back(fallback: route('admin.subscriptions.index'));
    }

    public function resume(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->assertProviderManagement($request);
        $this->assertStripeReady();
        $subscription = $this->latestSubscription($workspace);

        if ($subscription === null || ! $subscription->onGracePeriod()) {
            throw ValidationException::withMessages(['provider' => 'This subscription is not within a restorable grace period.']);
        }

        try {
            $subscription->resume();
        } catch (Throwable) {
            throw ValidationException::withMessages(['provider' => 'Stripe did not accept the resume. Check provider readiness and try again.']);
        }

        $actor = $request->user();
        abort_if($actor === null, 403);
        $this->audit->record(
            $actor,
            PlatformPermission::AdminBillingManage,
            'resume_subscription',
            $workspace,
            null,
            ['provider_status' => $subscription->stripe_status],
        );

        return back(fallback: route('admin.subscriptions.index'));
    }

    public function storeOverride(AdminWorkspaceOverrideRequest $request, Workspace $workspace): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);
        abort_unless($this->authorization->allows($actor, PlatformPermission::AdminBillingManage), 403);

        $validated = $request->validated();
        $plan = Plan::query()
            ->where('code', $validated['plan'])
            ->where('is_active', true)
            ->sole();
        $expiresAt = isset($validated['expires_at'])
            ? CarbonImmutable::parse($validated['expires_at'], 'UTC')->endOfDay()
            : null;

        $override = $this->overrides->assign(
            $workspace,
            $plan,
            $actor,
            $validated['source'],
            trim($validated['reason']),
            null,
            $expiresAt,
        );

        if ($override->wasRecentlyCreated) {
            $this->audit->record(
                $actor,
                PlatformPermission::AdminBillingManage,
                'assign_override',
                $workspace,
                trim($validated['reason']),
                [
                    'plan' => $plan->code,
                    'source' => $validated['source'],
                    'expires_at' => $expiresAt?->toDateString(),
                ],
            );
        }

        return back(fallback: route('admin.subscriptions.index'));
    }

    public function revokeOverride(
        AdminOverrideRevokeRequest $request,
        Workspace $workspace,
        WorkspacePlanOverride $override,
    ): RedirectResponse {
        abort_unless((int) $override->workspace_id === (int) $workspace->getKey(), 404);
        $actor = $request->user();
        abort_if($actor === null, 403);
        abort_unless($this->authorization->allows($actor, PlatformPermission::AdminBillingManage), 403);

        $wasActive = $override->revoked_at === null;
        $this->overrides->revoke($override, $actor, trim($request->validated()['reason']));

        if ($wasActive) {
            $this->audit->record(
                $actor,
                PlatformPermission::AdminBillingManage,
                'revoke_override',
                $workspace,
                trim($request->validated()['reason']),
                ['override_id' => $override->id],
            );
        }

        return back(fallback: route('admin.subscriptions.index'));
    }

    private function assertProviderManagement(Request $request): void
    {
        $actor = $request->user();
        abort_if($actor === null, 403);
        abort_unless($this->authorization->allows($actor, PlatformPermission::AdminBillingManage), 403);
    }

    private function assertStripeReady(): void
    {
        if (! $this->readiness->configured()) {
            throw ValidationException::withMessages(['provider' => 'Stripe is not configured; provider actions are disabled.']);
        }
    }

    private function latestSubscription(Workspace $workspace): ?Subscription
    {
        return $workspace->subscriptions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
