import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Eye, Edit, Trash2, X, Printer } from 'lucide-react';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import '../../../css/inventory.css';
import { formatNumber, fromGrams } from '@/utils/weight';

/** Sentinel for Radix's "no value selected" — it can't accept an empty string. */
const ALL = '__all__';

interface SelectOption {
    value: string;
    label: string;
}

interface SelectOptionGroup {
    label: string;
    options: SelectOption[];
}

/**
 * Filter dropdown built on Radix's popover-based Select instead of a native
 * <select> — a native select's option list is rendered by the OS, not the
 * page, so it ignores our sizing/theme and can render huge at some Windows
 * display-scaling settings. This one is a real DOM popup we control.
 */
function FilterSelect({
    value, onChange, placeholder, options, groups,
}: {
    value: string;
    onChange: (v: string) => void;
    placeholder: string;
    options?: SelectOption[];
    groups?: SelectOptionGroup[];
}) {
    return (
        <Select value={value || ALL} onValueChange={v => onChange(v === ALL ? '' : v)}>
            <SelectTrigger className="inv-select-trigger">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent className="inv-select-content">
                <SelectItem className="inv-select-item" value={ALL}>{placeholder}</SelectItem>
                {options?.map(o => (
                    <SelectItem key={o.value} className="inv-select-item" value={o.value}>{o.label}</SelectItem>
                ))}
                {groups?.map(g => (
                    <SelectGroup key={g.label}>
                        <SelectLabel className="inv-select-group-label">{g.label}</SelectLabel>
                        {g.options.map(o => (
                            <SelectItem key={o.value} className="inv-select-item" value={o.value}>{o.label}</SelectItem>
                        ))}
                    </SelectGroup>
                ))}
            </SelectContent>
        </Select>
    );
}

const ITEM_TYPE_GROUPS: SelectOptionGroup[] = [
    {
        label: 'Jewelry',
        options: [
            { value: 'ring', label: 'Ring' },
            { value: 'bangle', label: 'Bangle' },
            { value: 'necklace', label: 'Necklace' },
            { value: 'earring', label: 'Earring' },
            { value: 'bracelet', label: 'Bracelet' },
            { value: 'chain', label: 'Chain' },
            { value: 'pendant', label: 'Pendant' },
            { value: 'locket', label: 'Locket' },
            { value: 'nose_pin', label: 'Nose Pin' },
            { value: 'tops', label: 'Tops' },
            { value: 'other', label: 'Other Jewelry' },
        ],
    },
    {
        label: 'Bullion & Raw',
        options: [
            { value: 'biscuit', label: 'Biscuit / Bar' },
            { value: 'nugget', label: 'Nugget' },
            { value: 'coin', label: 'Coin' },
            { value: 'piece', label: 'Raw Gold / Lagdi' },
        ],
    },
];

const TOLA_GRAMS = 11.6638038;
const gToTola = (g: number) => g / TOLA_GRAMS;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inventory',
        href: '/inventory',
    },
];

/** Days a piece has sat in stock, with an aging band. Only meaningful while in_stock. */
function agingInfo(dateReceived: string, status: string): { text: string; cls: string } {
    if (status !== 'in_stock') return { text: '—', cls: 'inv-age-none' };
    const received = new Date(dateReceived).getTime();
    if (isNaN(received)) return { text: '—', cls: 'inv-age-none' };
    const days = Math.max(0, Math.floor((Date.now() - received) / 86_400_000));
    const text = days === 0 ? 'Today' : `${days}d`;
    if (days <= 30) return { text, cls: 'inv-age-fresh' };
    if (days <= 90) return { text, cls: 'inv-age-aging' };
    return { text, cls: 'inv-age-stale' };
}

export default function InventoryIndex({ items, filters, metals, purities, parties = [], stockSummary = [] }: any) {
    const [metalFilter, setMetalFilter] = useState(filters.metal_type_id || '');
    const [purityFilter, setPurityFilter] = useState(filters.purity_id || '');
    const [typeFilter, setTypeFilter] = useState(filters.item_type || '');
    const [dateFilter, setDateFilter] = useState(filters.date || '');
    const [sourceFilter, setSourceFilter] = useState(filters.source_party_id || '');
    const [viewItem, setViewItem] = useState<any>(null);

    const handleFilter = (e: any) => {
        e.preventDefault();
        router.get('/inventory', {
            metal_type_id: metalFilter,
            purity_id: purityFilter,
            item_type: typeFilter,
            date: dateFilter,
            source_party_id: sourceFilter,
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
        setSourceFilter('');
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

                {stockSummary.length > 0 && (
                    <div className="inv-stock-summary">
                        {stockSummary.map((row: any) => (
                            <div key={row.metal} className="inv-stock-card">
                                <div className="inv-stock-metal">{row.metal} on hand</div>
                                <div className="inv-stock-main">
                                    <span className="inv-stock-grams">{formatNumber(row.in_stock_grams, 3)} g</span>
                                    <span className="inv-stock-tola">≈ {formatNumber(gToTola(row.in_stock_grams), 3)} tola</span>
                                </div>
                                <div className="inv-stock-sub">
                                    {row.in_stock_count} item{row.in_stock_count === 1 ? '' : 's'} ready to sell
                                </div>
                                {row.bought_back_count > 0 && (
                                    <div className="inv-stock-scrap">
                                        + {formatNumber(row.bought_back_grams, 3)} g from {row.bought_back_count} bought-back piece{row.bought_back_count === 1 ? '' : 's'} (not yet reprocessed)
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                <form className="filters-card" onSubmit={handleFilter}>
                    <div className="filter-group">
                        <label className="filter-label">Metal</label>
                        <FilterSelect
                            value={metalFilter}
                            onChange={setMetalFilter}
                            placeholder="All Metals"
                            options={metals.map((m: any) => ({ value: String(m.id), label: m.name }))}
                        />
                    </div>

                    <div className="filter-group">
                        <label className="filter-label">Purity</label>
                        <FilterSelect
                            value={purityFilter}
                            onChange={setPurityFilter}
                            placeholder="All Purities"
                            options={purities.map((p: any) => ({ value: String(p.id), label: p.name }))}
                        />
                    </div>

                    <div className="filter-group">
                        <label className="filter-label">Item Type</label>
                        <FilterSelect
                            value={typeFilter}
                            onChange={setTypeFilter}
                            placeholder="All Types"
                            groups={ITEM_TYPE_GROUPS}
                        />
                    </div>

                    <div className="filter-group">
                        <label className="filter-label">Source Party</label>
                        <FilterSelect
                            value={sourceFilter}
                            onChange={setSourceFilter}
                            placeholder="All Sources"
                            options={parties.map((p: any) => ({ value: String(p.id), label: p.name }))}
                        />
                    </div>

                    <div className="filter-group">
                        <label className="filter-label">Date Received</label>
                        <input type="date" className="filter-input" value={dateFilter} onChange={e => setDateFilter(e.target.value)} />
                    </div>

                    <button type="submit" className="filter-btn">Apply Filters</button>
                    {(metalFilter || purityFilter || typeFilter || dateFilter || sourceFilter) && (
                        <button type="button" onClick={clearFilters} className="filter-clear">Clear</button>
                    )}
                </form>

                <div className="table-container">
                    <div className="table-scroll">
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
                                <th>Age</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="empty-state" style={{ padding: 0 }}>
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
                                            {(() => {
                                                const a = agingInfo(item.date_received, item.status);
                                                return <span className={`inv-age ${a.cls}`}>{a.text}</span>;
                                            })()}
                                        </td>
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
                                                    href={`/items/${item.id}/tag`}
                                                    className="action-btn"
                                                    title="Print Tag"
                                                    style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', color: '#6366f1' }}
                                                >
                                                    <Printer size={16} />
                                                </Link>
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
                    </div>

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
                    <div className="item-modal-overlay" onMouseDown={() => setViewItem(null)}>
                        <div className="item-modal" onMouseDown={e => e.stopPropagation()}>
                            {/* Modal Header */}
                            <div className="item-modal-head">
                                <div>
                                    <h3 className="item-modal-code">{viewItem.item_code}</h3>
                                    <span className="item-modal-meta">
                                        {viewItem.item_type} • {viewItem.metal_type?.name} ({viewItem.purity?.name})
                                    </span>
                                </div>
                                <button type="button" className="item-modal-close" onClick={() => setViewItem(null)} aria-label="Close">
                                    <X size={20} />
                                </button>
                            </div>

                            {/* Modal Body */}
                            <div className="item-modal-body">
                                <div className="item-modal-stats">
                                    <div className="item-modal-stat">
                                        <div className="item-modal-stat-label">Gross Weight</div>
                                        <div className="item-modal-stat-value">{formatNumber(viewItem.gross_weight_grams, 3)} g</div>
                                    </div>
                                    <div className="item-modal-stat negative">
                                        <div className="item-modal-stat-label">Stone Weight</div>
                                        <div className="item-modal-stat-value">{formatNumber(viewItem.stone_weight_grams, 3)} g</div>
                                    </div>
                                    <div className="item-modal-stat negative">
                                        <div className="item-modal-stat-label">Cutting Loss</div>
                                        <div className="item-modal-stat-value">{formatNumber(viewItem.cutting_loss_grams, 3)} g</div>
                                    </div>
                                    <div className="item-modal-stat gold">
                                        <div className="item-modal-stat-label">Net Weight</div>
                                        <div className="item-modal-stat-value">{formatNumber(viewItem.net_weight_grams, 3)} g</div>
                                    </div>
                                </div>

                                {/* Net Weight breakdown in all units */}
                                <div className="item-modal-trad">
                                    <div className="item-modal-trad-title">Net Weight in Traditional Units</div>
                                    <div className="item-modal-trad-grid">
                                        <div>Tola <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'tola'), 3)}</strong></div>
                                        <div>Masha <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'masha'), 3)}</strong></div>
                                        <div>Ratti <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'ratti'), 3)}</strong></div>
                                        <div>Point <strong>{formatNumber(fromGrams(parseFloat(viewItem.net_weight_grams) || 0, 'point'), 3)}</strong></div>
                                    </div>
                                </div>

                                {/* Financial Details */}
                                <div className="item-modal-fin">
                                    <div>
                                        <span className="item-modal-fin-label">Purchase Rate</span>
                                        <strong>Rs {formatNumber(viewItem.purchase_rate_per_gram, 2)} / g</strong>
                                    </div>
                                    <div>
                                        <span className="item-modal-fin-label">Labour Cost</span>
                                        <strong>Rs {formatNumber(viewItem.labour_cost, 2)}</strong>
                                    </div>
                                    <div>
                                        <span className="item-modal-fin-label">Polish Cost</span>
                                        <strong>Rs {formatNumber(viewItem.polish_cost, 2)}</strong>
                                    </div>
                                    <div>
                                        <span className="item-modal-fin-label">Total Cost</span>
                                        <strong className="item-modal-total">Rs {formatNumber(viewItem.purchase_price, 2)}</strong>
                                    </div>
                                </div>

                                <div className="item-modal-extra">
                                    <div>
                                        <span className="item-modal-fin-label">Source Party</span>
                                        <strong>{viewItem.source_party?.name || 'N/A'}</strong>
                                    </div>
                                    <div>
                                        <span className="item-modal-fin-label">Date Received</span>
                                        <strong>{new Date(viewItem.date_received).toLocaleDateString()}</strong>
                                    </div>
                                </div>
                            </div>

                            {/* Modal Footer */}
                            <div className="item-modal-footer">
                                <button
                                    type="button"
                                    className="item-modal-btn item-modal-btn-danger"
                                    onClick={() => {
                                        const itemToDel = viewItem;
                                        setViewItem(null);
                                        handleDelete(itemToDel);
                                    }}
                                >
                                    <Trash2 size={15} /> Delete Item
                                </button>
                                <div className="item-modal-footer-actions">
                                    <Link href={`/items/${viewItem.id}/edit`} className="item-modal-btn item-modal-btn-gold">
                                        <Edit size={15} /> Edit Item
                                    </Link>
                                    <Link href={`/items/${viewItem.id}/tag`} className="item-modal-btn item-modal-btn-ghost-gold">
                                        <Printer size={15} /> Print Tag
                                    </Link>
                                    <button type="button" className="item-modal-btn item-modal-btn-ghost" onClick={() => setViewItem(null)}>
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
