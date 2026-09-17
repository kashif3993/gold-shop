import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import '../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

export default function AdminIndex() {
    const { flash } = usePage().props as any;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Admin</h1>
                    <p className="adm-desc">System configuration and oversight — restricted to admin accounts.</p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <div className="adm-card-grid">
                    <Link href="/admin/purities" className="adm-link-card">
                        <h2 className="adm-link-card-title">Purities</h2>
                        <p className="adm-link-card-sub">Add, edit, or deactivate the purity list (22K, 24K, sterling, etc.) for each metal.</p>
                    </Link>
                    <Link href="/admin/daily-rates" className="adm-link-card">
                        <h2 className="adm-link-card-title">Daily rate history</h2>
                        <p className="adm-link-card-sub">Every rate ever stored — API fetches, manual entries, and overrides — filterable by metal, purity, source, and date.</p>
                    </Link>
                    <Link href="/rate-management" className="adm-link-card">
                        <h2 className="adm-link-card-title">Manual price override</h2>
                        <p className="adm-link-card-sub">On the Rate Management screen — override a purity's current rate directly, with a required reason.</p>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
