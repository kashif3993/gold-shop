import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';

export function UserInfo({ user, showEmail = false }: { user: User; showEmail?: boolean }) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="border-gold h-[34px] w-[34px] overflow-hidden rounded-full border bg-transparent">
                <AvatarImage src={user.avatar} alt={user.username} />
                <AvatarFallback className="text-gold bg-transparent text-[13px] font-semibold">{getInitials(user.username)}</AvatarFallback>
            </Avatar>
            <div className="ml-1 grid flex-1 text-left text-[14px] leading-tight">
                <span className="truncate font-bold text-slate-900 dark:text-slate-100">{user.username}</span>
                {showEmail && user.email && <span className="text-muted-foreground mt-0.5 truncate text-xs font-normal">{user.email}</span>}
            </div>
        </>
    );
}
