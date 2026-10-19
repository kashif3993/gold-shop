import { formatNumber, gramsToTraditional, TOLA_GRAMS } from '@/utils/weight';
import * as React from 'react';

interface Shop {
    name: string;
    address: string | null;
    phone: string | null;
    logo: string | null;
}

interface SlipItem {
    item_code: string;
    item_type: string;
    date_received: string;
    status: string;
    gross_weight_grams: string | number;
    stone_weight_grams: string | number;
    cutting_loss_grams: string | number;
    polish_weight_grams: string | number;
    net_weight_grams: string | number;
    labour_cost: string | number;
    purchase_rate_per_gram: string | number;
    purchase_price: string | number;
    metal_type: { name: string } | null;
    purity: { name: string } | null;
    source_party: { name: string } | null;
}

interface PrintDimensions {
    widthMm: number;
    heightMm: number;
    paddingMm: number;
}

const fmt = (n: number) => new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

function formatItemType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatDate(dateStr: string): string {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Gram value broken into the full Gram / Tola / Masha / Ratti / Point row, "0" (not blank) for a printed table. */
function weightRow(grams: number) {
    const traditional = gramsToTraditional(grams);
    return {
        gram: formatNumber(grams, 3),
        tola: traditional.tola || '0',
        masha: traditional.masha || '0',
        ratti: traditional.ratti || '0',
        point: traditional.point || '0',
    };
}

/**
 * The "Save & Print" output from Item Entry — a weight/value slip showing
 * the Net, Stone and Polish weights broken into Gram/Tola/Masha/Ratti/Point,
 * plus costing. Hidden on screen, shown only by @media print (see
 * item-entry-print.css). Width/height/padding come from Admin → Settings.
 */
export default function ItemEntryPrintSlip({
    item,
    shop,
    printerType,
    printDimensions,
}: {
    item: SlipItem;
    shop: Shop;
    printerType: 'regular' | 'thermal';
    printDimensions: PrintDimensions;
}) {
    const isThermal = printerType === 'thermal';
    const netGrams = Number(item.net_weight_grams) || 0;
    const stoneGrams = Number(item.stone_weight_grams) || 0;
    const polishGrams = Number(item.polish_weight_grams) || 0;
    const labourCost = Number(item.labour_cost) || 0;
    const ratePerGram = Number(item.purchase_rate_per_gram) || 0;
    const ratePerTola = ratePerGram * TOLA_GRAMS;
    const totalPrice = Number(item.purchase_price) || 0;
    const metalValue = netGrams * ratePerGram;

    const rows = [
        { label: 'Net Weight', ...weightRow(netGrams) },
        { label: 'Stone Weight', ...weightRow(stoneGrams) },
        { label: 'Polish Weight', ...weightRow(polishGrams) },
    ];

    const style = {
        '--ies-width': `${printDimensions.widthMm}mm`,
        '--ies-padding': `${printDimensions.paddingMm}mm`,
        ...(printDimensions.heightMm > 0 ? { '--ies-height': `${printDimensions.heightMm}mm` } : {}),
    } as React.CSSProperties;

    return (
        <div
            className={`ies-area ${isThermal ? 'ies-thermal' : 'ies-regular'} ${printDimensions.heightMm > 0 ? 'ies-has-height' : ''}`}
            style={style}
        >
            <div className="ies-header">
                {shop.logo && <img src={shop.logo} alt="" className="ies-logo" />}
                <div className="ies-shop-name">{shop.name}</div>
                {shop.address && <div className="ies-shop-line">{shop.address}</div>}
                {shop.phone && <div className="ies-shop-line">{shop.phone}</div>}
            </div>

            <div className="ies-title">Item Weight &amp; Value Slip</div>
            <div className="ies-meta">{formatDate(item.date_received)}</div>

            <div className="ies-fields">
                <div className="ies-field">
                    <span>Item Code</span>
                    <span>{item.item_code}</span>
                </div>
                <div className="ies-field">
                    <span>Type</span>
                    <span>{formatItemType(item.item_type)}</span>
                </div>
                <div className="ies-field">
                    <span>Metal</span>
                    <span>{item.metal_type?.name ?? '—'}</span>
                </div>
                <div className="ies-field">
                    <span>Purity</span>
                    <span>{item.purity?.name ?? '—'}</span>
                </div>
                {item.source_party && (
                    <div className="ies-field">
                        <span>Source</span>
                        <span>{item.source_party.name}</span>
                    </div>
                )}
            </div>

            <div className="ies-section-label">Weight Breakdown</div>
            <table className="ies-weight-table">
                <colgroup>
                    <col style={{ width: '29%' }} />
                    <col style={{ width: '14.2%' }} />
                    <col style={{ width: '14.2%' }} />
                    <col style={{ width: '14.2%' }} />
                    <col style={{ width: '14.2%' }} />
                    <col style={{ width: '14.2%' }} />
                </colgroup>
                <thead>
                    <tr>
                        <th></th>
                        <th>Gram</th>
                        <th>Tola</th>
                        <th>Masha</th>
                        <th>Ratti</th>
                        <th>Point</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label}>
                            <th>{row.label}</th>
                            <td>{row.gram}</td>
                            <td>{row.tola}</td>
                            <td>{row.masha}</td>
                            <td>{row.ratti}</td>
                            <td>{row.point}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="ies-costing">
                <div className="ies-row">
                    <span>Rate / Tola</span>
                    <span>{fmt(ratePerTola)}</span>
                </div>
                <div className="ies-row">
                    <span>Metal Value</span>
                    <span>{fmt(metalValue)}</span>
                </div>
                <div className="ies-row">
                    <span>Manufacturing</span>
                    <span>{fmt(labourCost)}</span>
                </div>
                <div className="ies-row ies-row-grand">
                    <span>Total Price</span>
                    <span>Rs {fmt(totalPrice)}</span>
                </div>
            </div>

            <div className="ies-footer">
                <div>
                    <strong>Software provided by ZK IT HUB</strong>
                </div>
                <div>Contact: 0344-1196139</div>
            </div>
        </div>
    );
}
