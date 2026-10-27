import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, PanelsTopLeft } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
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

            <section className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={bioPage.displayName}
                    eyebrow="Publishing desk"
                    icon={PanelsTopLeft}
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
