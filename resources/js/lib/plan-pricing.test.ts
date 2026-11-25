import { describe, expect, it } from 'vite-plus/test';
import type { AdminPlanRecord } from '@/types/admin-billing';
import { currentPlanPrice, currentPlanPriceIsVerified } from './plan-pricing';

function plan(overrides: Partial<AdminPlanRecord> = {}): AdminPlanRecord {
    return {
        code: 'pro',
        name: 'Pro',
        currency: 'usd',
        monthlyPrice: 2900,
        annualPrice: 29000,
        description: null,
        featured: false,
        sortOrder: 1,
        isActive: true,
        isBuiltIn: false,
        canDelete: true,
        deleteBlockedReason: null,
        stripeMonthlyPriceId: 'price_month',
        stripeYearlyPriceId: 'price_year',
        paypalMonthlyPlanId: null,
        paypalYearlyPlanId: null,
        paypalPrices: [],
        paypalCheckout: { month: false, year: false },
        prices: [
            {
                id: 'price_month',
                interval: 'month',
                currency: 'usd',
                amount: 2900,
                verified: true,
            },
            {
                id: 'price_year',
                interval: 'year',
                currency: 'usd',
                amount: 29000,
                verified: true,
            },
        ],
        checkout: { month: true, year: true },
        limits: {
            links: null,
            folders: null,
            qr_campaigns: null,
            bio_pages: null,
            domains: null,
            pixels: null,
            members: null,
            webhooks: null,
            link_pages: null,
            api_keys: null,
            storage_bytes: null,
        },
        ...overrides,
    };
}

describe('currentPlanPrice', () => {
    it('returns the current mapping instead of an older history row', () => {
        const record = plan({
            prices: [
                {
                    id: 'price_old',
                    interval: 'month',
                    currency: 'usd',
                    amount: 1900,
                    verified: true,
                },
                {
                    id: 'price_month',
                    interval: 'month',
                    currency: 'usd',
                    amount: 2900,
                    verified: true,
                },
            ],
        });

        expect(currentPlanPrice(record, 'month')?.amount).toBe(2900);
        expect(currentPlanPrice(record, 'month')?.id).toBe('price_month');
    });

    it('ignores a same-id row that belongs to the other interval', () => {
        const record = plan({
            stripeMonthlyPriceId: 'price_shared',
            prices: [
                {
                    id: 'price_shared',
                    interval: 'year',
                    currency: 'usd',
                    amount: 29000,
                    verified: true,
                },
            ],
        });

        expect(currentPlanPrice(record, 'month')).toBeNull();
    });

    it('returns null when no provider price is mapped', () => {
        expect(
            currentPlanPrice(plan({ stripeMonthlyPriceId: null }), 'month'),
        ).toBeNull();
        expect(
            currentPlanPrice(plan({ stripeYearlyPriceId: '' }), 'year'),
        ).toBeNull();
    });

    it('counts only verified prices with an amount', () => {
        const unverified = plan({
            prices: [
                {
                    id: 'price_month',
                    interval: 'month',
                    currency: 'usd',
                    amount: 2900,
                    verified: false,
                },
            ],
        });
        const missingAmount = plan({
            prices: [
                {
                    id: 'price_month',
                    interval: 'month',
                    currency: 'usd',
                    amount: null,
                    verified: true,
                },
            ],
        });

        expect(currentPlanPriceIsVerified(plan(), 'month')).toBe(true);
        expect(currentPlanPriceIsVerified(unverified, 'month')).toBe(false);
        expect(currentPlanPriceIsVerified(missingAmount, 'month')).toBe(false);
    });
});
