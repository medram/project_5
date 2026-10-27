import {
    Activity,
    BarChart3,
    Folder,
    Globe2,
    Layers,
    Link2,
    Megaphone,
    PanelsTopLeft,
    QrCode,
} from 'lucide-react';
import { index as analyticsIndex } from '@/routes/analytics';
import { index as bioPagesIndex } from '@/routes/bio-pages';
import { index as customDomainsIndex } from '@/routes/custom-domains';
import { index as foldersIndex } from '@/routes/folders';
import { index as linksIndex } from '@/routes/links';
import { index as linkPagesIndex } from '@/routes/link-pages';
import { index as overlaysIndex } from '@/routes/overlays';
import { index as qrCodesIndex } from '@/routes/qr-codes';
import { index as trackingPixelsIndex } from '@/routes/tracking-pixels';
import type { NavItem } from '@/types';

export type WorkspaceNavSection = {
    title: string;
    items: NavItem[];
};

export const workspaceNavSections: WorkspaceNavSection[] = [
    {
        title: 'Primary',
        items: [
            { title: 'Short links', href: linksIndex(), icon: Link2 },
            { title: 'QR codes', href: qrCodesIndex(), icon: QrCode },
            {
                title: 'Link in bio',
                href: bioPagesIndex(),
                icon: PanelsTopLeft,
            },
            { title: 'Insights', href: analyticsIndex(), icon: BarChart3 },
        ],
    },
    {
        title: 'Resources',
        items: [
            { title: 'Folders', href: foldersIndex(), icon: Folder },
            {
                title: 'Custom domains',
                href: customDomainsIndex(),
                icon: Globe2,
            },
            { title: 'CTA overlays', href: overlaysIndex(), icon: Layers },
            {
                title: 'Tracking pixels',
                href: trackingPixelsIndex(),
                icon: Activity,
            },
            { title: 'Link pages', href: linkPagesIndex(), icon: Megaphone },
        ],
    },
];
