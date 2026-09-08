import { Head, router, Link, usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Eye, Edit, Trash2, Plus, X } from 'lucide-react';
import PartyForm from '@/components/party-form';
import '../../../css/parties.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Parties', href: '/parties' }];

export function partyTypeClass(name?: string): string {
    const key = (name || '').toLowerCase().replace(/\s+/g, '-');
    return `pty-type-badge pty-type-${key}`;
}

export default function PartiesIndex({ parties, partyTypes, filters }: any) {
    const { flash } = usePage().props as any;
    const [typeFilter, setTypeFilter] = useState(filters.party_type_id || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [search, setSearch] = useState(filters.search || '');
    const [showCreate, setShowCreate] = useState(false);

    const createForm = useForm({
        party_type_id: '',
        name: '',
        phone: '',
        address: '',
        notes: '',
        is_active: true,
    });

    const closeCreate = () => {
        setShowCreate(false);
        createForm.reset();
        createForm.clearErrors();
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/parties', {
            preserveScroll: true,
            onSuccess: () => closeCreate(),
        });
    };

    const applyFilters = (e: any) => {
        e.preventDefault();
        router.get('/parties', {
            party_type_id: typeFilter,
            status: statusFilter,
            search: search,
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setTypeFilter('');
        setStatusFilter('');
        setSearch('');
        router.get('/parties');
    };

    const handleDelete = (party: any) => {
        if (confirm(`Delete party "${party.name}"? This cannot be undone.`)) {
            router.delete(`/parties/${party.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Parties" />

            <div className="pty-page">
                <div className="pty-page-head">
                    <div>
                        <h1 className="pty-page-title">Parties</h1>
                        <p className="pty-page-desc">Customers, karigars, wholesalers, other shops and companies you deal with.</p>
                    </div>
                    <button type="button" onClick={() => setShowCreate(true)} className="pty-filter-btn" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                        <Plus size={16} /> Add Party
                    </button>
                </div>

                {flash?.success && <div className="pty-flash pty-flash-success">{flash.success}</div>}
                {flash?.error && <div className="pty-flash pty-flash-error">{flash.error}</div>}

                <form className="pty-filters" onSubmit={applyFilters}>
                    <div className="pty-filter-group">
                        <label className="pty-filter-label">Party Type</label>
                        <select className="pty-filter-input" value={typeFilter} onChange={e => setTypeFilter(e.target.value)}>
                            <option value="">All Types</option>
                            {partyTypes.map((t: any) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="pty-filter-group">
                        <label className="pty-filter-label">Status</label>
                        <select className="pty-filter-input" value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div className="pty-filter-group">
                        <label className="pty-filter-label">Search (name / phone)</label>
                        <input className="pty-filter-input" value={search} onChange={e => setSearch(e.target.value)} placeholder="e.g. Ahmed or 0300..." />
                    </div>

                    <button type="submit" className="pty-filter-btn">Apply Filters</button>
                    {(typeFilter || statusFilter || search) && (
                        <button type="button" onClick={clearFilters} className="pty-filter-clear">Clear</button>
                    )}
                </form>

                <div className="pty-table-wrap">
                    <div className="pty-table-scroll">
                    <table className="pty-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Phone</th>
                                <th>Dealings</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {parties.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="pty-empty" style={{ padding: 0 }}>
                                        <div style={{ position: 'sticky', left: 0, width: '100%', padding: '4rem 1rem', display: 'flex', justifyContent: 'center' }}>
                                            No parties match your filters.
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                parties.data.map((party: any) => (
                                    <tr key={party.id}>
                                        <td>
                                            <Link href={`/parties/${party.id}`} className="pty-link">
                                                {party.name}
                                            </Link>
                                        </td>
                                        <td><span className={partyTypeClass(party.party_type?.name)}>{party.party_type?.name || '-'}</span></td>
                                        <td>{party.phone || <span className="led-muted">-</span>}</td>
                                        <td>
                                            {party.transactions_count + party.invoices_count} record{party.transactions_count + party.invoices_count === 1 ? '' : 's'}
                                        </td>
                                        <td>
                                            {party.is_active
                                                ? <span className="pty-active">Active</span>
                                                : <span className="pty-inactive-tag">Inactive</span>}
                                        </td>
                                        <td>
                                            <div className="pty-actions" style={{ justifyContent: 'flex-end' }}>
                                                <Link href={`/parties/${party.id}`} className="pty-action-btn view" title="View ledger">
                                                    <Eye size={16} />
                                                </Link>
                                                <Link href={`/parties/${party.id}/edit`} className="pty-action-btn edit" title="Edit">
                                                    <Edit size={16} />
                                                </Link>
                                                <button type="button" className="pty-action-btn delete" title="Delete" onClick={() => handleDelete(party)}>
                                                    <Trash2 size={16} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    </div>

                    {parties.links && parties.links.length > 3 && (
                        <div className="pty-pagination">
                            <div>
                                Showing {parties.from || 0} to {parties.to || 0} of {parties.total} parties
                            </div>
                            <div className="pty-pagination-links">
                                {parties.links.map((link: any, index: number) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`pty-page-link ${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {showCreate && (
                    <div className="pty-modal-overlay" onMouseDown={closeCreate}>
                        <div className="pty-modal" onMouseDown={e => e.stopPropagation()}>
                            <div className="pty-modal-head">
                                <h3>Add Party</h3>
                                <button type="button" className="pty-modal-close" onClick={closeCreate} aria-label="Close">
                                    <X size={18} />
                                </button>
                            </div>
                            <div className="pty-modal-body">
                                <PartyForm
                                    data={createForm.data as any}
                                    setData={createForm.setData as any}
                                    errors={createForm.errors as any}
                                    processing={createForm.processing}
                                    partyTypes={partyTypes}
                                    onSubmit={submitCreate}
                                    submitLabel="Create Party"
                                    onCancel={closeCreate}
                                />
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
