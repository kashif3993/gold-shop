import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import '../../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }, { title: 'Payment', href: '/admin/payment' }];

interface Bank {
    qr_image: string | null;
    account_title: string | null;
    bank_name: string | null;
}

export default function PaymentSettings({ bank }: { bank: Bank }) {
    const { flash } = usePage().props as any;
    const [preview, setPreview] = useState<string | null>(bank.qr_image);

    const form = useForm({
        account_title: bank.account_title || '',
        bank_name: bank.bank_name || '',
        qr_image: null as File | null,
    });

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        form.setData('qr_image', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = () => setPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/payment', { preserveScroll: true, forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Settings" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Bank QR payment</h1>
                    <p className="adm-desc">Upload the QR your bank, JazzCash, or Easypaisa app already shows for this account — it's the same image shown at every checkout from now on. Done once, or again only if the account changes.</p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <div className="adm-card">
                    <h2 className="adm-card-title">Account details</h2>
                    <p className="adm-card-sub">Shown to the customer at checkout, next to the QR.</p>

                    <form onSubmit={submit}>
                        <div style={{ display: 'flex', gap: '1.5rem', flexWrap: 'wrap', marginBottom: '1.25rem' }}>
                            <div style={{
                                width: 160, height: 160, flexShrink: 0, borderRadius: 10, border: '1px solid var(--adm-line)',
                                background: 'var(--adm-surface-alt)', display: 'flex', alignItems: 'center', justifyContent: 'center',
                                overflow: 'hidden',
                            }}>
                                {preview
                                    ? <img src={preview} alt="Bank QR preview" style={{ maxWidth: '100%', maxHeight: '100%' }} />
                                    : <span className="adm-muted" style={{ fontSize: '.8rem', textAlign: 'center', padding: '0 .5rem' }}>No QR uploaded yet</span>}
                            </div>

                            <div style={{ flex: 1, minWidth: 220, display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                                <div className="adm-field">
                                    <label className="adm-label" htmlFor="qr_image">Bank QR image</label>
                                    <input id="qr_image" type="file" accept="image/*" onChange={onFileChange} />
                                    {form.errors.qr_image && <span className="adm-error">{form.errors.qr_image}</span>}
                                </div>
                                <div className="adm-field">
                                    <label className="adm-label" htmlFor="account_title">Account title</label>
                                    <input
                                        id="account_title" className="adm-input" style={{ width: '100%' }}
                                        value={form.data.account_title} onChange={e => form.setData('account_title', e.target.value)}
                                        placeholder="e.g. Zar &amp; Noor Jewellers"
                                    />
                                    {form.errors.account_title && <span className="adm-error">{form.errors.account_title}</span>}
                                </div>
                                <div className="adm-field">
                                    <label className="adm-label" htmlFor="bank_name">Bank / wallet name</label>
                                    <input
                                        id="bank_name" className="adm-input" style={{ width: '100%' }}
                                        value={form.data.bank_name} onChange={e => form.setData('bank_name', e.target.value)}
                                        placeholder="e.g. Meezan Bank"
                                    />
                                    {form.errors.bank_name && <span className="adm-error">{form.errors.bank_name}</span>}
                                </div>
                            </div>
                        </div>

                        <button type="submit" className="adm-btn adm-btn-gold" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save payment settings'}
                        </button>
                    </form>
                </div>

                <p className="adm-card-sub" style={{ maxWidth: '60ch' }}>
                    This is a static account QR, not a per-sale payment link — the amount and a reference number are shown next to it at checkout, and the customer includes that reference in their own bank transfer. Confirming that a payment actually arrived stays admin-only, from the POS screen.
                </p>
            </div>
        </AppLayout>
    );
}
