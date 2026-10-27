import { ArrowUpRight, Eye, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { usePublicTrackingEvent } from '@/components/analytics/public-tracking-pixels';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    BioPageAppearance,
    BioPageItem,
    BioPagePresentationModel,
} from '@/types/bio-pages';

type LivePreviewProps = {
    presentation: BioPagePresentationModel;
    isPublished: boolean;
    previewAt: string;
};
function isCurrent(item: BioPageItem, now: number) {
    const start = item.startsAt ? new Date(item.startsAt).valueOf() : -Infinity;
    const end = item.endsAt ? new Date(item.endsAt).valueOf() : Infinity;
    return item.isActive && start <= now && now < end;
}
function appearanceClass(appearance: BioPageAppearance) {
    return `bio-theme-${appearance.theme} bio-type-${appearance.typography} bio-color-${appearance.color} bio-background-${appearance.background}`;
}

export function BioPagePresentation({
    presentation,
    previewAt,
}: {
    presentation: BioPagePresentationModel;
    previewAt?: string;
}) {
    const trackEvent = usePublicTrackingEvent();
    const { displayName, handle, bio, appearance } = presentation;
    const items = previewAt
        ? presentation.items.filter((item) =>
              isCurrent(item, Date.parse(previewAt)),
          )
        : presentation.items;
    const [loaded, setLoaded] = useState<number[]>([]);
    const name = displayName.trim() || 'Untitled profile';
    return (
        <section
            className={`bio-page-presentation ${appearanceClass(appearance)}`}
            aria-label={`${name}'s page`}
        >
            <header className="bio-page-identity">
                <p className="bio-page-handle">
                    @{handle.trim() || 'your-handle'}
                </p>
                <h1 className="bio-page-name">{name}</h1>
                {bio?.trim() && <p className="bio-page-bio">{bio}</p>}
            </header>
            <div className="bio-page-links" aria-label={`${name}'s content`}>
                {items.length === 0 ? (
                    <p className="rounded-md border border-dashed p-4 text-sm">
                        No active content is available right now.
                    </p>
                ) : (
                    <ul className="flex min-w-0 flex-col gap-3">
                        {items.map((item) => (
                            <li
                                key={`${item.type}-${item.id}`}
                                className="min-w-0"
                            >
                                {item.type === 'link' && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="lg"
                                        className="h-auto min-h-11 w-full justify-between whitespace-normal"
                                    >
                                        <a
                                            href={item.url}
                                            target="_blank"
                                            onClick={() =>
                                                trackEvent('button_click')
                                            }
                                            rel="noreferrer"
                                        >
                                            <span className="break-words">
                                                {item.title}
                                            </span>
                                            <ArrowUpRight aria-hidden="true" />
                                        </a>
                                    </Button>
                                )}
                                {item.type === 'text' && (
                                    <p className="rounded-lg border bg-background/80 p-4 leading-7 break-words whitespace-pre-wrap">
                                        {item.text}
                                    </p>
                                )}
                                {item.type === 'image' && item.imageUrl && (
                                    <img
                                        className="h-auto max-h-[32rem] w-full rounded-lg object-contain"
                                        src={item.imageUrl}
                                        alt={item.altText}
                                        loading="lazy"
                                    />
                                )}
                                {item.type === 'social' && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="lg"
                                        className="h-auto min-h-11 w-full justify-between whitespace-normal"
                                    >
                                        <a
                                            href={item.url}
                                            target="_blank"
                                            onClick={() =>
                                                trackEvent('button_click')
                                            }
                                            rel="noopener noreferrer"
                                        >
                                            <span>
                                                {item.provider} ·{' '}
                                                {item.identifier}
                                            </span>
                                            <ExternalLink aria-hidden="true" />
                                        </a>
                                    </Button>
                                )}
                                {item.type === 'embed' && (
                                    <div className="overflow-hidden rounded-lg border bg-background p-4">
                                        {loaded.includes(item.id) ? (
                                            <iframe
                                                title={`${item.provider} content`}
                                                src={item.embedUrl}
                                                loading="lazy"
                                                referrerPolicy="no-referrer"
                                                sandbox="allow-scripts allow-same-origin allow-presentation"
                                                allow="fullscreen; encrypted-media; picture-in-picture"
                                                className="aspect-video w-full border-0"
                                            />
                                        ) : (
                                            <div className="grid min-h-24 place-items-center gap-2 text-center">
                                                <p>
                                                    Embedded {item.provider}{' '}
                                                    content stays unloaded until
                                                    you choose.
                                                </p>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setLoaded((ids) => [
                                                            ...ids,
                                                            item.id,
                                                        ])
                                                    }
                                                >
                                                    Load {item.provider} content
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

export function LivePreview({
    isPublished,
    presentation,
    previewAt,
}: LivePreviewProps) {
    return (
        <Card className="gap-4 py-4">
            <CardHeader>
                <div className="flex items-center gap-2 text-linkpilot-creator">
                    <Eye aria-hidden="true" />
                    <span className="text-sm font-medium">Live preview</span>
                </div>
                <CardTitle>Visitor-facing page</CardTitle>
                <CardDescription>
                    {isPublished
                        ? 'A current-time preview of active, scheduled content.'
                        : 'This is a draft. Publish the page before visitors can open it.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <BioPagePresentation
                    presentation={presentation}
                    previewAt={previewAt}
                />
            </CardContent>
            <CardFooter>
                <p className="text-sm leading-6 text-muted-foreground">
                    Scheduled items appear in the preview when their time window
                    is active.
                </p>
            </CardFooter>
        </Card>
    );
}
