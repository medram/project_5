import { App } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import AdminUserShow from './show';
import type { AdminUserDetailProps } from '@/types/admin';

const detail: AdminUserDetailProps = {
    account: {
        id: 2,
        name: 'Demo Owner',
        email: 'user@linkpilot.test',
        avatarUrl: null,
        status: 'active',
        emailVerified: true,
        emailVerifiedAt: null,
        roles: [],
        createdAt: null,
        updatedAt: null,
        lastSeenAt: null,
        suspendedAt: null,
        deletionRequestedAt: null,
        purgeAfter: null,
        isSelf: false,
    },
    security: {
        twoFactorEnabled: false,
        passkeyCount: 0,
        sessionCount: 0,
        latestSessionAt: null,
        tokenCount: 0,
        tokens: [],
    },
    workspaces: [{
        id: 1,
        name: "Demo Owner's workspace",
        role: 'owner',
        isOwner: true,
        isPersonal: true,
        membersCount: 1,
        linksCount: 0,
        bioPagesCount: 0,
        domainsCount: 0,
        plan: {
            code: 'pro',
            name: 'Pro',
            source: 'override',
            subscriptionStatus: null,
        },
    }],
    roles: [],
    plans: [
        { code: 'free', name: 'Free' },
        { code: 'pro', name: 'Pro' },
        { code: 'business', name: 'Business' },
    ],
};

function renderUserDetail(): string {
    const props = {
        ...detail,
        auth: {
            user: null,
            isAdmin: true,
            canSearchPlatform: true,
            adminAbilities: ['admin.billing.manage'],
        },
        errors: {},
        name: 'LinkPilot',
    } as Page['props'];

    return renderToStaticMarkup(
        <App
            initialPage={{
                component: 'admin/users/show',
                props,
                url: '/admin/users/2',
                version: null,
                flash: {},
                rescuedProps: [],
                rememberedState: {},
            }}
            initialComponent={AdminUserShow}
        />,
    );
}

describe('admin user plan editor', () => {
    it('shows the current personal plan and an update action before a change', () => {
        const html = renderUserDetail();
        const planControl = html.match(
            /<button(?=[^>]*aria-label="Personal workspace plan")[^>]*>([\s\S]*?)<\/button>/,
        );

        expect(planControl?.[1]).toContain('Pro');
        expect(planControl?.[1]).not.toContain('Select a plan');
        expect(html).toMatch(
            /<button(?=[^>]*type="button")(?=[^>]*disabled="")[^>]*>[\s\S]*?Update plan[\s\S]*?<\/button>/,
        );
    });
});
