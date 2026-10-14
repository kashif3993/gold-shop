import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { ArrowLeft, Printer, Plus } from 'lucide-react';
import ItemEntryPrintSlip from '@/components/item-entry-print-slip';
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
            <div className="no-print min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-900 dark:to-slate-800 px-4 py-8 flex flex-col items-center gap-6">
                <div className="w-full max-w-md flex items-center justify-between flex-wrap gap-3">
                    <Link
                        href={route('inventory.index')}
                        className="flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-gold transition-colors font-medium"
                    >
                        <ArrowLeft size={18} />
                        Back to Inventory
                    </Link>
                    <button
                        onClick={() => window.print()}
                        className="flex items-center gap-2 bg-gold hover:bg-gold/90 text-white font-semibold px-5 py-2.5 rounded-xl shadow-lg transition-all hover:shadow-gold/30"
                    >
                        <Printer size={18} />
                        Print Again
                    </button>
                </div>

                <div className="w-full max-w-md bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-xl px-5 py-3 text-emerald-800 dark:text-emerald-300 text-sm font-medium text-center">
                    Item <strong>{item.item_code}</strong> saved. Sending the weight &amp; value slip to your printer...
                </div>

                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
                    <ItemEntryPrintSlip item={item} shop={shop} printerType={printerType} printDimensions={printDimensions} />
                </div>

                <Link
                    href={route('items.create')}
                    className="flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-500 transition-colors"
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
