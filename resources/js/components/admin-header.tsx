import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeSwitch from '@/components/theme-switch';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/** Simplified from AppSidebarHeader — no shop-wide search, this is the admin zone. */
export function AdminHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <header className="bg-background border-sidebar-border/50 sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-3 sm:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">
                <SidebarTrigger className="-ml-2 shrink-0 transition-transform hover:scale-105" />
                <div className="min-w-0 truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-2 sm:gap-4">
                <ThemeSwitch className="origin-right scale-90 sm:scale-100" />

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
