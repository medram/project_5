export type WebhookHealth = {
    counts: {
        pending: number;
        delivered: number;
        failed: number;
        cancelled: number;
    };
    latestState: 'pending' | 'delivered' | 'failed' | 'cancelled' | null;
};

export type Webhook = {
    id: number;
    name: string;
    url: string | null;
    events: string[];
    isActive: boolean;
    revision: number;
    secretRotatedAt: string | null;
    deliveriesCount: number;
    health: WebhookHealth | null;
};

export type WebhookDelivery = {
    id: number;
    sourceDeliveryId: number | null;
    eventUuid: string;
    eventName: string;
    attemptCount: number;
    state: 'pending' | 'delivered' | 'failed' | 'cancelled';
    responseCode: number | null;
    failureDetail: string | null;
    responseExcerpt: string | null;
    completedAt: string | null;
};

export type WebhooksPageProps = {
    webhooks: Webhook[];
    supportedEvents: string[];
    canManageIntegrations: boolean;
    oneTimeSecret: string | null;
};
