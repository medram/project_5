import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Power } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { useContentEditorRoutes } from '@/components/admin/content-editor-routes';
import { BioPageEditor } from '@/components/bio-pages/editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, publicMethod, update } from '@/routes/bio-pages';
import { type BioPageEditorProps, toBioPage } from '@/types/bio-pages';

export default function EditBioPage(props: BioPageEditorProps) {
    const { editorUrl, backUrl } = useContentEditorRoutes();
    const bioPage = toBioPage(props.bioPage);
    const publicUrl = publicMethod(bioPage.handle).url;
    const [updatingPublication, setUpdatingPublication] = useState(false);

    function togglePublished() {
        router.patch(
            editorUrl(update(bioPage.id).url),
            {
                handle: bioPage.handle,
                display_name: bioPage.displayName,
                bio: bioPage.bio ?? '',
                is_published: !bioPage.isPublished,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setUpdatingPublication(true),
                onFinish: () => setUpdatingPublication(false),
            },
        );
    }

    return (
        <>
            <Head title={`Edit ${bioPage.displayName}`} />

            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title={bioPage.displayName}
                    description="Manage the profile, button order, and publication state from one place."
                    actions={
                        <>
                            <Badge
                                variant={
                                    bioPage.isPublished ? 'default' : 'outline'
                                }
                            >
                                {bioPage.isPublished ? 'Published' : 'Draft'}
                            </Badge>
                            <Button
                                variant={
                                    bioPage.isPublished ? 'outline' : 'default'
                                }
                                className="min-h-11 sm:min-h-9"
                                disabled={updatingPublication}
                                aria-busy={updatingPublication}
                                onClick={togglePublished}
                            >
                                {updatingPublication ? (
                                    <Spinner
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                ) : (
                                    <Power
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                )}
                                {updatingPublication
                                    ? bioPage.isPublished
                                        ? 'Unpublishing…'
                                        : 'Publishing…'
                                    : bioPage.isPublished
                                      ? 'Unpublish'
                                      : 'Publish'}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={backUrl ?? index().url}>
                                    <ArrowLeft data-icon="inline-start" />
                                    All bio pages
                                </Link>
                            </Button>
                            {bioPage.isPublished && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={publicUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink data-icon="inline-start" />
                                        Open public page
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                <BioPageEditor {...props} />
            </section>
        </>
    );
}

EditBioPage.layout = {
    breadcrumbs: [
        {
            title: 'Bio pages',
            href: index(),
        },
        {
            title: 'Edit bio page',
            href: index(),
        },
    ],
};
