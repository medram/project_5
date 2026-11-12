export type AdminSettingField = {
    key: string;
    label: string;
    type: string;
    options: Record<string, string> | null;
    help: string | null;
    secret: boolean;
    readOnly: boolean;
    source: 'database' | 'environment';
    configured: boolean;
    value: string | number | boolean | string[] | number[] | null;
};

export type AdminSettingSection = {
    key: string;
    label: string;
    description: string;
    fields: AdminSettingField[];
    version: number;
};

export type AdminSettingsNavigationItem = {
    key: string;
    label: string;
    description: string;
};

export type AdminSettingsPageProps = {
    section: AdminSettingSection;
    navigation: AdminSettingsNavigationItem[];
};
