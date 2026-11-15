import { UserAvatar } from '@/components/user-avatar';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    avatarClassName = 'h-8 w-8',
}: {
    user: User;
    showEmail?: boolean;
    avatarClassName?: string;
}) {
    return (
        <>
            <UserAvatar
                name={user.name}
                src={user.avatar}
                seed={user.id ?? user.email}
                className={avatarClassName}
            />
            <div className="grid min-w-0 flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
