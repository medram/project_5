import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, PenLine } from 'lucide-react';

type PublicCustomPage = {
    slug: string;
    title: string;
    body: string;
    state: string;
    seoTitle: string | null;
    seoDescription: string | null;
    publishedAt: string | null;
};

export default function ContentShow({
    page,
    preview,
}: {
    page: PublicCustomPage;
    preview: boolean;
}) {
    const title = page.seoTitle || page.title;

    return (
        <>
            <Head title={title} />
            <main className="min-h-screen bg-background text-foreground">
                {preview && (
                    <div className="border-b border-warning/30 bg-warning-soft px-4 py-3 text-center text-sm text-warning-soft-foreground">
                        <PenLine
                            className="mr-2 inline size-4"
                            aria-hidden="true"
                        />
                        Admin preview — this {page.state} page is not public.
                    </div>
                )}
                <div className="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:py-16">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-2 text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        LinkPilot
                    </Link>
                    <article className="mt-8">
                        <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            {page.title}
                        </h1>
                        {page.publishedAt && !preview && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                Published{' '}
                                {new Intl.DateTimeFormat(undefined, {
                                    dateStyle: 'medium',
                                }).format(new Date(page.publishedAt))}
                            </p>
                        )}
                        <div
                            className="mt-8 space-y-4 text-base leading-7 [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_blockquote]:text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h4]:mt-4 [&_h4]:text-lg [&_h4]:font-semibold [&_li]:ml-6 [&_ol]:list-decimal [&_pre]:overflow-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-4 [&_ul]:list-disc"
                            dangerouslySetInnerHTML={{ __html: page.body }}
                        />
                    </article>
                </div>
            </main>
        </>
    );
}
