import { App } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import AdminSettingsSection from './section';
import type { AdminSettingField, AdminSettingSection } from '@/types/admin-settings';

function field(overrides: Partial<AdminSettingField> = {}): AdminSettingField {
    return {
        key: 'site_name', label: 'Site name', type: 'string', control: 'input',
        options: null, help: null, placeholder: null, autocomplete: null,
        visibleWhen: null, asset: false, secret: false, configured: false,
        value: 'LinkPilot', ...overrides,
    };
}

function renderSection(
    overrides: Partial<AdminSettingSection> = {},
    errors: Record<string, string> = {},
    canManage = true,
): string {
    const section: AdminSettingSection = {
        key: 'general', label: 'General', description: 'General settings', category: 'platform',
        groups: [{ key: 'identity', label: 'Identity', description: '', fields: [field()] }],
        version: 1, readiness: { state: 'ready', items: [] }, ...overrides,
    };
    const props = {
        section,
        navigation: [{ key: section.key, label: section.label, description: section.description, category: section.category }],
        canManage,
        errors: errors as unknown as Page['props']['errors'],
    };
    return renderToStaticMarkup(
        <App initialPage={{ component: 'admin/settings/section', props, url: '/admin/settings/' + section.key, version: null, flash: {}, rescuedProps: [], rememberedState: {} }} initialComponent={AdminSettingsSection} />,
    );
}

const retryField = field({
    key: 'webhook_retry_delays', label: 'Webhook retry delays', type: 'int_list',
    control: 'number-list', value: [60, 300],
});
const pageListField = field({
    key: 'header_page_ids', label: 'Header pages', type: 'int_list',
    control: 'ordered-list', value: [1, 2],
});
const configuredSecret = field({
    key: 'secret', label: 'Secret key', type: 'string', secret: true,
    configured: true, value: 'sensitive-sentinel',
});

function groupWith(setting: AdminSettingField): AdminSettingSection['groups'] {
    return [{ key: 'settings', label: 'Settings', description: '', fields: [setting] }];
}

describe('admin settings sections', () => {
    it('identifies the current section and preserves the page heading hierarchy', () => {
        const html = renderSection();
        expect(html).toMatch(/<h1[^>]*>Platform settings<\/h1>/);
        expect(html).toMatch(/<h2[^>]*>General<\/h2>/);
        expect(html).toMatch(/<h3[^>]*>Identity<\/h3>/);
        expect(html).toMatch(/aria-current="page"/);
        expect(html).toMatch(/aria-label="Choose settings section: General"/);
    });
    it('hides the SMTP EHLO domain from email settings', () => {
        const mailer = field({ key: 'mailer', label: 'Mailer', value: 'smtp' });
        const smtpHost = field({
            key: 'smtp_host',
            label: 'SMTP host',
            visibleWhen: { mailer: 'smtp' },
        });
        const ehloDomain = field({
            key: 'smtp_ehlo_domain',
            label: 'EHLO domain',
            visibleWhen: { mailer: 'smtp' },
            value: 'mail.example.com',
        });
        const html = renderSection({
            key: 'email',
            label: 'Email',
            groups: [
                {
                    key: 'delivery',
                    label: 'Delivery',
                    description: '',
                    fields: [mailer, smtpHost, ehloDomain],
                },
            ],
        });

        expect(html).toContain('SMTP host');
        expect(html).not.toContain('EHLO domain');
        expect(html).not.toContain('name="smtp_ehlo_domain"');
    });


    it('hides only the generic ready fallback and places view-only access before settings', () => {
        const html = renderSection({
            readiness: { state: 'ready', items: [{ label: 'Configuration', state: 'ready', detail: 'Database-backed settings are available.' }] },
        }, {}, false);
        expect(html).not.toContain('Provider readiness');
        expect(html.indexOf('View-only access')).toBeLessThan(html.indexOf('Identity'));
        expect(html).not.toContain('Save changes');
        expect(html).toMatch(/<input(?=[^>]*name="site_name")(?=[^>]*\sdisabled="")[^>]*>/);

        const incomplete = renderSection({
            readiness: { state: 'incomplete', items: [{ label: 'Configuration', state: 'incomplete', detail: 'Database-backed settings are available.' }] },
        });
        expect(incomplete).toContain('Provider readiness');
    });

    it('uses a numeric retry-delay editor for System', () => {
        const html = renderSection({ key: 'system', label: 'System', groups: groupWith(retryField) });
        expect(html).toMatch(/<input(?=[^>]*type="number")(?=[^>]*aria-label="Retry delay in seconds")[^>]*>/);
        expect(html).toContain('Add delay');
        expect(html).toContain('60 seconds');
        expect(html).toContain('300 seconds');
        expect(html).not.toContain('Add an item…');
    });

    it('uses the published page picker for Menus', () => {
        const html = renderSection({ key: 'menus', label: 'Menus', groups: groupWith(pageListField) });
        expect(html).toContain('Add an item…');
        expect(html).toMatch(/<button(?=[^>]*role="combobox")(?=[^>]*aria-label="Add page to Header pages")[^>]*>/);
        expect(html).toContain('Page #1');
        expect(html).not.toContain('Retry delay in seconds');
    });

    it('masks configured secrets and permits managers to remove them', () => {
        const html = renderSection({ groups: groupWith(configuredSecret) });
        expect(html).toMatch(/<input(?=[^>]*name="secret")(?=[^>]*type="password")[^>]*>/);
        expect(html).not.toContain('sensitive-sentinel');
        expect(html).toContain('Stored');
        expect(html).toContain('Remove');
    });

    it('keeps configured secrets masked and non-removable for view-only access', () => {
        const html = renderSection({ groups: groupWith(configuredSecret) }, {}, false);
        expect(html).toMatch(/<input(?=[^>]*name="secret")(?=[^>]*type="password")(?=[^>]*\sdisabled="")[^>]*>/);
        expect(html).not.toContain('sensitive-sentinel');
        expect(html).toContain('Stored');
        expect(html).not.toContain('Remove');
    });

    it('keeps retry delays readable but prevents mutation for view-only access', () => {
        const html = renderSection({ key: 'system', label: 'System', groups: groupWith(retryField) }, {}, false);
        expect(html).toContain('60 seconds');
        expect(html).not.toContain('Add delay');
        expect(html).not.toContain('Save changes');
        expect(html).toMatch(/<button(?=[^>]*aria-label="Remove delay 1")(?=[^>]*\sdisabled="")[^>]*>/);
    });

    it('shows indexed validation errors both inline and in the field summary', () => {
        const message = 'The retry delay must be at least 1.';
        const html = renderSection(
            { key: 'system', label: 'System', groups: groupWith(retryField) },
            { 'values.webhook_retry_delays.0': message },
        );
        expect(html.match(new RegExp(message.replace('.', '\\.'), 'g'))).toHaveLength(2);
        expect(html).toContain('Webhook retry delays: ' + message);
        expect(html).toMatch(/id="setting-webhook_retry_delays-error"/);
    });

    it('shows the actual stale-version message', () => {
        const message = 'These settings changed in another session. Reload before saving.';
        const html = renderSection({}, { version: message });
        expect(html).toContain('Could not complete the request');
        expect(html).toContain(message);
    });

    it('associates test-email recipient errors with the recipient input', () => {
        const html = renderSection(
            { key: 'email', label: 'Email' },
            { recipient: 'The recipient must be a valid email address.' },
        );
        expect(html).toMatch(/<input(?=[^>]*id="test-email-recipient")(?=[^>]*aria-invalid="true")(?=[^>]*aria-describedby="test-email-recipient-error")[^>]*>/);
        expect(html).toMatch(/id="test-email-recipient-error"[^>]*>The recipient must be a valid email address\./);
        expect(html).not.toContain('Settings were not saved');
    });
});
