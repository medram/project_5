export function formatMoney(amount: number, currency: string): string {
    const code = currency.toUpperCase();
    const currencyDigits =
        new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: code,
        }).resolvedOptions().maximumFractionDigits ?? 2;
    const scale =
        code === 'ISK' || code === 'UGX' ? 2 : Math.min(currencyDigits, 2);
    const value = amount / 10 ** scale;

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        minimumFractionDigits: value % 1 === 0 ? 0 : scale,
        maximumFractionDigits: scale,
    }).format(value);
}
