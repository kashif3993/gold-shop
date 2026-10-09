import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { Bell, AlertTriangle, Info, AlertCircle } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';

interface Notification {
    id: string;
    title: string;
    message: string;
    severity: 'warning' | 'info' | 'danger';
    href: string | null;
}

const severityIcon = { warning: AlertTriangle, info: Info, danger: AlertCircle } as const;
const severityColor = {
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-600 dark:text-sky-400',
    danger: 'text-red-600 dark:text-red-400',
} as const;

/**
 * Live, read-only — nothing here is ever marked read or dismissed, each
 * notification simply stops appearing once its underlying condition does
 * (the payment gets confirmed, stock comes back, the rate refreshes). See
 * NotificationService for what's actually checked.
 */
export default function NotificationBell() {
    const [notifications, setNotifications] = useState<Notification[]>([]);

    useEffect(() => {
        let alive = true;

        const load = () =>
            axios.get('/api/v1/notifications')
                .then(({ data }) => { if (alive) setNotifications(Array.isArray(data.notifications) ? data.notifications : []); })
                .catch(() => { /* keep whatever we have */ });

        load();
        const id = window.setInterval(load, 60_000);
        return () => { alive = false; window.clearInterval(id); };
    }, []);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="relative p-1.5 transition-colors hover:text-gold outline-none focus-visible:ring-2 focus-visible:ring-gold text-slate-500 dark:text-slate-400 shrink-0">
                    <Bell className="h-[20px] w-[20px] sm:h-[22px] sm:w-[22px] stroke-[1.5]" />
                    {notifications.length > 0 && (
                        <span className="absolute right-1 top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">
                            {notifications.length > 9 ? '9+' : notifications.length}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-80 rounded-xl shadow-lg border-border/50" align="end" sideOffset={8}>
                <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {notifications.length === 0 ? (
                    <div className="px-2 py-4 text-center text-sm text-muted-foreground">Nothing needs your attention right now.</div>
                ) : (
                    <div className="max-h-80 overflow-y-auto">
                        {notifications.map(n => {
                            const Icon = severityIcon[n.severity];
                            const body = (
                                <div className="flex items-start gap-2.5 px-2 py-2.5">
                                    <Icon className={`h-4 w-4 mt-0.5 shrink-0 ${severityColor[n.severity]}`} />
                                    <div className="min-w-0">
                                        <div className="text-sm font-semibold text-foreground">{n.title}</div>
                                        <div className="text-xs text-muted-foreground mt-0.5">{n.message}</div>
                                    </div>
                                </div>
                            );
                            return n.href ? (
                                <Link key={n.id} href={n.href} className="block rounded-md hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    {body}
                                </Link>
                            ) : (
                                <div key={n.id}>{body}</div>
                            );
                        })}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
