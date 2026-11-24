import { describe, expect, it } from 'vite-plus/test';
import { collectSettingsChanges } from './settings-form';
import type { AdminSettingField } from '@/types/admin-settings';

const base: AdminSettingField = {
    key: '',
    label: '',
    type: 'string',
    control: 'input',
    options: null,
    help: null,
    placeholder: null,
    autocomplete: null,
    visibleWhen: null,
    asset: false,
    secret: false,
    configured: false,
    value: '',
};
const fields: AdminSettingField[] = [
    { ...base, key: 'mailer', label: 'Mailer' },
    {
        ...base,
        key: 'smtp_host',
        label: 'SMTP host',
        visibleWhen: { mailer: 'smtp' },
    },
    { ...base, key: 'sender_name', label: 'Sender name' },
    { ...base, key: 'smtp_password', label: 'SMTP password', secret: true },
];
const initial = {
    mailer: 'log',
    smtp_host: '',
    sender_name: 'Sender',
    smtp_password: '',
};

describe('collectSettingsChanges', () => {
    it('reports a changed hidden SMTP host without sending it', () => {
        expect(
            collectSettingsChanges(
                fields,
                { ...initial, smtp_host: 'mail.example' },
                initial,
            ),
        ).toEqual({ visible: {}, hasHiddenChanges: true });
    });

    it('sends visible changes while reporting a changed hidden host', () => {
        expect(
            collectSettingsChanges(
                fields,
                {
                    ...initial,
                    smtp_host: 'mail.example',
                    sender_name: 'New sender',
                },
                initial,
            ),
        ).toEqual({
            visible: { sender_name: 'New sender' },
            hasHiddenChanges: true,
        });
    });

    it('sends the changed mailer and host when SMTP is selected', () => {
        expect(
            collectSettingsChanges(
                fields,
                { ...initial, mailer: 'smtp', smtp_host: 'mail.example' },
                initial,
            ),
        ).toEqual({
            visible: { mailer: 'smtp', smtp_host: 'mail.example' },
            hasHiddenChanges: false,
        });
    });

    it('omits whitespace-only secret replacements', () => {
        expect(
            collectSettingsChanges(
                fields,
                { ...initial, smtp_password: '  ' },
                initial,
            ),
        ).toEqual({ visible: {}, hasHiddenChanges: false });
    });
});
