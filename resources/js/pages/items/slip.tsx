import ItemEntryPrintSlip from '@/components/item-entry-print-slip';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, Printer } from 'lucide-react';
import { useEffect } from 'react';
import '../../../css/item-entry-print.css';

interface Shop {
    name: string;
    address: string | null;
    phone: string | null;
    logo: string | null;
}

interface PrintDimensions {
    widthMm: number;
    heightMm: number;
    paddingMm: number;
}

interface SlipProps {
    item: any;
    shop: Shop;
    printerType: 'regular' | 'thermal';
    printDimensions: PrintDimensions;
}

export default function ItemSlip({ item, shop, printerType, printDimensions }: SlipProps) {
    // This page exists to be printed — fire the dialog as soon as it's ready,
    // same as handing a printed slip straight to the shopkeeper.
    useEffect(() => {
        const t = setTimeout(() => window.print(), 150);
        return () => clearTimeout(t);
    }, []);

    return (
        <>
            <Head title={`Slip — ${item.item_code}`} />

            {/* ─── Screen controls + preview (hidden on print) ─── */}
            <div className="no-print flex min-h-screen flex-col items-center gap-6 bg-gradient-to-br from-slate-100 to-slate-200 px-4 py-8 dark:from-slate-900 dark:to-slate-800">
                <div className="flex w-full max-w-md flex-wrap items-center justify-between gap-3">
                    <Link
                        href={route('inventory.index')}
                        className="hover:text-gold flex items-center gap-2 font-medium text-slate-600 transition-colors dark:text-slate-400"
                    >
                        <ArrowLeft size={18} />
                        Back to Inventory
                    </Link>
                    <button
                        onClick={() => window.print()}
                        className="bg-gold hover:bg-gold/90 hover:shadow-gold/30 flex items-center gap-2 rounded-xl px-5 py-2.5 font-semibold text-white shadow-lg transition-all"
                    >
                        <Printer size={18} />
                        Print Again
                    </button>
                </div>

                <div className="w-full max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-center text-sm font-medium text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    Item <strong>{item.item_code}</strong> saved. Sending the weight &amp; value slip to your printer...
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <ItemEntryPrintSlip item={item} shop={shop} printerType={printerType} printDimensions={printDimensions} />
                </div>

                <Link
                    href={route('items.create')}
                    className="flex items-center gap-1.5 text-sm font-medium text-emerald-600 transition-colors hover:text-emerald-500"
                >
                    <Plus size={15} />
                    Add Another Item
                </Link>
            </div>

            {/* ─── Print-only standalone slip (outside the screen container, so
                hiding .no-print under print never hides this too) ─── */}
            <div className="print-only">
                <ItemEntryPrintSlip item={item} shop={shop} printerType={printerType} printDimensions={printDimensions} />
            </div>

            <style>{`
                .print-only { display: none; }
                @media print {
                    .no-print { display: none !important; }
                    .print-only { display: block !important; }
                }
            `}</style>
        </>
    );
}
