import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import '../../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Audit Log', href: '/admin/audit-log' },
];

const entityLabel = (type: string) => type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function AuditLogIndex({ logs, filters, entityTypes }: { logs: any; filters: any; entityTypes: string[] }) {
    const { flash } = usePage().props as any;
    const [entityType, setEntityType] = useState(filters.entity_type || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/audit-log',
            { entity_type: entityType, date_from: dateFrom, date_to: dateTo },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setEntityType('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/audit-log');
    };

    const hasFilters = entityType || dateFrom || dateTo;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Audit log</h1>
                    <p className="adm-desc">
                        Every protected override the system has recorded — rate edits, price/weight overrides, discounts, purity changes — who did it,
                        when, and why.
                    </p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <form className="adm-card adm-form-row" onSubmit={applyFilters}>
                    <div className="adm-field">
                        <label className="adm-label">Type</label>
                        <select className="adm-select" value={entityType} onChange={(e) => setEntityType(e.target.value)}>
                            <option value="">All</option>
                            {entityTypes.map((t) => (
                                <option key={t} value={t}>
                                    {entityLabel(t)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="adm-field">
                        <label className="adm-label">From</label>
                        <input type="date" className="adm-input" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                    </div>
                    <div className="adm-field">
                        <label className="adm-label">To</label>
                        <input type="date" className="adm-input" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                    </div>
                    <button type="submit" className="adm-btn adm-btn-gold">
                        Apply
                    </button>
                    {hasFilters && (
                        <button type="button" className="adm-btn adm-btn-ghost" onClick={clearFilters}>
                            Clear
                        </button>
                    )}
                </form>

                <div className="adm-card">
                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Type</th>
                                    <th>Field</th>
                                    <th>Old → New</th>
                                    <th>Reason</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="adm-empty">
                                            No audit entries match your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    logs.data.map((log: any) => (
                                        <tr key={log.id}>
                                            <td className="adm-muted">{new Date(log.changed_at).toLocaleString()}</td>
                                            <td>
                                                <span className="adm-badge adm-badge-manual">{entityLabel(log.entity_type)}</span>
                                            </td>
                                            <td className="adm-num">{log.field_name}</td>
                                            <td className="adm-num">
                                                {log.old_value !== null ? (
                                                    <span className="adm-muted">{log.old_value}</span>
                                                ) : (
                                                    <span className="adm-muted">—</span>
                                                )}
                                                {' → '}
                                                <strong>{log.new_value}</strong>
                                            </td>
                                            <td>{log.reason || <span className="adm-muted">—</span>}</td>
                                            <td>{log.changed_by?.username ?? <span className="adm-muted">system</span>}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {logs.total > 0 && (
                        <div className="adm-pagination">
                            <div>
                                Showing {logs.from} to {logs.to} of {logs.total}
                            </div>
                            {logs.links && logs.links.length > 3 && (
                                <div className="adm-page-links">
                                    {logs.links.map((link: any, i: number) => (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className={`adm-page-link ${link.active ? 'active' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
