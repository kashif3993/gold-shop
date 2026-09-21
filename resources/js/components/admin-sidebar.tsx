import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { LayoutDashboard, Gem, Landmark, History, ShieldCheck, Users, ArrowLeftCircle } from 'lucide-react';
import AppLogo from './app-logo';

const adminNavItems: NavItem[] = [
    { title: 'Overview', url: '/admin', icon: LayoutDashboard },
    { title: 'Purities', url: '/admin/purities', icon: Gem },
    { title: 'Daily Rates', url: '/admin/daily-rates', icon: History },
    { title: 'Bank QR Payment', url: '/admin/payment', icon: Landmark },
    { title: 'Users', url: '/admin/users', icon: Users },
    { title: 'Audit Log', url: '/admin/audit-log', icon: ShieldCheck },
];

/**
 * Deliberately distinct from AppSidebar — same underlying components, but
 * scoped inside `.admin-zone` (see admin.css) which permanently overrides
 * the --sidebar-* tokens to a dark/gold scheme regardless of the site's own
 * light/dark toggle, so it reads as its own zone rather than another page.
 */
export function AdminSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="mx-4 mb-1 flex items-center gap-1.5 rounded-md border border-[#b5892f]/40 bg-[#b5892f]/10 px-2.5 py-1 group-data-[collapsible=icon]:hidden">
                    <ShieldCheck className="size-3.5 text-[#e4b248]" />
                    <span className="text-[10px] font-bold uppercase tracking-widest text-[#e4b248]">Admin Zone</span>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={adminNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild className="text-sidebar-foreground/70 hover:text-sidebar-foreground">
                            <Link href="/dashboard">
                                <ArrowLeftCircle className="size-4" />
                                <span>Back to Shop</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
