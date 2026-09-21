import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeSwitch from '@/components/theme-switch';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { UserInfo } from '@/components/user-info';
import { usePage } from '@inertiajs/react';

/** Simplified from AppSidebarHeader — no shop-wide search, this is the admin zone. */
export function AdminHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <header className="bg-background sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-2 px-3 sm:px-6 border-b border-sidebar-border/50">
            <div className="flex items-center gap-2 sm:gap-4 flex-1 min-w-0">
                <SidebarTrigger className="-ml-2 transition-transform hover:scale-105 shrink-0" />
                <div className="min-w-0 truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            <div className="flex items-center gap-2 sm:gap-4 shrink-0">
                <ThemeSwitch className="scale-90 sm:scale-100 origin-right" />

                <div className="h-6 w-px bg-slate-200 dark:bg-slate-700 mx-0.5 sm:mx-1 shrink-0"></div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button className="flex items-center gap-2 sm:gap-3 p-1 rounded-lg transition-all hover:bg-slate-50 dark:hover:bg-slate-800/50 outline-none focus-visible:ring-2 focus-visible:ring-gold text-left max-w-[140px] sm:max-w-[200px]">
                            <UserInfo user={auth.user} />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-56 rounded-xl shadow-lg border-border/50" align="end" sideOffset={8}>
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
