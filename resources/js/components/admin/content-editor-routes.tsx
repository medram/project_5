import { createContext, useContext, type ReactNode } from 'react';

type ContentEditorRoutes = {
    editorUrl: (ownerUrl: string) => string;
    insightsUrl: (ownerUrl: string) => string;
    backUrl: string | null;
    isAdmin: boolean;
};

const ownerRoutes: ContentEditorRoutes = {
    editorUrl: (url) => url,
    insightsUrl: (url) => url,
    backUrl: null,
    isAdmin: false,
};

const ContentEditorRoutesContext = createContext<ContentEditorRoutes>(ownerRoutes);

export function ContentEditorRoutesProvider({
    ownerPrefix,
    adminPrefix,
    backUrl,
    children,
}: {
    ownerPrefix: string;
    adminPrefix: string;
    backUrl: string;
    children: ReactNode;
}) {
    function mapUrl(ownerUrl: string): string {
        const suffix = ownerUrl.slice(ownerPrefix.length);
        if (
            !ownerUrl.startsWith(ownerPrefix) ||
            (suffix !== '' && !['/', '?', '#'].includes(suffix[0]))
        ) {
            throw new Error('The editor URL does not belong to the selected content record.');
        }

        return adminPrefix + suffix;
    }

    return (
        <ContentEditorRoutesContext.Provider
            value={{
                editorUrl: mapUrl,
                insightsUrl: mapUrl,
                backUrl,
                isAdmin: true,
            }}
        >
            {children}
        </ContentEditorRoutesContext.Provider>
    );
}

export function useContentEditorRoutes(): ContentEditorRoutes {
    return useContext(ContentEditorRoutesContext);
}
