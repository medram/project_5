import type { AdminSettingField } from '@/types/admin-settings';

export type SettingValue = string | number | boolean | string[] | number[] | null;
export type FormValues = Record<string, SettingValue>;

export function isSettingVisible(field: AdminSettingField, values: FormValues): boolean {
    return field.visibleWhen === null || Object.entries(field.visibleWhen).every(
        ([key, expected]) => values[key] === expected,
    );
}

export function collectSettingsChanges(
    fields: readonly AdminSettingField[],
    values: FormValues,
    initial: FormValues,
): { visible: FormValues; hasHiddenChanges: boolean } {
    const visible: FormValues = {};
    let hasHiddenChanges = false;

    for (const field of fields) {
        const value = values[field.key];
        if (JSON.stringify(value) === JSON.stringify(initial[field.key])) {
            continue;
        }
        if (!isSettingVisible(field, values)) {
            hasHiddenChanges = true;
        } else if (!field.secret || (typeof value === 'string' && value.trim() !== '')) {
            visible[field.key] = value;
        }
    }

    return { visible, hasHiddenChanges };
}
