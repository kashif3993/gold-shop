import AdminLayoutTemplate from '@/layouts/admin/admin-sidebar-layout';
import { type BreadcrumbItem } from '@/types';

interface AdminLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: AdminLayoutProps) => (
    <AdminLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        {children}
    </AdminLayoutTemplate>
);
