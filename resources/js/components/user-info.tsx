import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';

export function UserInfo({ user, showEmail = false }: { user: User; showEmail?: boolean }) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-[34px] w-[34px] overflow-hidden rounded-full border border-gold bg-transparent">
                <AvatarImage src={user.avatar} alt={user.username} />
                <AvatarFallback className="bg-transparent text-[13px] font-semibold text-gold">
                    {getInitials(user.username)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-[14px] leading-tight ml-1">
                <span className="truncate font-bold text-slate-900 dark:text-slate-100">{user.username}</span>
                {showEmail && user.email && <span className="text-muted-foreground truncate text-xs font-normal mt-0.5">{user.email}</span>}
            </div>
        </>
    );
}
