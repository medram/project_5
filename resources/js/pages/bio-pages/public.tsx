import { Head } from '@inertiajs/react';
import { BioPagePresentation } from '@/components/bio-pages/live-preview';
import type { BioPagePublicProps } from '@/types/bio-pages';

export default function PublicBioPage({
    displayName,
    bio,
    handle,
    links,
}: BioPagePublicProps) {
    return (
        <>
            <Head title={`${displayName} (@${handle})`} />

            <main className="bio-page-shell">
                <div className="bio-page-frame">
                    <BioPagePresentation
                        displayName={displayName}
                        bio={bio}
                        handle={handle}
                        links={links}
                    />
                </div>
            </main>
        </>
    );
}
