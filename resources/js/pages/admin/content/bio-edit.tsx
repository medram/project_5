import { ContentEditorRoutesProvider } from '@/components/admin/content-editor-routes';
import EditBioPage from '@/pages/bio-pages/edit';
import type { BioPageEditorProps } from '@/types/bio-pages';

export default function AdminBioEdit(props: BioPageEditorProps & { backUrl: string; editorUrl: string }) {
    return (
        <ContentEditorRoutesProvider
            ownerPrefix={`/dashboard/biolinks/${props.bioPage.id}`}
            adminPrefix={`/admin/biolinks/${props.bioPage.id}`}
            backUrl={props.backUrl}
        >
            <EditBioPage {...props} />
        </ContentEditorRoutesProvider>
    );
}
