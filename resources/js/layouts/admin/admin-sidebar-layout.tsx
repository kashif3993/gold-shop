import { AdminHeader } from '@/components/admin-header';
import { AdminSidebar } from '@/components/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { type BreadcrumbItem } from '@/types';

export default function AdminSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <div className="admin-zone">
            <AppShell variant="sidebar">
                <AdminSidebar />
                <AppContent variant="sidebar">
                    <AdminHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
