import { Breadcrumbs } from '@/components/breadcrumbs';
import NotificationBell from '@/components/notification-bell';
import ThemeSwitch from '@/components/theme-switch';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <header className="bg-background border-sidebar-border/50 sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">
                <SidebarTrigger className="-ml-2 shrink-0 transition-transform hover:scale-105" />
                <div className="min-w-0 truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>

                <div className="group relative ml-4 hidden max-w-[320px] flex-1 lg:block">
                    <Search className="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                    <Input
                        type="search"
                        placeholder="Search items, parties, invoices..."
                        className="focus-visible:ring-gold focus-visible:border-gold/50 w-full rounded-lg border-slate-200 bg-white pl-10 text-sm shadow-sm transition-all focus-visible:ring-1 dark:border-slate-800 dark:bg-neutral-900"
                    />
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-2 sm:gap-4">
                <ThemeSwitch className="origin-right scale-90 sm:scale-100" />

                <NotificationBell />

                <div className="mx-0.5 h-6 w-px shrink-0 bg-slate-200 sm:mx-1 dark:bg-slate-700"></div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button className="focus-visible:ring-gold flex max-w-[140px] items-center gap-2 rounded-lg p-1 text-left transition-all outline-none hover:bg-slate-50 focus-visible:ring-2 sm:max-w-[200px] sm:gap-3 dark:hover:bg-slate-800/50">
                            <UserInfo user={auth.user} />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="border-border/50 w-56 rounded-xl shadow-lg" align="end" sideOffset={8}>
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
