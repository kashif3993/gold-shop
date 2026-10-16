import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Clock, Lock, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface BankInfo {
    qr_image: string | null;
    account_title: string | null;
    bank_name: string | null;
}

interface Payment {
    reference: string;
    amount: number;
    status: 'pending' | 'confirmed' | 'expired';
    expires_at: string;
    confirmed_at: string | null;
    bank_txn_id: string | null;
    confirmed_by: string | null;
    invoice_number: string | null;
    bank: BankInfo;
}

const formatPKR = (amount: number) => 'Rs ' + new Intl.NumberFormat('en-PK', { maximumFractionDigits: 0 }).format(amount);

export default function BankQrPaymentModal({
    startPayload,
    onClose,
    onCompleted,
}: {
    startPayload: Record<string, any>;
    onClose: () => void;
    onCompleted: (invoiceNumber: string) => void;
}) {
    const { auth } = usePage().props as any;
    const canConfirm = auth?.can_confirm_bank_qr === true;

    const [payment, setPayment] = useState<Payment | null>(null);
    const [stage, setStage] = useState<'starting' | 'showing' | 'confirming' | 'error'>('starting');
    const [error, setError] = useState('');
    const [txnId, setTxnId] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [scanNote, setScanNote] = useState('');
    const [slipPreview, setSlipPreview] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const pollRef = useRef<number | null>(null);

    // Release the blob URL once it's no longer shown, and always on unmount.
    useEffect(() => {
        return () => {
            if (slipPreview) URL.revokeObjectURL(slipPreview);
        };
    }, [slipPreview]);

    const start = () => {
        setStage('starting');
        setError('');
        axios
            .post('/api/v1/pos/bank-qr', startPayload)
            .then(({ data }) => {
                setPayment(data.payment);
                setStage('showing');
            })
            .catch((err) => {
                setError(err.response?.data?.message || 'Could not start the payment. Please try again.');
                setStage('error');
            });
    };

    useEffect(() => {
        start();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Poll status while pending — picks up an admin confirming from elsewhere,
    // and the server-side expiry once the window closes.
    useEffect(() => {
        if (!payment || payment.status !== 'pending') return;

        pollRef.current = window.setInterval(() => {
            axios
                .get(`/api/v1/pos/bank-qr/${payment.reference}`)
                .then(({ data }) => setPayment(data.payment))
                .catch(() => {
                    /* keep showing what we have */
                });
        }, 5000);

        return () => {
            if (pollRef.current) window.clearInterval(pollRef.current);
        };
    }, [payment?.reference, payment?.status]);

    const scanSlip = (file: File) => {
        if (!payment) return;
        setScanning(true);
        setScanNote('');
        setError('');
        // A local preview of exactly what's being uploaded — this is a
        // client-side blob URL, never sent anywhere; the actual stored copy
        // is whatever the server saved from this same file.
        setSlipPreview(URL.createObjectURL(file));
        const form = new FormData();
        form.append('slip', file);
        axios
            .post(`/admin/pos/bank-qr/${payment.reference}/scan-slip`, form)
            .then(({ data }) => {
                if (data.transaction_id) {
                    setTxnId(data.transaction_id);
                    setScanNote(`Detected from the slip: ${data.transaction_id} — check it, then confirm.`);
                } else {
                    setScanNote("Couldn't find a transaction ID on that image — please type it in below.");
                }
            })
            .catch((err) => setError(err.response?.data?.message || 'Could not scan that image.'))
            .finally(() => setScanning(false));
    };

    const submitConfirm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!payment || !txnId.trim()) return;
        setConfirming(true);
        setError('');
        axios
            .post(`/admin/pos/bank-qr/${payment.reference}/confirm`, { bank_txn_id: txnId.trim() })
            .then(({ data }) => setPayment(data.payment))
            .catch((err) => setError(err.response?.data?.message || 'Could not confirm this payment.'))
            .finally(() => setConfirming(false));
    };

    const restart = () => {
        setPayment(null);
        setTxnId('');
        setSlipPreview(null);
        setScanNote('');
        start();
    };

    if (!payment && stage === 'starting') {
        return (
            <ModalShell onClose={onClose} title="Bank QR payment">
                <div className="py-10 text-center text-sm text-gray-500">Opening payment attempt…</div>
            </ModalShell>
        );
    }

    if (stage === 'error' || !payment) {
        return (
            <ModalShell onClose={onClose} title="Bank QR payment">
                <div className="py-6 text-center">
                    <p className="mb-4 text-sm text-red-600">{error || 'Something went wrong.'}</p>
                    <button onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium">
                        Close
                    </button>
                </div>
            </ModalShell>
        );
    }

    if (payment.status === 'confirmed') {
        return (
            <ModalShell onClose={onClose} title="Payment confirmed">
                <div className="flex flex-col items-center gap-3 py-4 text-center">
                    <div className="flex h-14 w-14 items-center justify-center rounded-full border border-amber-200 bg-amber-50">
                        <CheckCircle2 className="h-7 w-7 text-[#b38a36]" />
                    </div>
                    <h3 className="text-base font-bold text-gray-900">Payment confirmed</h3>
                    <div className="w-full space-y-1.5 rounded-lg border border-gray-200 bg-gray-50 p-3 text-left text-sm">
                        <Row label="Invoice" value={payment.invoice_number || '—'} />
                        <Row label="Amount" value={formatPKR(payment.amount)} />
                        <Row label="Bank txn ID" value={payment.bank_txn_id || '—'} />
                        <Row label="Confirmed by" value={payment.confirmed_by || '—'} />
                    </div>
                    <button
                        onClick={() => onCompleted(payment.invoice_number || '')}
                        className="mt-1 w-full rounded-lg bg-[#b38a36] py-2.5 text-sm font-bold text-white transition hover:bg-[#a17a2d]"
                    >
                        Continue
                    </button>
                </div>
            </ModalShell>
        );
    }

    if (payment.status === 'expired') {
        return (
            <ModalShell onClose={onClose} title="Bank QR payment">
                <div className="flex flex-col items-center gap-3 py-4 text-center">
                    <div className="flex h-14 w-14 items-center justify-center rounded-full border border-red-200 bg-red-50">
                        <Clock className="h-7 w-7 text-red-500" />
                    </div>
                    <h3 className="text-base font-bold text-gray-900">Payment window expired</h3>
                    <p className="text-sm text-gray-500">
                        No confirmation came in within the time limit. That reference is retired — start again to show the QR with a fresh one.
                    </p>
                    <button
                        onClick={restart}
                        className="mt-1 w-full rounded-lg bg-[#b38a36] py-2.5 text-sm font-bold text-white transition hover:bg-[#a17a2d]"
                    >
                        Start a new payment attempt
                    </button>
                </div>
            </ModalShell>
        );
    }

    // status === 'pending'
    return (
        <ModalShell onClose={onClose} title="Scan to pay">
            <div className="flex flex-col gap-4">
                <div className="text-xs font-semibold text-gray-500">Show this screen to the customer</div>

                <div className="flex items-center gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div className="flex h-28 w-28 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-white">
                        {payment.bank.qr_image ? (
                            <img src={payment.bank.qr_image} alt="Bank QR" className="max-h-full max-w-full" />
                        ) : (
                            <span className="px-1 text-center text-[10px] text-gray-400">No QR uploaded — ask an admin to set one up</span>
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-xl font-extrabold text-gray-900 tabular-nums">{formatPKR(payment.amount)}</div>
                        <div className="mt-0.5 text-xs text-gray-500">
                            {payment.bank.account_title} · {payment.bank.bank_name}
                        </div>
                        <div className="mt-2">
                            <div className="text-[10px] font-bold tracking-wide text-gray-400 uppercase">
                                Reference — must be in the transfer note
                            </div>
                            <span className="mt-1 inline-block rounded border border-dashed border-gray-300 bg-white px-2 py-1 font-mono text-sm font-semibold">
                                {payment.reference}
                            </span>
                        </div>
                    </div>
                </div>

                {stage !== 'confirming' && (
                    <p className="text-xs text-gray-400">
                        Once the customer has sent the payment, the cashier checks their own banking app for the transfer, then continues below.
                    </p>
                )}

                {stage !== 'confirming' ? (
                    <div className="flex gap-2">
                        <button
                            onClick={() => setStage('confirming')}
                            className="flex-1 rounded-lg bg-[#b38a36] py-2.5 text-sm font-bold text-white transition hover:bg-[#a17a2d]"
                        >
                            I&rsquo;ve received the payment →
                        </button>
                        <button onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium">
                            Cancel
                        </button>
                    </div>
                ) : canConfirm ? (
                    <form onSubmit={submitConfirm} className="flex flex-col gap-3">
                        <div>
                            <label className="mb-1 block text-[10px] font-bold tracking-wide text-gray-500 uppercase">
                                Bank transaction ID (from your app)
                            </label>
                            <input
                                autoFocus
                                type="text"
                                value={txnId}
                                onChange={(e) => setTxnId(e.target.value)}
                                placeholder="e.g. 482917"
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm"
                            />
                        </div>

                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => {
                                const f = e.target.files?.[0];
                                if (f) scanSlip(f);
                                e.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            disabled={scanning}
                            onClick={() => fileInputRef.current?.click()}
                            className="flex items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-300 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50"
                        >
                            <Upload className="h-3.5 w-3.5" />
                            {scanning ? 'Scanning slip…' : 'Or upload the bank slip to auto-fill the ID'}
                        </button>
                        {slipPreview && (
                            <div className="flex items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <img
                                    src={slipPreview}
                                    alt="Uploaded slip"
                                    className="h-14 w-14 flex-shrink-0 rounded border border-gray-200 object-cover"
                                />
                                <p className="text-[11px] leading-snug text-gray-500">{scanNote || 'Scanning…'}</p>
                            </div>
                        )}

                        {error && <p className="text-xs text-red-600">{error}</p>}
                        <p className="text-[11px] text-gray-400">
                            Confirming locks this invoice as paid immediately — it can&rsquo;t be undone or repeated.
                        </p>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={confirming || !txnId.trim()}
                                className="flex-1 rounded-lg bg-[#b38a36] py-2.5 text-sm font-bold text-white transition hover:bg-[#a17a2d] disabled:opacity-50"
                            >
                                {confirming ? 'Confirming…' : 'Confirm payment received'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setStage('showing')}
                                className="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium"
                            >
                                Back
                            </button>
                        </div>
                    </form>
                ) : (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-center">
                        <Lock className="h-6 w-6 text-gray-400" />
                        <h4 className="text-sm font-bold text-gray-800">Only an admin (or an authorized manager) can confirm this</h4>
                        <p className="max-w-[26ch] text-xs text-gray-500">
                            The customer has been shown the pay screen. Ask an admin, or a manager with this permission, to sign in and confirm the
                            bank transaction to complete this sale.
                        </p>
                        <p className="text-[11px] text-gray-400">This screen updates automatically once it's confirmed.</p>
                    </div>
                )}
            </div>
        </ModalShell>
    );
}

function ModalShell({ title, onClose, children }: { title: string; onClose: () => void; children: React.ReactNode }) {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" onMouseDown={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-sm font-bold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-700" aria-label="Close">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-gray-500">{label}</span>
            <span className="text-right font-mono font-semibold text-gray-900">{value}</span>
        </div>
    );
}
