import { Link, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

type SearchResult = {
    id: number;
    type:
        | 'link'
        | 'qr_code'
        | 'folder'
        | 'bio_page'
        | 'link_page'
        | 'custom_domain'
        | 'overlay'
        | 'tracking_pixel'
        | 'webhook'
        | 'account'
        | 'plan';
    title: string;
    description: string;
    href: string;
};

const typeLabels: Record<SearchResult['type'], string> = {
    link: 'Short link',
    qr_code: 'QR code',
    folder: 'Folder',
    bio_page: 'Bio page',
    link_page: 'Link page',
    custom_domain: 'Custom domain',
    overlay: 'CTA overlay',
    tracking_pixel: 'Tracking pixel',
    webhook: 'Webhook',
    account: 'Account',
    plan: 'Plan',
};

export function GlobalSearch() {
    const { auth } = usePage().props;
    const canSearchPlatform = auth.canSearchPlatform;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const resultRefs = useRef<Array<HTMLAnchorElement | null>>([]);
    const normalizedQuery = query.trim();

    useEffect(() => {
        function openSearch(event: KeyboardEvent) {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen(true);
            }
        }

        window.addEventListener('keydown', openSearch);
        return () => window.removeEventListener('keydown', openSearch);
    }, []);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        } else {
            setQuery('');
        }
    }, [open]);

    useEffect(() => {
        if (!open || normalizedQuery.length < 2) {
            setResults([]);
            setLoading(false);
            setFailed(false);
            return;
        }

        const controller = new AbortController();
        setResults([]);
        setFailed(false);
        const timeout = window.setTimeout(async () => {
            setLoading(true);

            try {
                const response = await fetch(
                    `/search?q=${encodeURIComponent(normalizedQuery)}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                if (!response.ok) throw new Error('Search request failed.');

                const payload = (await response.json()) as {
                    results: SearchResult[];
                };
                if (!controller.signal.aborted) setResults(payload.results);
            } catch {
                if (!controller.signal.aborted) setFailed(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 200);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [normalizedQuery, open]);
    const handleResultNavigation = (
        event: React.KeyboardEvent<HTMLElement>,
        index: number,
    ) => {
        if (
            !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) ||
            results.length === 0
        ) {
            return;
        }

        event.preventDefault();
        if (event.key === 'ArrowUp' && index === 0) {
            inputRef.current?.focus();
            return;
        }

        const nextIndex =
            event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? results.length - 1
                  : (index +
                        (event.key === 'ArrowDown' ? 1 : -1) +
                        results.length) %
                    results.length;
        resultRefs.current[nextIndex]?.focus();
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-11 min-w-11 sm:min-w-0"
                    aria-label={
                        canSearchPlatform
                            ? 'Search platform accounts and plans (Ctrl+K or Command+K)'
                            : 'Search this workspace (Ctrl+K or Command+K)'
                    }
                >
                    <Search data-icon="inline-start" aria-hidden="true" />
                    <span className="hidden sm:inline">Search</span>
                    <kbd className="ml-2 hidden rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground sm:inline">
                        Ctrl/⌘ K
                    </kbd>
                </Button>
            </DialogTrigger>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl">
                <DialogHeader className="sr-only">
                    <DialogTitle>
                        {canSearchPlatform
                            ? 'Search platform'
                            : 'Search this workspace'}
                    </DialogTitle>
                    <DialogDescription>
                        {canSearchPlatform
                            ? 'Search account metadata and plans only. Workspace content, destinations, visitor details, and integration secrets are excluded. Use arrow keys to navigate results.'
                            : 'Search short links, folders, bio pages, link pages, QR codes, domains, CTA overlays, tracking pixels, and named webhooks in your active workspace. Use arrow keys to navigate results.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="border-b p-4">
                    <Input
                        ref={inputRef}
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (
                                event.key === 'ArrowDown' &&
                                results.length > 0
                            ) {
                                event.preventDefault();
                                resultRefs.current[0]?.focus();
                            }
                        }}
                        maxLength={100}
                        placeholder={
                            canSearchPlatform
                                ? 'Search accounts or plans'
                                : 'Search workspace resources'
                        }
                        aria-label={
                            canSearchPlatform
                                ? 'Search accounts or plans'
                                : 'Search workspace resources'
                        }
                    />
                </div>
                <div
                    className="max-h-[60dvh] overflow-y-auto p-2"
                    aria-live="polite"
                    aria-busy={loading}
                >
                    {normalizedQuery.length < 2 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            Type at least two characters to search.
                        </p>
                    ) : loading ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            Searching…
                        </p>
                    ) : failed ? (
                        <p
                            className="p-4 text-sm text-destructive"
                            role="alert"
                        >
                            Search is temporarily unavailable. Try again.
                        </p>
                    ) : results.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            {canSearchPlatform
                                ? 'No matching accounts or plans found.'
                                : 'No matching resources found.'}
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-1">
                            {results.map((result, index) => (
                                <li key={`${result.type}:${result.id}`}>
                                    <Link
                                        ref={(element) => {
                                            resultRefs.current[index] =
                                                element instanceof
                                                HTMLAnchorElement
                                                    ? element
                                                    : null;
                                        }}
                                        href={result.href}
                                        onClick={() => setOpen(false)}
                                        onKeyDown={(event) =>
                                            handleResultNavigation(event, index)
                                        }
                                        className="flex min-h-12 items-center gap-3 rounded-md px-3 py-2 text-left transition-colors outline-none hover:bg-muted focus-visible:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium">
                                                {result.title}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {result.description}
                                            </span>
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="shrink-0"
                                        >
                                            {typeLabels[result.type]}
                                        </Badge>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
