import { Link } from '@inertiajs/react';
import { CreditCard, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { UsageRow, usageOrder } from '@/components/billing/usage-metrics';
import { index as billingIndex } from '@/routes/billing';
import type { WorkspaceRole, WorkspaceUsageMetric } from '@/types/workspaces';

type SidebarPlanUsageProps = {
    plan: string;
    role: WorkspaceRole;
    usage: Record<string, WorkspaceUsageMetric>;
    onBillingClick?: () => void;
};

type PlanUsagePanelProps = {
    role: WorkspaceRole;
    usage: Record<string, WorkspaceUsageMetric>;
    onBillingClick?: () => void;
};

export function PlanUsagePanel({
    role,
    usage,
    onBillingClick,
}: PlanUsagePanelProps) {
    return (
        <>
            <div className="flex flex-col gap-1 border-b pb-3">
                <h2 className="text-sm font-semibold">Usage</h2>
                <p className="text-xs text-muted-foreground">
                    Downgrades keep existing resources; only new over-limit
                    writes are blocked.
                </p>
            </div>
            <div className="grid grid-cols-1 gap-x-4 gap-y-1 py-2 sm:grid-cols-2">
                {usageOrder.map(({ key, noun }) => (
                    <UsageRow
                        key={key}
                        metric={usage[key]}
                        noun={noun}
                        metricKey={key}
                    />
                ))}
            </div>
            <div className="border-t pt-3">
                <Button
                    asChild
                    className="w-full max-lg:h-11"
                    variant={role === 'owner' ? 'default' : 'outline'}
                >
                    <Link href={billingIndex()} onClick={onBillingClick}>
                        {role === 'owner' ? (
                            <>
                                <Sparkles
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                Upgrade plan
                            </>
                        ) : (
                            'Plan details'
                        )}
                    </Link>
                </Button>
            </div>
        </>
    );
}

export function SidebarPlanUsage({
    plan,
    role,
    usage,
    onBillingClick,
}: SidebarPlanUsageProps) {
    const [open, setOpen] = useState(false);
    const closeTimeout = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const triggerRef = useRef<HTMLButtonElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const pointerWithinPanel = useRef(false);
    const suppressFocusOpen = useRef(false);

    function cancelClose() {
        clearTimeout(closeTimeout.current);
        closeTimeout.current = undefined;
    }

    function suppressFocusReopen() {
        suppressFocusOpen.current = true;
        window.setTimeout(() => {
            suppressFocusOpen.current = false;
        }, 250);
    }

    function handleBillingAction() {
        cancelClose();
        pointerWithinPanel.current = false;
        suppressFocusReopen();
        setOpen(false);
        onBillingClick?.();
    }

    function closeSoon() {
        cancelClose();
        closeTimeout.current = setTimeout(() => {
            const activeElement = document.activeElement;
            if (
                pointerWithinPanel.current ||
                triggerRef.current?.contains(activeElement) ||
                panelRef.current?.contains(activeElement)
            ) {
                return;
            }

            setOpen(false);
        }, 180);
    }

    function handleFocusLeave(nextTarget: EventTarget | null) {
        if (
            nextTarget instanceof Node &&
            (triggerRef.current?.contains(nextTarget) ||
                panelRef.current?.contains(nextTarget))
        ) {
            return;
        }

        closeSoon();
    }

    function focusPanelAction() {
        requestAnimationFrame(() => {
            panelRef.current
                ?.querySelector<HTMLElement>('a[href], button:not([disabled])')
                ?.focus();
        });
    }

    useEffect(() => () => clearTimeout(closeTimeout.current), []);

    return (
        <Popover
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);
                if (!nextOpen) {
                    suppressFocusReopen();
                    cancelClose();
                }
            }}
        >
            <PopoverTrigger asChild>
                <SidebarMenuButton
                    ref={triggerRef}
                    type="button"
                    className="h-10 max-lg:h-11"
                    aria-label={`Show usage for ${plan} plan`}
                    onPointerEnter={(event) => {
                        if (event.pointerType !== 'touch') {
                            pointerWithinPanel.current = true;
                            suppressFocusOpen.current = false;
                            cancelClose();
                            setOpen(true);
                        }
                    }}
                    onPointerLeave={(event) => {
                        if (event.pointerType !== 'touch') {
                            pointerWithinPanel.current = false;
                            closeSoon();
                        }
                    }}
                    onFocus={() => {
                        if (suppressFocusOpen.current) {
                            return;
                        }

                        cancelClose();
                        setOpen(true);
                    }}
                    onBlur={(event) => handleFocusLeave(event.relatedTarget)}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            cancelClose();
                            setOpen(true);
                            focusPanelAction();
                        }
                    }}
                >
                    <CreditCard aria-hidden="true" />
                    <span className="truncate">{plan} plan</span>
                    <span className="ml-auto text-[11px] font-normal text-muted-foreground group-data-[collapsible=icon]:hidden">
                        Usage
                    </span>
                </SidebarMenuButton>
            </PopoverTrigger>
            <PopoverContent
                ref={panelRef}
                side="right"
                align="end"
                sideOffset={8}
                aria-label={`${plan} plan usage`}
                className="max-h-[calc(100vh-2rem)] w-[min(34rem,calc(100vw-1rem))] overflow-y-auto rounded-2xl p-4 shadow-sm"
                onPointerEnter={(event) => {
                    if (event.pointerType !== 'touch') {
                        pointerWithinPanel.current = true;
                        cancelClose();
                    }
                }}
                onPointerLeave={(event) => {
                    if (event.pointerType !== 'touch') {
                        pointerWithinPanel.current = false;
                        closeSoon();
                    }
                }}
                onFocusCapture={cancelClose}
                onBlurCapture={(event) => handleFocusLeave(event.relatedTarget)}
                onOpenAutoFocus={(event) => event.preventDefault()}
            >
                <PlanUsagePanel
                    role={role}
                    usage={usage}
                    onBillingClick={handleBillingAction}
                />
            </PopoverContent>
        </Popover>
    );
}
