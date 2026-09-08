import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { ArrowLeft, Printer } from 'lucide-react';
import InvoiceSheet from '@/components/invoice-sheet';
import '../../../css/invoices.css';

export default function InvoiceShow({ invoice, subtotal, exchangeLines = [], shop }: any) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Invoices', href: '/invoices' },
        { title: invoice.invoice_number, href: `/invoices/${invoice.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={invoice.invoice_number} />

            <div className="inv-page">
                <Link href="/invoices" className="inv-back"><ArrowLeft size={15} /> Back to Invoices</Link>

                <div className="inv-actions">
                    <button type="button" className="inv-btn inv-btn-gold" onClick={() => window.print()}>
                        <Printer size={15} /> Print
                    </button>
                </div>

                <InvoiceSheet invoice={invoice} subtotal={subtotal} exchangeLines={exchangeLines} shop={shop} />
            </div>
        </AppLayout>
    );
}
