export type AnalyticsDay = {
    date: string;
    events: number;
};

export type AnalyticsWindow = {
    total: number;
    daily: AnalyticsDay[];
};

export type AnalyticsWindows = Record<7 | 30 | 90, AnalyticsWindow>;

export type AccountAnalyticsProps = {
    windows: AnalyticsWindows;
};
