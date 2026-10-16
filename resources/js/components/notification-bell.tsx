import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { AlertCircle, AlertTriangle, Bell, Info } from 'lucide-react';
import { useEffect, useState } from 'react';

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
            axios
                .get('/api/v1/notifications')
                .then(({ data }) => {
                    if (alive) setNotifications(Array.isArray(data.notifications) ? data.notifications : []);
                })
                .catch(() => {
                    /* keep whatever we have */
                });

        load();
        const id = window.setInterval(load, 60_000);
        return () => {
            alive = false;
            window.clearInterval(id);
        };
    }, []);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="hover:text-gold focus-visible:ring-gold relative shrink-0 p-1.5 text-slate-500 transition-colors outline-none focus-visible:ring-2 dark:text-slate-400">
                    <Bell className="h-[20px] w-[20px] stroke-[1.5] sm:h-[22px] sm:w-[22px]" />
                    {notifications.length > 0 && (
                        <span className="absolute top-1 right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] leading-none font-bold text-white">
                            {notifications.length > 9 ? '9+' : notifications.length}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="border-border/50 w-80 rounded-xl shadow-lg" align="end" sideOffset={8}>
                <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {notifications.length === 0 ? (
                    <div className="text-muted-foreground px-2 py-4 text-center text-sm">Nothing needs your attention right now.</div>
                ) : (
                    <div className="max-h-80 overflow-y-auto">
                        {notifications.map((n) => {
                            const Icon = severityIcon[n.severity];
                            const body = (
                                <div className="flex items-start gap-2.5 px-2 py-2.5">
                                    <Icon className={`mt-0.5 h-4 w-4 shrink-0 ${severityColor[n.severity]}`} />
                                    <div className="min-w-0">
                                        <div className="text-foreground text-sm font-semibold">{n.title}</div>
                                        <div className="text-muted-foreground mt-0.5 text-xs">{n.message}</div>
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
