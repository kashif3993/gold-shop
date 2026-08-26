import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeSwitch from '@/components/theme-switch';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Input } from '@/components/ui/input';
import { Search, Bell } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { UserInfo } from '@/components/user-info';
import { usePage } from '@inertiajs/react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <header className="bg-background flex h-16 shrink-0 items-center justify-between gap-2 px-3 sm:px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <div className="flex items-center gap-2 sm:gap-4 flex-1 min-w-0">
                <SidebarTrigger className="-ml-2 transition-transform hover:scale-105 shrink-0" />
                <div className="min-w-0 truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                
                <div className="relative ml-4 hidden max-w-[320px] flex-1 lg:block group">
                    <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-slate-500" />
                    <Input
                        type="search"
                        placeholder="Search items, parties, invoices..."
                        className="w-full rounded-lg bg-white dark:bg-black pl-10 border-slate-200 dark:border-slate-800 text-sm shadow-sm transition-all focus-visible:ring-1 focus-visible:ring-gold focus-visible:border-gold/50"
                    />
                </div>
            </div>
            
            <div className="flex items-center gap-2 sm:gap-4 shrink-0">
                <ThemeSwitch className="scale-90 sm:scale-100 origin-right" />
                
                <button className="relative p-1.5 transition-colors hover:text-gold outline-none focus-visible:ring-2 focus-visible:ring-gold text-slate-500 dark:text-slate-400 shrink-0">
                    <Bell className="h-[20px] w-[20px] sm:h-[22px] sm:w-[22px] stroke-[1.5]" />
                    <span className="absolute right-1.5 top-1.5 flex h-2 w-2 rounded-full bg-red-500"></span>
                </button>
                
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

