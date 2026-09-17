import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Pencil, X, Check } from 'lucide-react';
import '../../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }, { title: 'Purities', href: '/admin/purities' }];

interface Purity {
    id: number;
    metal_type_id: number;
    name: string;
    fineness_percent: string | number;
    is_active: boolean;
}

interface Metal {
    id: number;
    name: string;
    purities: Purity[];
}

export default function PuritiesIndex({ metals }: { metals: Metal[] }) {
    const { flash } = usePage().props as any;
    const [editingId, setEditingId] = useState<number | null>(null);

    const addForm = useForm({ metal_type_id: metals[0]?.id ? String(metals[0].id) : '', name: '', fineness_percent: '' });
    const editForm = useForm({ name: '', fineness_percent: '', reason: '' });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post('/admin/purities', {
            preserveScroll: true,
            onSuccess: () => addForm.reset('name', 'fineness_percent'),
        });
    };

    const startEdit = (p: Purity) => {
        setEditingId(p.id);
        editForm.setData({ name: p.name, fineness_percent: String(p.fineness_percent), reason: '' });
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.clearErrors();
    };

    const submitEdit = (e: React.FormEvent, id: number) => {
        e.preventDefault();
        editForm.put(`/admin/purities/${id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const toggle = (p: Purity) => {
        const verb = p.is_active ? 'deactivate' : 'reactivate';
        if (confirm(`Are you sure you want to ${verb} "${p.name}"?`)) {
            router.post(`/admin/purities/${p.id}/toggle`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purities" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Purities</h1>
                    <p className="adm-desc">The purity list every item, sale, and rate is priced against. Deactivate instead of deleting — purities stay linked to historical records.</p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <div className="adm-card">
                    <h2 className="adm-card-title">Add a purity</h2>
                    <form className="adm-form-row" onSubmit={submitAdd}>
                        <div className="adm-field">
                            <label className="adm-label">Metal</label>
                            <select className="adm-select" value={addForm.data.metal_type_id} onChange={e => addForm.setData('metal_type_id', e.target.value)}>
                                {metals.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </div>
                        <div className="adm-field">
                            <label className="adm-label">Name</label>
                            <input className="adm-input" value={addForm.data.name} onChange={e => addForm.setData('name', e.target.value)} placeholder="e.g. 18K" />
                            {addForm.errors.name && <span className="adm-error">{addForm.errors.name}</span>}
                        </div>
                        <div className="adm-field">
                            <label className="adm-label">Fineness %</label>
                            <input type="number" step="0.01" min="0.01" max="100" className="adm-input" style={{ maxWidth: '110px' }}
                                value={addForm.data.fineness_percent} onChange={e => addForm.setData('fineness_percent', e.target.value)} placeholder="75.0" />
                            {addForm.errors.fineness_percent && <span className="adm-error">{addForm.errors.fineness_percent}</span>}
                        </div>
                        <button type="submit" className="adm-btn adm-btn-gold" disabled={addForm.processing}>
                            {addForm.processing ? 'Adding…' : 'Add purity'}
                        </button>
                    </form>
                </div>

                {metals.map(metal => (
                    <div className="adm-card" key={metal.id}>
                        <h2 className="adm-card-title">{metal.name}</h2>
                        {metal.purities.length === 0 ? (
                            <div className="adm-empty">No purities yet for {metal.name}.</div>
                        ) : (
                            <div className="adm-table-wrap">
                                <table className="adm-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Fineness</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {metal.purities.map(p => (
                                            editingId === p.id ? (
                                                <tr key={p.id}>
                                                    <td colSpan={4}>
                                                        <form className="adm-form-row" onSubmit={e => submitEdit(e, p.id)}>
                                                            <div className="adm-field">
                                                                <label className="adm-label">Name</label>
                                                                <input className="adm-input" value={editForm.data.name} onChange={e => editForm.setData('name', e.target.value)} />
                                                                {editForm.errors.name && <span className="adm-error">{editForm.errors.name}</span>}
                                                            </div>
                                                            <div className="adm-field">
                                                                <label className="adm-label">Fineness %</label>
                                                                <input type="number" step="0.01" min="0.01" max="100" className="adm-input" style={{ maxWidth: '110px' }}
                                                                    value={editForm.data.fineness_percent} onChange={e => editForm.setData('fineness_percent', e.target.value)} />
                                                                {editForm.errors.fineness_percent && <span className="adm-error">{editForm.errors.fineness_percent}</span>}
                                                            </div>
                                                            <div className="adm-field" style={{ flex: 1, minWidth: '180px' }}>
                                                                <label className="adm-label">Reason (optional)</label>
                                                                <input className="adm-input" style={{ width: '100%' }} value={editForm.data.reason} onChange={e => editForm.setData('reason', e.target.value)} placeholder="e.g. corrected fineness typo" />
                                                            </div>
                                                            <button type="submit" className="adm-btn adm-btn-gold adm-btn-sm" disabled={editForm.processing}>
                                                                <Check size={14} /> Save
                                                            </button>
                                                            <button type="button" className="adm-btn adm-btn-ghost adm-btn-sm" onClick={cancelEdit}>
                                                                <X size={14} /> Cancel
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            ) : (
                                                <tr key={p.id} className={p.is_active ? '' : 'inactive'}>
                                                    <td>{p.name}</td>
                                                    <td className="adm-num">{p.fineness_percent}%</td>
                                                    <td>
                                                        <span className={`adm-badge ${p.is_active ? 'adm-badge-active' : 'adm-badge-inactive'}`}>
                                                            {p.is_active ? 'Active' : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <button type="button" className="adm-btn adm-btn-ghost adm-btn-sm" onClick={() => startEdit(p)} style={{ marginRight: '0.5rem' }}>
                                                            <Pencil size={13} /> Edit
                                                        </button>
                                                        <button type="button" className={`adm-btn adm-btn-sm ${p.is_active ? 'adm-btn-danger' : 'adm-btn-ghost'}`} onClick={() => toggle(p)}>
                                                            {p.is_active ? 'Deactivate' : 'Reactivate'}
                                                        </button>
                                                    </td>
                                                </tr>
                                            )
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
