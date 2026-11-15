import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

const gradients = [
    'from-sky-400 via-blue-500 to-indigo-500',
    'from-teal-300 via-cyan-400 to-blue-500',
    'from-rose-300 via-pink-400 to-fuchsia-500',
    'from-amber-300 via-orange-400 to-rose-400',
    'from-emerald-300 via-teal-400 to-cyan-500',
    'from-violet-300 via-purple-400 to-indigo-500',
] as const;

function gradientFor(seed: string | number): string {
    const source = String(seed);
    let hash = 0;

    for (let index = 0; index < source.length; index += 1) {
        hash = (hash * 31 + source.charCodeAt(index)) % 997;
    }

    return gradients[hash % gradients.length];
}

export function UserAvatar({
    name,
    src,
    seed,
    className,
    imageClassName,
}: {
    name: string;
    src?: string | null;
    seed?: string | number;
    className?: string;
    imageClassName?: string;
}) {
    const getInitials = useInitials();
    const gradient = gradientFor(seed ?? name);

    return (
        <Avatar
            className={cn(
                'size-9 rounded-full ring-1 ring-black/5 dark:ring-white/10',
                className,
            )}
        >
            {src ? (
                <AvatarImage
                    src={src}
                    alt=""
                    className={cn('object-cover', imageClassName)}
                />
            ) : null}
            <AvatarFallback
                aria-hidden="true"
                className={cn(
                    'bg-gradient-to-br text-[11px] font-semibold text-white',
                    gradient,
                )}
            >
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
