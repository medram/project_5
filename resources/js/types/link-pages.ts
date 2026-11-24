export type TrackingPixelProvider = 'meta_pixel' | 'google_analytics';

export type TrackingPixelRule = {
    id: number;
    targetType: 'bio_page' | 'link_page' | 'link_overlay';
    targetId: number;
    trigger: 'page_view' | 'button_click' | 'lead_submit';
};

export type TrackingPixelTarget = {
    type: TrackingPixelRule['targetType'];
    id: number;
    name: string;
};

export type TrackingPixel = {
    id: number;
    name: string;
    provider: TrackingPixelProvider;
    providerId: string;
    isActive: boolean;
    rules: TrackingPixelRule[];
};

export type TrackingPixelsPageProps = {
    pixels: TrackingPixel[];
    canManageIntegrations: boolean;
    availableTargets: TrackingPixelTarget[];
};

export type PublicTrackingPixel = {
    provider: TrackingPixelProvider;
    identifier: string;
};

export type PublicTrackingPixelsByTrigger = Record<
    TrackingPixelRule['trigger'],
    PublicTrackingPixel[]
>;

export type LinkPageTemplate = 'standard' | 'profile' | 'campaign';

export type LinkPage = {
    id: number;
    name: string;
    slug: string;
    title: string;
    description: string | null;
    template: LinkPageTemplate;
    isPublished: boolean;
    itemsCount: number;
    publicUrl: string;
};
export type LinkPageVariant = {
    id: number;
    name: string;
    weight: number;
    isControl: boolean;
    isActive: boolean;
};

export type LinkPageItem = {
    linkPageVariantId: number;
    type: 'link';
    id: number;
    title: string;
    destinationUrl: string;
    position: number;
    isActive: boolean;
    isPreviewVisible: boolean;
};

export type LinkPageFormField = {
    id: string;
    label: string;
    type: 'text' | 'email' | 'textarea' | 'select';
    required: boolean;
    options?: string[];
};
type LinkPageEditorBlockBase = {
    id: number;
    linkPageVariantId: number;
    position: number;
    isActive: boolean;
    isPreviewVisible: boolean;
    startsAt: string | null;
    endsAt: string | null;
};

export type LinkPageEditorBlock =
    | (LinkPageEditorBlockBase & {
          type: 'text';
          text: string;
      })
    | (LinkPageEditorBlockBase & {
          type: 'image';
          altText: string;
          imageUrl: string | null;
      })
    | (LinkPageEditorBlockBase & {
          type: 'file';
          fileName: string | null;
      })
    | (LinkPageEditorBlockBase & {
          type: 'form';
          fields: LinkPageFormField[];
      });

export type LinkPageEditorItem = LinkPageItem | LinkPageEditorBlock;

export type LinkPagePresentationItem =
    | {
          type: 'link';
          id: number;
          title: string;
          url?: string;
          position: number;
      }
    | {
          type: 'text';
          id: number;
          text: string;
          position: number;
      }
    | {
          type: 'image';
          id: number;
          altText: string;
          imageUrl: string | null;
          position: number;
      }
    | {
          type: 'file';
          id: number;
          fileName: string | null;
          fileUrl: string | null;
          position: number;
      }
    | {
          type: 'form';
          id: number;
          fields: LinkPageFormField[];
          submitUrl?: string;
          position: number;
      };

export type LinkPageIndexProps = {
    linkPages: LinkPage[];
};

export type LinkPageEditorProps = {
    linkPage: LinkPage;
    variants: LinkPageVariant[];
    selectedVariantId: number;
    insightsUrl: string;
    items: LinkPageEditorItem[];
    previewItems: LinkPagePresentationItem[];
    canViewLeads: boolean;
    leadsUrl: string | null;
    privacyUrl: string | null;
};

type LinkPageMetrics = {
    views: number;
    clicks: number;
    leads: number;
};

export type LinkPageInsightDaily = LinkPageMetrics & {
    date: string;
};

export type LinkPageInsightVariant = LinkPageVariant &
    LinkPageMetrics & {
        isDeleted: boolean;
        daily: LinkPageInsightDaily[];
    };

export type LinkPageInsightsProps = {
    linkPage: Pick<LinkPage, 'id' | 'name' | 'slug'> & {
        publicUrl: string | null;
    };
    backUrl: string;
    backLabel?: string;
    editUrl?: string;
    windowBaseUrl: string;
    insights: {
        days: 7 | 30 | 90;
        startDate: string;
        endDate: string;
        totals: LinkPageMetrics;
        variants: LinkPageInsightVariant[];
    };
};

export type LinkPagePublicItem =
    | {
          type: 'link';
          id: number;
          title: string;
          url: string;
          position: number;
      }
    | {
          type: 'text';
          id: number;
          text: string;
          position: number;
      }
    | {
          type: 'image';
          id: number;
          altText: string;
          imageUrl: string | null;
          position: number;
      }
    | {
          type: 'file';
          id: number;
          fileName: string | null;
          fileUrl: string | null;
          position: number;
      }
    | {
          type: 'form';
          id: number;
          fields: LinkPageFormField[];
          submitUrl: string;
          position: number;
      };
export type LinkPageLeadColumn = {
    key: string;
    fieldId: string;
    formBlockId: number;
    label: string;
};

export type LinkPageLeadSubmission = {
    id: number;
    submittedAt: string;
    formBlockId: number;
    values: Record<string, string>;
};

export type LinkPageLeadsProps = {
    linkPage: Pick<LinkPage, 'id' | 'name' | 'slug'>;
    backUrl: string;
    exportUrl: string;
    columns: LinkPageLeadColumn[];
    leads: {
        data: LinkPageLeadSubmission[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export type LinkPagePublicProps = {
    title: string;
    description: string | null;
    slug: string;
    template: LinkPageTemplate;
    items: LinkPagePublicItem[];
    pixels: PublicTrackingPixelsByTrigger;
    trackingConsent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
    canGrantConsent: boolean;
};
