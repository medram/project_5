import { describe, expect, it } from 'vite-plus/test';
import { formatMoney } from './money';

describe('formatMoney', () => {
    it('preserves nonzero USD cents', () => {
        expect(formatMoney(3499, 'usd')).toBe('$34.99');
        expect(formatMoney(3400, 'usd')).toBe('$34');
    });

    it('uses whole yen for JPY', () => {
        expect(formatMoney(3499, 'jpy')).toBe('¥3,499');
    });

    it('uses Stripe two-decimal compatibility for ISK and UGX', () => {
        expect(formatMoney(3499, 'isk')).toMatch(/34\.99/);
        expect(formatMoney(3499, 'ugx')).toMatch(/34\.99/);
    });
});
