import { usePublicTrackingEvent } from '@/components/analytics/public-tracking-pixels';
import { LeadForm } from '@/components/link-pages/lead-form';
import { ExternalLink } from 'lucide-react';
import type {
    LinkPagePresentationItem,
    LinkPageTemplate,
} from '@/types/link-pages';

type Props = {
    template: LinkPageTemplate;
    title: string;
    description: string | null;
    items: LinkPagePresentationItem[];
    preview?: boolean;
    privacyUrl?: string | null;
};

const styles: Record<
    LinkPageTemplate,
    {
        header: string;
        title: string;
        description: string;
        link: string;
        text: string;
    }
> = {
    standard: {
        header: 'items-start text-left',
        title: 'text-3xl',
        description: 'text-base leading-7 text-muted-foreground',
        link: 'justify-between rounded-xl border bg-background px-4 py-3 text-base font-medium shadow-sm transition-colors hover:border-primary hover:bg-accent',
        text: 'text-left',
    },
    profile: {
        header: 'items-center text-center',
        title: 'text-3xl',
        description:
            'max-w-prose text-center text-base leading-7 text-muted-foreground',
        link: 'justify-center rounded-full border bg-background px-5 py-3 text-center text-base font-medium transition-colors hover:border-primary hover:bg-accent',
        text: 'text-center',
    },
    campaign: {
        header: 'items-start text-left',
        title: 'text-4xl',
        description: 'text-base leading-7 text-muted-foreground',
        link: 'justify-between rounded-lg border border-primary bg-primary px-5 py-4 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90',
        text: 'text-left',
    },
};

export function LinkPagePresentation({
    template,
    title,
    description,
    items,
    preview = false,
    privacyUrl,
}: Props) {
    const trackEvent = usePublicTrackingEvent();
    const style = styles[template];

    return (
        <div className="flex min-w-0 flex-col gap-6" data-template={template}>
            <header className={`flex min-w-0 flex-col gap-2 ${style.header}`}>
                <h1
                    className={`max-w-full font-semibold tracking-tight break-words ${style.title}`}
                >
                    {title}
                </h1>
                {description && (
                    <p
                        className={`max-w-full break-words ${style.description}`}
                    >
                        {description}
                    </p>
                )}
            </header>

            <section className="flex min-w-0 flex-col gap-4">
                {items.map((item) => {
                    if (item.type === 'link') {
                        const content = (
                            <>
                                <span className="min-w-0 break-words">
                                    {item.title}
                                </span>
                                <ExternalLink
                                    className="size-4 shrink-0 opacity-70"
                                    aria-hidden="true"
                                />
                            </>
                        );
                        const className = `flex min-w-0 items-center gap-3 ${style.link}`;

                        return preview || !item.url ? (
                            <div key={`link-${item.id}`} className={className}>
                                {content}
                            </div>
                        ) : (
                            <a
                                key={`link-${item.id}`}
                                href={item.url}
                                onClick={() => trackEvent('button_click')}
                                className={`group ${className}`}
                            >
                                {content}
                            </a>
                        );
                    }

                    if (item.type === 'text') {
                        return (
                            <p
                                key={`text-${item.id}`}
                                className={`leading-7 break-words whitespace-pre-wrap ${style.text}`}
                            >
                                {item.text}
                            </p>
                        );
                    }
                    if (item.type === 'form') {
                        return (
                            <LeadForm
                                key={`form-${item.id}`}
                                fields={item.fields}
                                submitUrl={preview ? undefined : item.submitUrl}
                                privacyUrl={privacyUrl ?? null}
                                preview={preview}
                            />
                        );
                    }

                    if (item.type === 'file') {
                        const label = item.fileName ?? 'PDF file';
                        const className = `flex min-w-0 items-center gap-3 ${style.link}`;

                        return preview || !item.fileUrl ? (
                            <div key={`file-${item.id}`} className={className}>
                                <span className="min-w-0 truncate">
                                    {label}
                                </span>
                                {preview && (
                                    <ExternalLink
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                        ) : (
                            <a
                                key={`file-${item.id}`}
                                href={item.fileUrl}
                                className={`group ${className}`}
                                onClick={() => trackEvent('button_click')}
                                aria-label={`Download ${label}`}
                            >
                                <span className="min-w-0 truncate">
                                    {label}
                                </span>
                                <ExternalLink
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            </a>
                        );
                    }

                    return item.imageUrl ? (
                        <img
                            key={`image-${item.id}`}
                            src={item.imageUrl}
                            alt={item.altText}
                            className="h-auto max-h-96 w-full rounded-xl object-cover"
                        />
                    ) : preview ? (
                        <p
                            key={`image-${item.id}`}
                            className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                        >
                            Image preview unavailable
                        </p>
                    ) : null;
                })}
                {preview && items.length === 0 && (
                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        Add a destination or content block to preview this page.
                    </p>
                )}
            </section>
        </div>
    );
}
