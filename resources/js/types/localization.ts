export type AdminLocale = {
    code: string;
    name: string;
    direction: string;
    isPublished: boolean;
    isDefault: boolean;
    translationsCount: number;
};

export type AdminTranslation = { key: string; value: string };

export type AdminLocalizationsPageProps = {
    locales: AdminLocale[];
    selected: string | null;
    translations: {
        data: AdminTranslation[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { locale: string | null; q: string };
};
