import { ContentEditorRoutesProvider } from '@/components/admin/content-editor-routes';
import LinkPageEditor from '@/pages/link-pages/edit';
import type { LinkPageEditorProps } from '@/types/link-pages';

export default function AdminLinkPageEdit(
    props: LinkPageEditorProps & { backUrl: string },
) {
    return (
        <ContentEditorRoutesProvider
            ownerPrefix={`/dashboard/link-pages/${props.linkPage.id}`}
            adminPrefix={`/admin/link-pages/${props.linkPage.id}`}
            backUrl={props.backUrl}
        >
            <LinkPageEditor {...props} />
        </ContentEditorRoutesProvider>
    );
}
