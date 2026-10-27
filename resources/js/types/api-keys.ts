export type ApiKey = {
    id: number;
    name: string;
    abilities: string[];
    createdAt: string | null;
    expiresAt: string | null;
    lastUsedAt: string | null;
};

export type ApiKeyAbility = {
    value: string;
    label: string;
};

export type ApiKeysPageProps = {
    keys: ApiKey[];
    availableAbilities: ApiKeyAbility[];
    oneTimeToken: string | null;
};
