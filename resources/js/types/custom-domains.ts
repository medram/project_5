export type CustomDomain = {
    id: number;
    hostname: string;
    status: 'pending_dns' | 'pending_tls' | 'active' | 'failed';
    verificationToken?: string;
    txtHost?: string;
    txtValue?: string;
    challengeUrl?: string;

    verifiedAt: string | null;
    lastCheckedAt: string | null;
    failureReason?: string | null;
    linksCount: number;
};

export type CustomDomainOption = {
    id: number;
    hostname: string;
};

export type CustomDomainsPageProps = {
    domains: CustomDomain[];
    canManageIntegrations: boolean;
};
