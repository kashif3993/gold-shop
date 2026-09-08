import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="mt-2 px-4 py-4 group-data-[collapsible=icon]:px-2">
            <SidebarMenu className="gap-2">
                {items.map((item) => {
                    const isActive = item.url === page.url;
                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                tooltip={item.title}
                                className={cn(
                                    'relative h-[46px] w-full justify-start rounded-[10px] px-4 transition-all duration-200 ease-in-out',
                                    'group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0',
                                    isActive
                                        ? 'bg-[#b38a36] hover:bg-[#a17a2e]'
                                        : 'bg-transparent hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                )}
                            >
                                <Link href={item.url} prefetch className="flex items-center gap-[14px]">
                                    {item.icon && (
                                        <item.icon
                                            className={cn(
                                                'size-[20px] shrink-0 stroke-[2]',
                                                isActive ? 'text-black' : 'text-sidebar-foreground/60',
                                            )}
                                        />
                                    )}
                                    <span
                                        className={cn(
                                            'text-[15px] font-semibold tracking-wide group-data-[collapsible=icon]:hidden',
                                            isActive ? 'text-black' : 'text-sidebar-foreground/80',
                                        )}
                                    >
                                        {item.title}
                                    </span>

                                    {isActive && (
                                        <div className="absolute right-3 top-1/2 h-[18px] w-[5px] -translate-y-1/2 rounded-full bg-black group-data-[collapsible=icon]:hidden" />
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
