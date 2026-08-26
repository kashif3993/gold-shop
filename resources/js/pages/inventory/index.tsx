import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Eye, Edit, Trash2, X } from 'lucide-react';
import '../../../css/inventory.css';
import { formatNumber, fromGrams } from '@/utils/weight';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inventory',
        href: '/inventory',
    },
];

export default function InventoryIndex({ items, filters, metals, purities }: any) {
    const [metalFilter, setMetalFilter] = useState(filters.metal_type_id || '');
    const [purityFilter, setPurityFilter] = useState(filters.purity_id || '');
    const [typeFilter, setTypeFilter] = useState(filters.item_type || '');
    const [dateFilter, setDateFilter] = useState(filters.date || '');
    const [viewItem, setViewItem] = useState<any>(null);

    const handleFilter = (e: any) => {
        e.preventDefault();
        router.get('/inventory', {
            metal_type_id: metalFilter,
            purity_id: purityFilter,
            item_type: typeFilter,
            date: dateFilter,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setMetalFilter('');
        setPurityFilter('');
        setTypeFilter('');
        setDateFilter('');
        router.get('/inventory');
    };

    const handleDelete = (item: any) => {
        if (confirm(`Are you sure you want to delete item "${item.item_code}" (${item.item_type}) from inventory?`)) {
            router.delete(`/items/${item.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory Stock" />

            <div className="inventory-container">
                <div className="inventory-header">
                    <div>
                        <h1 className="inventory-title">Inventory Stock</h1>
                        <p className="inventory-desc">View and manage your current stock of gold and silver items.</p>
                    </div>
                    <Link href="/items/create" className="filter-btn" style={{ textDecoration: 'none' }}>
                        + Add New Item
                    </Link>
                </div>

                <form className="filters-card" onSubmit={handleFilter}>
                    <div className="filter-group">
                        <label className="filter-label">Metal</label>
                        <select className="filter-input" value={metalFilter} onChange={e => setMetalFilter(e.target.value)}>
                            <option value="">All Metals</option>
                            {metals.map((m: any) => (
                                <option key={m.id} value={m.id}>{m.name}</option>
                            ))}
                        </select>
                    </div>
                    
                    <div className="filter-group">
                        <label className="filter-label">Purity</label>
                        <select className="filter-input" value={purityFilter} onChange={e => setPurityFilter(e.target.value)}>
                            <option value="">All Purities</option>
                            {purities.map((p: any) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="filter-group">
                        <label className="filter-label">Item Type</label>
                        <select className="filter-input" value={typeFilter} onChange={e => setTypeFilter(e.target.value)}>
                            <option value="">All Types</option>
                            <optgroup label="Jewelry">
                                <option value="ring">Ring</option>
                                <option value="bangle">Bangle</option>
                                <option value="necklace">Necklace</option>
                                <option value="earring">Earring</option>
                                <option value="bracelet">Bracelet</option>
                                <option value="chain">Chain</option>
                                <option value="pendant">Pendant</option>
                                <option value="locket">Locket</option>
                                <option value="nose_pin">Nose Pin</option>
                                <option value="tops">Tops</option>
                                <option value="other">Other Jewelry</option>
                            </optgroup>
                            <optgroup label="Bullion & Raw">
                                <option value="biscuit">Biscuit / Bar</option>
                                <option value="nugget">Nugget</option>
                                <option value="coin">Coin</option>
                                <option value="piece">Raw Gold / Lagdi</option>
                            </optgroup>
                        </select>
                    </div>

                    <div className="filter-group">
                        <label className="filter-label">Date Received</label>
                        <input type="date" className="filter-input" value={dateFilter} onChange={e => setDateFilter(e.target.value)} />
                    </div>

                    <button type="submit" className="filter-btn">Apply Filters</button>
                    {(metalFilter || purityFilter || typeFilter || dateFilter) && (
                        <button type="button" onClick={clearFilters} className="filter-clear">Clear</button>
                    )}
                </form>

                <div className="table-container">
                    <table className="inventory-table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Type & Metal</th>
                                <th>Source</th>
                                <th>Net Weight</th>
                                <th>Total Cost</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="empty-state" style={{ padding: 0 }}>
                                        <div style={{ position: 'sticky', left: 0, width: '100%', padding: '4rem 1rem', display: 'flex', justifyContent: 'center' }}>
                                            No items found in inventory matching your filters.
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item: any) => (
                                    <tr key={item.id}>
                                        <td><strong>{item.item_code}</strong></td>
                                        <td>
                                            <div style={{ textTransform: 'capitalize', marginBottom: '0.25rem' }}>{item.item_type}</div>
                                            <span className={`badge ${item.metal_type?.name?.toLowerCase() === 'gold' ? 'badge-gold' : 'badge-silver'}`}>
                                                {item.metal_type?.name} • {item.purity?.name}
                                            </span>
                                        </td>
                                        <td>{item.source_party?.name || '-'}</td>
                                        <td className="weight-text">{formatNumber(item.net_weight_grams, 3)} g</td>
                                        <td className="price-text">Rs {formatNumber(item.purchase_price, 2)}</td>
                                        <td>
                                            <span style={{
                                                color: item.status === 'in_stock' ? '#16a34a' : '#9ca3af',
                                                textTransform: 'capitalize',
                                                fontWeight: 500
                                            }}>
                                                {item.status.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td>{new Date(item.date_received).toLocaleDateString()}</td>
                                        <td>
                                            <div className="action-buttons" style={{ justifyContent: 'flex-end' }}>
                                                <button
                                                    type="button"
                                                    className="action-btn view"
                                                    title="View Item Details"
                                                    onClick={() => setViewItem(item)}
                                                >
                                                    <Eye size={16} />
                                                </button>
                                                <Link
                                                    href={`/items/${item.id}/edit`}
                                                    className="action-btn edit"
                                                    title="Edit Item"
                                                    style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}
                                                >
                                                    <Edit size={16} />
                                                </Link>
                                                <button
                                                    type="button"
                                                    className="action-btn delete"
                                                    title="Delete Item"
                                                    onClick={() => handleDelete(item)}
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {/* Basic Pagination */}
                    {items.links && items.links.length > 3 && (
                        <div className="pagination">
                        <div style={{ color: 'var(--color-muted-foreground, #6b7280)', fontSize: '0.9rem' }}>
                                Showing {items.from || 0} to {items.to || 0} of {items.total} results
                            </div>
                            <div className="pagination-links">
                                {items.links.map((link: any, index: number) => (
                                    <Link 
                                        key={index}
                                        href={link.url || '#'}
                                        className={`page-link ${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Quick View Item Modal */}
                {viewItem && (
                    <div style={{
                        position: 'fixed',
                        inset: 0,
                        backgroundColor: 'rgba(0, 0, 0, 0.65)',
                        backdropFilter: 'blur(4px)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 9999,
                        padding: '1rem'
                    }}>
                        <div style={{
                            backgroundColor: '#ffffff',
                            borderRadius: '12px',
                            maxWidth: '650px',
                            width: '100%',
                            boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1)',
                            overflow: 'hidden',
                            animation: 'fadeIn 0.2s ease-out'
                        }}>
                            {/* Modal Header */}
                            <div style={{
                                padding: '1.25rem 1.5rem',
                                borderBottom: '1px solid #e5e7eb',
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                backgroundColor: '#f9fafb'
                            }}>
                                <div>
                                    <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 700, color: '#111827' }}>
                                        {viewItem.item_code}
                                    </h3>
                                    <span style={{ textTransform: 'capitalize', fontSize: '0.85rem', color: '#6b7280' }}>
                                        {viewItem.item_type} • {viewItem.metal_type?.name} ({viewItem.purity?.name})
                                    </span>
                                </div>
                                <button
                                    onClick={() => setViewItem(null)}
                                    style={{
                                        border: 'none',
                                        background: 'transparent',
                                        cursor: 'pointer',
                                        padding: '0.25rem',
                                        color: '#6b7280',
                                        display: 'flex',
                                        alignItems: 'center',
                                        borderRadius: '4px'
                                    }}
                                >
                                    <X size={20} />
                                </button>
                            </div>

                            {/* Modal Body */}
                            <div style={{ padding: '1.5rem', maxHeight: '70vh', overflowY: 'auto' }}>
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
                                    <div style={{ padding: '0.75rem', backgroundColor: '#f3f4f6', borderRadius: '8px' }}>
                                        <div style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: 600 }}>Gross Weight</div>
                                        <div style={{ fontSize: '1.1rem', fontWeight: 700, color: '#111827', marginTop: '0.25rem' }}>{formatNumber(viewItem.gross_weight_grams, 3)} g</div>
                                    </div>
                                    <div style={{ padding: '0.75rem', backgroundColor: '#fef2f2', borderRadius: '8px' }}>
                                        <div style={{ fontSize: '0.75rem', color: '#ef4444', textTransform: 'uppercase', fontWeight: 600 }}>Stone Weight</div>
                                        <div style={{ fontSize: '1.1rem', fontWeight: 700, color: '#991b1b', marginTop: '0.25rem' }}>{formatNumber(viewItem.stone_weight_grams, 3)} g</div>
                                    </div>
                                    <div style={{ padding: '0.75rem', backgroundColor: '#fffbeb', borderRadius: '8px' }}>
                                        <div style={{ fontSize: '0.75rem', color: '#f59e0b', textTransform: 'uppercase', fontWeight: 600 }}>Cutting Loss</div>
                                        <div style={{ fontSize: '1.1rem', fontWeight: 700, color: '#92400e', marginTop: '0.25rem' }}>{formatNumber(viewItem.cutting_loss_grams, 3)} g</div>
                                    </div>
                                    <div style={{ padding: '0.75rem', backgroundColor: '#ecfdf5', borderRadius: '8px' }}>
                                        <div style={{ fontSize: '0.75rem', color: '#10b981', textTransform: 'uppercase', fontWeight: 600 }}>Net Weight</div>
                                        <div style={{ fontSize: '1.1rem', fontWeight: 700, color: '#065f46', marginTop: '0.25rem' }}>{formatNumber(viewItem.net_weight_grams, 3)} g</div>
                                    </div>
                                </div>

                                {/* Net Weight breakdown in all units */}
                                <div style={{ marginBottom: '1.5rem', padding: '1rem', border: '1px solid #e5e7eb', borderRadius: '8px' }}>
                                    <div style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151', marginBottom: '0.5rem' }}>
                                        Net Weight in Traditional Units
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '0.5rem', fontSize: '0.85rem', color: '#4b5563' }}>
                                        <div>Tola: <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'tola'), 3)}</strong></div>
                                        <div>Masha: <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'masha'), 3)}</strong></div>
                                        <div>Ratti: <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'ratti'), 3)}</strong></div>
                                        <div>Point: <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'point'), 3)}</strong></div>
                                    </div>
                                </div>

                                {/* Financial Details */}
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1rem', fontSize: '0.9rem' }}>
                                    <div>
                                        <span style={{ color: '#6b7280' }}>Purchase Rate: </span>
                                        <strong>Rs {formatNumber(viewItem.purchase_rate_per_gram, 2)} / g</strong>
                                    </div>
                                    <div>
                                        <span style={{ color: '#6b7280' }}>Labour Cost: </span>
                                        <strong>Rs {formatNumber(viewItem.labour_cost, 2)}</strong>
                                    </div>
                                    <div>
                                        <span style={{ color: '#6b7280' }}>Polish Cost: </span>
                                        <strong>Rs {formatNumber(viewItem.polish_cost, 2)}</strong>
                                    </div>
                                    <div>
                                        <span style={{ color: '#6b7280' }}>Total Cost: </span>
                                        <strong style={{ color: '#2563eb', fontSize: '1.05rem' }}>Rs {formatNumber(viewItem.purchase_price, 2)}</strong>
                                    </div>
                                </div>

                                <div style={{ borderTop: '1px solid #e5e7eb', paddingTop: '1rem', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', fontSize: '0.85rem' }}>
                                    <div>
                                        <span style={{ color: '#6b7280' }}>Source Party: </span>
                                        <strong>{viewItem.source_party?.name || 'N/A'}</strong>
                                    </div>
                                    <div>
                                        <span style={{ color: '#6b7280' }}>Date Received: </span>
                                        <strong>{new Date(viewItem.date_received).toLocaleDateString()}</strong>
                                    </div>
                                </div>
                            </div>

                            {/* Modal Footer */}
                            <div style={{
                                padding: '1rem 1.5rem',
                                borderTop: '1px solid #e5e7eb',
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                backgroundColor: '#f9fafb'
                            }}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const itemToDel = viewItem;
                                        setViewItem(null);
                                        handleDelete(itemToDel);
                                    }}
                                    style={{
                                        border: 'none',
                                        background: '#fee2e2',
                                        color: '#b91c1c',
                                        padding: '0.5rem 1rem',
                                        borderRadius: '6px',
                                        fontSize: '0.875rem',
                                        cursor: 'pointer',
                                        fontWeight: 600,
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: '0.35rem'
                                    }}
                                >
                                    <Trash2 size={15} /> Delete Item
                                </button>
                                <div style={{ display: 'flex', gap: '0.75rem' }}>
                                    <Link
                                        href={`/items/${viewItem.id}/edit`}
                                        style={{
                                            backgroundColor: '#4f46e5',
                                            color: '#ffffff',
                                            padding: '0.5rem 1.25rem',
                                            borderRadius: '6px',
                                            fontSize: '0.875rem',
                                            textDecoration: 'none',
                                            fontWeight: 600,
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: '0.35rem'
                                        }}
                                    >
                                        <Edit size={15} /> Edit Item
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => setViewItem(null)}
                                        style={{
                                            border: '1px solid #d1d5db',
                                            background: '#ffffff',
                                            color: '#374151',
                                            padding: '0.5rem 1rem',
                                            borderRadius: '6px',
                                            fontSize: '0.875rem',
                                            cursor: 'pointer'
                                        }}
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
