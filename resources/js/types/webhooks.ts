export type Webhook = {
    id: number;
    name: string;
    url: string;
    events: string[];
    isActive: boolean;
    revision: number;
    secretRotatedAt: string | null;
    deliveriesCount: number;
};

export type WebhookDelivery = {
    id: number;
    eventUuid: string;
    eventName: string;
    attemptCount: number;
    state: string;
    responseCode: number | null;
    failureDetail: string | null;
    completedAt: string | null;
};

export type WebhooksPageProps = {
    webhooks: Webhook[];
    supportedEvents: string[];
    oneTimeSecret: string | null;
};
