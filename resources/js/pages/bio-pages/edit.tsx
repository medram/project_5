import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, PanelsTopLeft } from 'lucide-react';
import { BioPageEditor } from '@/components/bio-pages/editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, publicMethod } from '@/routes/bio-pages';
import { type BioPageEditorProps, toBioPage } from '@/types/bio-pages';

export default function EditBioPage(props: BioPageEditorProps) {
    const bioPage = toBioPage(props.bioPage);
    const publicUrl = publicMethod(bioPage.handle).url;

    return (
        <>
            <Head title={`Edit ${bioPage.displayName}`} />

            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex max-w-2xl flex-col gap-2">
                        <div className="flex items-center gap-2 text-linkpilot-creator">
                            <PanelsTopLeft aria-hidden="true" />
                            <span className="text-sm font-medium">
                                Publishing desk
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                {bioPage.displayName}
                            </h1>
                            <Badge
                                variant={
                                    bioPage.isPublished ? 'default' : 'outline'
                                }
                            >
                                {bioPage.isPublished ? 'Published' : 'Draft'}
                            </Badge>
                        </div>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Manage the profile, button order, and publication
                            state from one place.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={index()}>
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
                    </div>
                </section>

                <BioPageEditor {...props} />
            </main>
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
