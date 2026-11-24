import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CustomPageEditor } from '@/components/admin/custom-page-editor';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import pages from '@/routes/admin/pages';
import type { AdminPageFormProps } from '@/types/admin-pages';

export default function AdminPageForm({ page }: AdminPageFormProps) {
    const form = useForm({
        slug: page?.slug ?? '',
        title: page?.title ?? '',
        body: page?.body ?? '<p></p>',
        state: page?.state ?? 'draft',
        placement: page?.placement ?? 'none',
        seo_title: page?.seo_title ?? '',
        seo_description: page?.seo_description ?? '',
        og_media_id: page?.og_media_id ? String(page.og_media_id) : '',
    });
    const [assetQuery, setAssetQuery] = useState('');
    const [assets, setAssets] = useState<{ value: string; label: string }[]>([]);
    const [assetError, setAssetError] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            const params = new URLSearchParams({ q: assetQuery, selected: form.data.og_media_id });
            fetch(`/admin/custom-pages/assets/options?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) throw new Error('Unable to load assets');
                    return response.json();
                })
                .then((payload: { items: { value: string; label: string }[] }) => {
                    setAssets(payload.items);
                    setAssetError(false);
                })
                .catch(() => {
                    if (!controller.signal.aborted) setAssetError(true);
                });
        }, 150);
        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [assetQuery, form.data.og_media_id]);

    function submit() {
        form.transform((data) => ({
            ...data,
            placement: data.placement === 'none' ? null : data.placement,
            og_media_id: data.og_media_id ? Number(data.og_media_id) : null,
            seo_title: data.seo_title || null,
            seo_description: data.seo_description || null,
        }));

        if (page) {
            form.patch(pages.update(page.id).url, { preserveScroll: true });
        } else {
            form.post(pages.store().url);
        }
    }

    return (
        <>
            <Head
                title={`${page ? 'Edit' : 'New'} custom page — Administration`}
            />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="self-start px-0"
                >
                    <Link href={pages.index().url}>
                        <ArrowLeft aria-hidden="true" />
                        Custom pages
                    </Link>
                </Button>
                <PageHeader
                    title={page ? 'Edit custom page' : 'New custom page'}
                    description="Server sanitization is authoritative. Only safe paragraphs, headings, lists, emphasis, quotes, code, and http/https/mailto links survive."
                    actions={
                        page && (
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11"
                            >
                                <a
                                    href={pages.preview(page.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink aria-hidden="true" />
                                    Preview
                                </a>
                            </Button>
                        )
                    }
                />

                <form
                    className="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid min-w-0 gap-4">
                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Page content</CardTitle>
                                <CardDescription>
                                    The editor exposes a restricted toolbar; the
                                    server strips unsupported markup and unsafe
                                    link protocols on write and render.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="page-title">Title</Label>
                                    <Input
                                        id="page-title"
                                        value={form.data.title}
                                        maxLength={160}
                                        required
                                        onChange={(event) =>
                                            form.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    {form.errors.title && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.title}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Body</Label>
                                    <CustomPageEditor
                                        value={form.data.body}
                                        onChange={(html) =>
                                            form.setData('body', html)
                                        }
                                    />
                                    {form.errors.body && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.body}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid min-w-0 content-start gap-4">
                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Publication</CardTitle>
                                <CardDescription>
                                    Reserved application slugs are rejected.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="page-slug">Slug</Label>
                                    <Input
                                        id="page-slug"
                                        value={form.data.slug}
                                        maxLength={80}
                                        required
                                        placeholder="about-us"
                                        onChange={(event) =>
                                            form.setData(
                                                'slug',
                                                event.target.value.toLowerCase(),
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        /content/
                                        {form.data.slug || 'slug'}
                                    </p>
                                    {form.errors.slug && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.slug}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="page-state">State</Label>
                                    <Select
                                        value={form.data.state}
                                        onValueChange={(value) =>
                                            form.setData('state', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="page-state"
                                            className="min-h-11 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="draft">
                                                Draft
                                            </SelectItem>
                                            <SelectItem value="published">
                                                Published
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="page-placement">
                                        Optional placement
                                    </Label>
                                    <Select
                                        value={form.data.placement}
                                        onValueChange={(value) =>
                                            form.setData('placement', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="page-placement"
                                            className="min-h-11 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                None
                                            </SelectItem>
                                            <SelectItem value="header">
                                                Header
                                            </SelectItem>
                                            <SelectItem value="footer">
                                                Footer
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>SEO and OG media</CardTitle>
                                <CardDescription>
                                    Choose an eligible platform image for social
                                    previews, or use the platform default.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="page-seo-title">
                                        SEO title
                                    </Label>
                                    <Input
                                        id="page-seo-title"
                                        value={form.data.seo_title}
                                        maxLength={160}
                                        onChange={(event) =>
                                            form.setData(
                                                'seo_title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="page-seo-description">
                                        SEO description
                                    </Label>
                                    <Textarea
                                        id="page-seo-description"
                                        value={form.data.seo_description}
                                        maxLength={320}
                                        rows={3}
                                        onChange={(event) =>
                                            form.setData(
                                                'seo_description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="page-og-search">
                                        Search social image assets
                                    </Label>
                                    <Input
                                        id="page-og-search"
                                        value={assetQuery}
                                        onChange={(event) => setAssetQuery(event.target.value)}
                                        placeholder="Search by filename"
                                    />
                                    <Label htmlFor="page-og-media">Social image</Label>
                                    <Select
                                        value={form.data.og_media_id || 'none'}
                                        onValueChange={(value) =>
                                            form.setData('og_media_id', value === 'none' ? '' : value)
                                        }
                                    >
                                        <SelectTrigger id="page-og-media" aria-invalid={!!form.errors.og_media_id}>
                                            <SelectValue placeholder="Choose an image" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Use platform default</SelectItem>
                                            {assets.map((asset) => (
                                                <SelectItem key={asset.value} value={asset.value}>
                                                    {asset.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {assetError && (
                                        <p className="text-sm text-destructive">Unable to load images. Try searching again.</p>
                                    )}
                                    {form.errors.og_media_id && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.og_media_id}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            <Save aria-hidden="true" />
                            {form.processing ? 'Saving…' : 'Save page'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
