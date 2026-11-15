export type AdminSettingsCategory =
    | 'platform'
    | 'product'
    | 'access'
    | 'revenue'
    | 'operations';

export type AdminSettingField = {
    key: string;
    label: string;
    type: string;
    control: string;
    options: Record<string, string> | null;
    help: string | null;
    placeholder: string | null;
    autocomplete: string | null;
    visibleWhen: Record<string, string | boolean> | null;
    asset: boolean;
    secret: boolean;
    configured: boolean;
    value: string | number | boolean | string[] | number[] | null;
};

export type AdminSettingGroup = {
    key: string;
    label: string;
    description: string;
    fields: AdminSettingField[];
};

export type AdminSettingSection = {
    key: string;
    label: string;
    description: string;
    category: AdminSettingsCategory;
    groups: AdminSettingGroup[];
    version: number;
    readiness: {
        state: 'ready' | 'incomplete' | 'disabled' | 'development';
        items: Array<{ label: string; state: string; detail: string }>;
    };
};

export type AdminSettingsNavigationItem = {
    key: string;
    label: string;
    description: string;
    category: AdminSettingsCategory;
};

export type AdminSettingsPageProps = {
    section: AdminSettingSection;
    navigation: AdminSettingsNavigationItem[];
    canManage: boolean;
};
