import { Head, Link } from '@inertiajs/react';
import { QRCodeSVG, QRCodeCanvas } from 'qrcode.react';
import Barcode from 'react-barcode';
import { useRef } from 'react';
import { Printer, ArrowLeft, Download, Package, QrCode } from 'lucide-react';

interface Item {
    id: number;
    item_code: string;
    qr_payload: string | null;
    item_type: string;
    gross_weight_grams: string;
    stone_weight_grams: string;
    cutting_loss_grams: string;
    net_weight_grams: string;
    labour_cost: string;
    polish_cost: string;
    purchase_rate_per_gram: string;
    purchase_price: string;
    date_received: string;
    status: string;
    metal_type: { id: number; name: string } | null;
    purity: { id: number; name: string } | null;
    source_party: { id: number; name: string } | null;
}

interface TagProps {
    item: Item;
}

function formatItemType(type: string): string {
    return type
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function ItemTag({ item }: TagProps) {
    const qrValue = item.qr_payload || item.item_code;
    const netWeight = parseFloat(item.net_weight_grams || '0');
    const purchasePrice = parseFloat(item.purchase_price || '0');

    const qrCanvasRef = useRef<HTMLCanvasElement>(null);
    const barcodeWrapperRef = useRef<HTMLDivElement>(null);

    const handlePrint = () => window.print();

    /** Download the QR code as a PNG file */
    const downloadQR = () => {
        const canvas = qrCanvasRef.current;
        if (!canvas) return;
        const url = canvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = url;
        a.download = `QR-${item.item_code}.png`;
        a.click();
    };

    /** Download the barcode SVG as a PNG file */
    const downloadBarcode = () => {
        const svg = barcodeWrapperRef.current?.querySelector('svg');
        if (!svg) return;
        const serializer = new XMLSerializer();
        const svgStr = serializer.serializeToString(svg);
        const svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(svgBlob);
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width * 2;
            canvas.height = img.height * 2;
            const ctx = canvas.getContext('2d')!;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            const pngUrl = canvas.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = pngUrl;
            a.download = `Barcode-${item.item_code}.png`;
            a.click();
        };
        img.src = url;
    };

    return (
        <>
            <Head title={`Tag — ${item.item_code}`} />

            {/* ─── Screen controls (hidden on print) ─── */}
            <div className="no-print min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-900 dark:to-slate-800 px-4 py-8 flex flex-col items-center gap-6">
                {/* Top bar */}
                <div className="w-full max-w-2xl flex items-center justify-between flex-wrap gap-3">
                    <Link
                        href={route('inventory.index')}
                        className="flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-gold transition-colors font-medium"
                    >
                        <ArrowLeft size={18} />
                        Back to Inventory
                    </Link>
                    <div className="flex items-center gap-2 flex-wrap">
                        <button
                            onClick={downloadQR}
                            title="Download QR Code as PNG"
                            className="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:border-gold hover:text-gold font-medium px-4 py-2 rounded-xl shadow-sm transition-all text-sm"
                        >
                            <QrCode size={16} />
                            Download QR
                        </button>
                        <button
                            onClick={downloadBarcode}
                            title="Download Barcode as PNG"
                            className="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:border-gold hover:text-gold font-medium px-4 py-2 rounded-xl shadow-sm transition-all text-sm"
                        >
                            <Download size={16} />
                            Download Barcode
                        </button>
                        <button
                            onClick={handlePrint}
                            className="flex items-center gap-2 bg-gold hover:bg-gold/90 text-white font-semibold px-5 py-2.5 rounded-xl shadow-lg transition-all hover:shadow-gold/30 hover:scale-105 active:scale-95"
                        >
                            <Printer size={18} />
                            Print Tag
                        </button>
                    </div>
                </div>

                {/* Success banner */}
                <div className="w-full max-w-2xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-xl px-5 py-3 flex items-center gap-3 text-emerald-800 dark:text-emerald-300 text-sm font-medium">
                    <span className="text-lg">✅</span>
                    Item <strong>{item.item_code}</strong> created successfully. Print the tag below.
                </div>

                {/* Preview label */}
                <p className="text-slate-500 dark:text-slate-400 text-sm font-medium tracking-wide uppercase">
                    Tag Preview
                </p>

                {/* ─── TAG CARD ─── */}
                <div className="tag-card">
                    <TagCard item={item} qrValue={qrValue} netWeight={netWeight} purchasePrice={purchasePrice} />
                </div>

                {/* Item details summary */}
                <div className="w-full max-w-2xl bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                    <h2 className="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4 flex items-center gap-2">
                        <Package size={16} />
                        Item Summary
                    </h2>
                    <dl className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Item Code</dt>
                            <dd className="font-mono font-semibold text-slate-900 dark:text-white mt-0.5">{item.item_code}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Type</dt>
                            <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{formatItemType(item.item_type)}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Metal</dt>
                            <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{item.metal_type?.name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Purity</dt>
                            <dd className="font-medium text-slate-900 dark:text-white mt-0.5">{item.purity?.name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Gross Weight</dt>
                            <dd className="font-mono font-medium text-slate-900 dark:text-white mt-0.5">{parseFloat(item.gross_weight_grams).toFixed(3)} g</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Net Weight</dt>
                            <dd className="font-mono font-semibold text-gold mt-0.5">{netWeight.toFixed(3)} g</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Stone Deduction</dt>
                            <dd className="font-mono text-slate-900 dark:text-white mt-0.5">{parseFloat(item.stone_weight_grams).toFixed(3)} g</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Cutting Loss</dt>
                            <dd className="font-mono text-slate-900 dark:text-white mt-0.5">{parseFloat(item.cutting_loss_grams).toFixed(3)} g</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Purchase Price</dt>
                            <dd className="font-mono font-semibold text-slate-900 dark:text-white mt-0.5">PKR {purchasePrice.toLocaleString('en-PK', { minimumFractionDigits: 2 })}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500 dark:text-slate-400">Date Received</dt>
                            <dd className="text-slate-900 dark:text-white mt-0.5">{formatDate(item.date_received)}</dd>
                        </div>
                        {item.source_party && (
                            <div>
                                <dt className="text-slate-500 dark:text-slate-400">Source Party</dt>
                                <dd className="text-slate-900 dark:text-white mt-0.5">{item.source_party.name}</dd>
                            </div>
                        )}
                    </dl>

                    <div className="mt-6 flex gap-3">
                        <Link
                            href={route('items.edit', item.id)}
                            className="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-gold transition-colors"
                        >
                            Edit Item →
                        </Link>
                        <Link
                            href={route('items.create')}
                            className="text-sm font-medium text-emerald-600 hover:text-emerald-500 transition-colors"
                        >
                            + Add Another Item
                        </Link>
                    </div>
                </div>
            </div>

            {/* Hidden elements for high-res PNG downloads */}
            <div style={{ display: 'none' }}>
                {/* 500x500 QR Code Canvas */}
                <QRCodeCanvas ref={qrCanvasRef} value={qrValue} size={500} level="M" />
                {/* Barcode SVG Wrapper */}
                <div ref={barcodeWrapperRef}>
                    <Barcode
                        value={item.item_code}
                        width={2.5}
                        height={80}
                        fontSize={18}
                        margin={10}
                        displayValue={true}
                        background="#ffffff"
                        lineColor="#000000"
                        format="CODE128"
                    />
                </div>
            </div>

            {/* ─── Print-only styles ─── */}
            <style>{`
                @media print {
                    body { margin: 0; padding: 0; background: white; }
                    .no-print { display: none !important; }
                    .print-only { display: block !important; }
                    @page { size: A7 landscape; margin: 4mm; }
                }
                .print-only { display: none; }
                .tag-card {
                    background: white;
                    border: 1.5px solid #e5e7eb;
                    border-radius: 16px;
                    width: 100%;
                    max-width: 520px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
                    overflow: hidden;
                }
                @media print {
                    .tag-card {
                        border: 1px solid #ccc;
                        border-radius: 8px;
                        box-shadow: none;
                        max-width: 100%;
                        width: 100%;
                    }
                }
            `}</style>

            {/* Print-only standalone tag (outside screen container) */}
            <div className="print-only">
                <TagCard item={item} qrValue={qrValue} netWeight={netWeight} purchasePrice={purchasePrice} />
            </div>
        </>
    );
}

/* ─── Reusable tag card component ─── */
function TagCard({ item, qrValue, netWeight, purchasePrice }: {
    item: Item;
    qrValue: string;
    netWeight: number;
    purchasePrice: number;
}) {
    return (
        <div style={{ fontFamily: 'Inter, system-ui, sans-serif', background: 'white', color: '#111827' }}>
            {/* Header strip */}
            <div style={{
                background: 'linear-gradient(135deg, #1a1a1a 0%, #2d2005 100%)',
                padding: '12px 18px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
            }}>
                <div>
                    <div style={{ color: '#f5c842', fontWeight: 700, fontSize: '15px', letterSpacing: '0.05em' }}>
                        ZAR &amp; NOOR
                    </div>
                    <div style={{ color: '#9ca3af', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase' }}>
                        Jewelry ERP
                    </div>
                </div>
                <div style={{
                    background: '#f5c842',
                    color: '#111',
                    fontSize: '10px',
                    fontWeight: 700,
                    padding: '3px 10px',
                    borderRadius: '20px',
                    textTransform: 'uppercase',
                    letterSpacing: '0.05em',
                }}>
                    {item.status === 'in_stock' ? 'In Stock' : item.status}
                </div>
            </div>

            {/* Body */}
            <div style={{ padding: '16px 18px', display: 'flex', gap: '16px', alignItems: 'flex-start' }}>
                {/* Left: info */}
                <div style={{ flex: 1, minWidth: 0 }}>
                    {/* Item code */}
                    <div style={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '17px', color: '#111', letterSpacing: '0.04em', marginBottom: '10px' }}>
                        {item.item_code}
                    </div>

                    {/* Details grid */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px 12px', fontSize: '12px' }}>
                        <InfoRow label="Type" value={item.item_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())} />
                        <InfoRow label="Metal" value={item.metal_type?.name ?? '—'} />
                        <InfoRow label="Purity" value={item.purity?.name ?? '—'} />
                        <InfoRow label="Date" value={item.date_received ? new Date(item.date_received).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'} />
                    </div>

                    {/* Weight highlight */}
                    <div style={{
                        marginTop: '12px',
                        background: '#fffbeb',
                        border: '1px solid #fde68a',
                        borderRadius: '8px',
                        padding: '8px 12px',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                    }}>
                        <div>
                            <div style={{ fontSize: '9px', color: '#92400e', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em' }}>Net Weight</div>
                            <div style={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '18px', color: '#b45309' }}>
                                {netWeight.toFixed(3)} g
                            </div>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                            <div style={{ fontSize: '9px', color: '#374151', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em' }}>Price</div>
                            <div style={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '13px', color: '#111' }}>
                                PKR {purchasePrice.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right: QR + Barcode */}
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px', flexShrink: 0 }}>
                    {/* QR Code */}
                    <div style={{ padding: '6px', background: 'white', border: '1px solid #e5e7eb', borderRadius: '8px' }}>
                        <QRCodeSVG
                            value={qrValue}
                            size={90}
                            bgColor="#ffffff"
                            fgColor="#111827"
                            level="M"
                        />
                    </div>
                    <div style={{ fontSize: '8px', color: '#6b7280', textAlign: 'center' }}>Scan for details</div>

                    {/* Barcode */}
                    <div style={{ background: 'white', padding: '4px 6px', border: '1px solid #e5e7eb', borderRadius: '6px' }}>
                        <Barcode
                            value={item.item_code}
                            width={1.2}
                            height={36}
                            fontSize={9}
                            margin={0}
                            displayValue={true}
                            background="#ffffff"
                            lineColor="#111827"
                            format="CODE128"
                        />
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div style={{
                borderTop: '1px solid #f3f4f6',
                padding: '8px 18px',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                fontSize: '9px',
                color: '#9ca3af',
            }}>
                <span>ID #{item.id}</span>
                <span>Gross: {parseFloat(item.gross_weight_grams).toFixed(3)}g &nbsp;|&nbsp; Stone: {parseFloat(item.stone_weight_grams).toFixed(3)}g &nbsp;|&nbsp; Cut: {parseFloat(item.cutting_loss_grams).toFixed(3)}g</span>
            </div>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div style={{ color: '#6b7280', fontSize: '9px', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{label}</div>
            <div style={{ color: '#111827', fontWeight: 600, marginTop: '1px' }}>{value}</div>
        </div>
    );
}
