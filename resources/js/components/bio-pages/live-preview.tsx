import { ArrowUpRight, Eye } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { BioPagePublicLink } from '@/types/bio-pages';

type BioPagePresentationProps = {
    displayName: string;
    bio: string | null;
    handle: string;
    links: BioPagePublicLink[];
    preview?: boolean;
};

type LivePreviewProps = BioPagePresentationProps & {
    isPublished: boolean;
};

export function BioPagePresentation({
    displayName,
    bio,
    handle,
    links,
    preview = false,
}: BioPagePresentationProps) {
    const name = displayName.trim() || 'Untitled profile';
    const publicHandle = handle.trim() || 'your-handle';

    return (
        <section
            className="bio-page-presentation"
            aria-label={`${name}'s links`}
        >
            <header className="bio-page-identity">
                <p className="bio-page-handle">@{publicHandle}</p>
                <h1 className="bio-page-name">{name}</h1>
                {bio?.trim() && <p className="bio-page-bio">{bio}</p>}
            </header>

            <nav className="bio-page-links" aria-label={`${name}'s links`}>
                {links.length > 0 ? (
                    <ul className="flex min-w-0 flex-col gap-3">
                        {links.map((link) => (
                            <li key={link.id} className="min-w-0">
                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="h-auto min-h-11 w-full min-w-0 justify-between py-2 text-left whitespace-normal"
                                >
                                    <a
                                        href={link.destinationUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <span className="min-w-0 break-words">
                                            {link.title}
                                        </span>
                                        <ArrowUpRight
                                            data-icon="inline-end"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="rounded-md border border-dashed p-4 text-sm leading-6 text-muted-foreground">
                        {preview
                            ? 'Active buttons will appear here.'
                            : 'No active links are available.'}
                    </p>
                )}
            </nav>
        </section>
    );
}

export function LivePreview({ isPublished, ...props }: LivePreviewProps) {
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
                        ? 'This preview matches the active buttons visitors can open now.'
                        : 'This is a draft. Publish the page before visitors can open it.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <BioPagePresentation {...props} preview />
            </CardContent>
            <CardFooter>
                <p className="text-sm leading-6 text-muted-foreground">
                    Disabled buttons stay hidden from the public page.
                </p>
            </CardFooter>
        </Card>
    );
}
