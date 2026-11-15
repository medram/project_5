let regionNames: Intl.DisplayNames | null = null;

try {
    regionNames = new Intl.DisplayNames('en', { type: 'region' });
} catch {
    regionNames = null;
}

export function countryName(code: string): string {
    if (code === 'Other' || !/^[A-Z]{2}$/.test(code)) {
        return code;
    }

    try {
        return regionNames?.of(code) ?? code;
    } catch {
        return code;
    }
}
