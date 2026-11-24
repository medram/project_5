import type { CustomDomain, CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption, FolderPayload } from '@/types/folders';
import type { TrackingPixel, TrackingPixelTarget } from '@/types/link-pages';
import type {
    CountryOption,
    LinkPageOption,
    LinkPayload,
    LinkTagOption,
} from '@/types/links';
import type { LinkOverlay, LinkOverlayOption } from '@/types/overlays';
import type { QrCodeCampaign, QrCodeLinkOption } from '@/types/qr-codes';

export type AdminContentEditor =
    | {
          resource: 'links';
          backUrl: string;
          record: LinkPayload;
          folders: FolderOption[];
          customDomains: CustomDomainOption[];
          linkOverlays: LinkOverlayOption[];
          linkPages: LinkPageOption[];
          tagOptions: LinkTagOption[];
          countries: CountryOption[];
          canManageApprovals: boolean;
      }
    | {
          resource: 'qr-codes';
          backUrl: string;
          record: QrCodeCampaign;
          availableLinks: QrCodeLinkOption[];
      }
    | {
          resource: 'folders';
          backUrl: string;
          record: FolderPayload;
      }
    | {
          resource: 'custom-domains';
          backUrl: string;
          record: CustomDomain;
      }
    | {
          resource: 'link-overlays';
          backUrl: string;
          record: LinkOverlay;
      }
    | {
          resource: 'pixels';
          backUrl: string;
          record: TrackingPixel;
          availableTargets: TrackingPixelTarget[];
      };

export type AdminContentRow = {
    name: string;
    identifier: string | null;
    workspace: string | null;
    owner: string | null;
    state: string;
    type: string;
    provider: string | null;
    counts: Array<{
        label: string;
        value: number;
    }>;
    createdAt: string | null;
    updatedAt: string | null;
    extra: Record<string, string | null>;
    editUrl?: string;
};

export type AdminContentResource = {
    key: string;
    title: string;
    description: string;
    identifierLabel: string | null;
    stateOptions: Array<{
        value: string;
        label: string;
    }>;
};

export type AdminContentPageProps = {
    resource: AdminContentResource;
    rows: {
        data: AdminContentRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters: {
        q: string;
        state: string | null;
        sort: string;
        direction: string;
    };
    editing: AdminContentEditor | null;
};
