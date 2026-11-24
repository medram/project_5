import { usePage } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

import { useAppearance } from '@/hooks/use-appearance';

export default function AppLogo({
    hideNameWhenCollapsed = false,
}: {
    hideNameWhenCollapsed?: boolean;
} = {}) {
    const { name, platform } = usePage().props;
    const { resolvedAppearance } = useAppearance();
    const [loadedLogo, setLoadedLogo] = useState<string | null>(null);
    const logoRef = useRef<HTMLImageElement>(null);
    const logo =
        resolvedAppearance === 'dark'
            ? (platform?.logos.dark ??
              platform?.logos.light ??
              platform?.logos.compact ??
              null)
            : (platform?.logos.light ??
              platform?.logos.dark ??
              platform?.logos.compact ??
              null);
    const showLogo = logo !== null && logo === loadedLogo;

    useEffect(() => {
        const image = logoRef.current;
        if (
            image?.complete &&
            image.naturalWidth > 1 &&
            image.naturalHeight > 1
        ) {
            setLoadedLogo(logo);
        }
    }, [logo]);

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {!showLogo && (
                    <Link2
                        className="size-5"
                        strokeWidth={2.25}
                        aria-hidden="true"
                    />
                )}
                {logo && (
                    <img
                        ref={logoRef}
                        src={logo}
                        alt=""
                        className={cn(
                            'size-full object-contain',
                            !showLogo && 'hidden',
                        )}
                        onLoad={(event) => {
                            const { naturalWidth, naturalHeight } =
                                event.currentTarget;
                            setLoadedLogo(
                                naturalWidth > 1 && naturalHeight > 1
                                    ? logo
                                    : null,
                            );
                        }}
                        onError={() => setLoadedLogo(null)}
                    />
                )}
            </div>
            <div
                className={cn(
                    'ml-1 grid flex-1 text-left text-sm',
                    hideNameWhenCollapsed &&
                        'group-data-[collapsible=icon]:sr-only',
                )}
            >
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
