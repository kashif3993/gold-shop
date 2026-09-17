import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { X, Lock, CheckCircle2, Clock } from 'lucide-react';

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

const formatPKR = (amount: number) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { maximumFractionDigits: 0 }).format(amount);

function formatCountdown(expiresAt: string): string {
    const ms = new Date(expiresAt).getTime() - Date.now();
    if (ms <= 0) return '00:00';
    const totalSeconds = Math.floor(ms / 1000);
    const m = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
    const s = (totalSeconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

export default function BankQrPaymentModal({
    startPayload, onClose, onCompleted,
}: {
    startPayload: Record<string, any>;
    onClose: () => void;
    onCompleted: (invoiceNumber: string) => void;
}) {
    const { auth } = usePage().props as any;
    const isAdmin = auth?.user?.role === 'admin';

    const [payment, setPayment] = useState<Payment | null>(null);
    const [stage, setStage] = useState<'starting' | 'showing' | 'confirming' | 'error'>('starting');
    const [error, setError] = useState('');
    const [txnId, setTxnId] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [, forceTick] = useState(0);

    const pollRef = useRef<number | null>(null);

    const start = () => {
        setStage('starting');
        setError('');
        axios.post('/api/v1/pos/bank-qr', startPayload)
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
            axios.get(`/api/v1/pos/bank-qr/${payment.reference}`)
                .then(({ data }) => setPayment(data.payment))
                .catch(() => { /* keep showing what we have */ });
        }, 5000);

        return () => { if (pollRef.current) window.clearInterval(pollRef.current); };
    }, [payment?.reference, payment?.status]);

    // Tick every second just to re-render the countdown.
    useEffect(() => {
        const id = window.setInterval(() => forceTick(t => t + 1), 1000);
        return () => window.clearInterval(id);
    }, []);

    const submitConfirm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!payment || !txnId.trim()) return;
        setConfirming(true);
        setError('');
        axios.post(`/admin/pos/bank-qr/${payment.reference}/confirm`, { bank_txn_id: txnId.trim() })
            .then(({ data }) => setPayment(data.payment))
            .catch((err) => setError(err.response?.data?.message || 'Could not confirm this payment.'))
            .finally(() => setConfirming(false));
    };

    const restart = () => {
        setPayment(null);
        setTxnId('');
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
                    <p className="text-sm text-red-600 mb-4">{error || 'Something went wrong.'}</p>
                    <button onClick={onClose} className="px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium">Close</button>
                </div>
            </ModalShell>
        );
    }

    if (payment.status === 'confirmed') {
        return (
            <ModalShell onClose={onClose} title="Payment confirmed">
                <div className="flex flex-col items-center gap-3 py-4 text-center">
                    <div className="w-14 h-14 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center">
                        <CheckCircle2 className="h-7 w-7 text-[#b38a36]" />
                    </div>
                    <h3 className="text-base font-bold text-gray-900">Payment confirmed</h3>
                    <div className="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm text-left space-y-1.5">
                        <Row label="Invoice" value={payment.invoice_number || '—'} />
                        <Row label="Amount" value={formatPKR(payment.amount)} />
                        <Row label="Bank txn ID" value={payment.bank_txn_id || '—'} />
                        <Row label="Confirmed by" value={payment.confirmed_by || '—'} />
                    </div>
                    <button
                        onClick={() => onCompleted(payment.invoice_number || '')}
                        className="mt-1 w-full py-2.5 rounded-lg bg-[#b38a36] hover:bg-[#a17a2d] text-white text-sm font-bold transition"
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
                    <div className="w-14 h-14 rounded-full bg-red-50 border border-red-200 flex items-center justify-center">
                        <Clock className="h-7 w-7 text-red-500" />
                    </div>
                    <h3 className="text-base font-bold text-gray-900">Payment window expired</h3>
                    <p className="text-sm text-gray-500">No confirmation came in within the time limit. That reference is retired — start again to show the QR with a fresh one.</p>
                    <button onClick={restart} className="mt-1 w-full py-2.5 rounded-lg bg-[#b38a36] hover:bg-[#a17a2d] text-white text-sm font-bold transition">
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
                <div className="flex items-center justify-between text-xs font-semibold text-gray-500">
                    <span>Show this screen to the customer</span>
                    <span className="tabular-nums">Expires in {formatCountdown(payment.expires_at)}</span>
                </div>

                <div className="flex gap-4 items-center bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <div className="w-28 h-28 flex-shrink-0 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                        {payment.bank.qr_image
                            ? <img src={payment.bank.qr_image} alt="Bank QR" className="max-w-full max-h-full" />
                            : <span className="text-[10px] text-gray-400 text-center px-1">No QR uploaded — ask an admin to set one up</span>}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-xl font-extrabold text-gray-900 tabular-nums">{formatPKR(payment.amount)}</div>
                        <div className="text-xs text-gray-500 mt-0.5">{payment.bank.account_title} · {payment.bank.bank_name}</div>
                        <div className="mt-2">
                            <div className="text-[10px] font-bold uppercase tracking-wide text-gray-400">Reference — must be in the transfer note</div>
                            <span className="inline-block mt-1 font-mono text-sm font-semibold bg-white border border-dashed border-gray-300 rounded px-2 py-1">
                                {payment.reference}
                            </span>
                        </div>
                    </div>
                </div>

                {stage !== 'confirming' && (
                    <p className="text-xs text-gray-400">Once the customer has sent the payment, the cashier checks their own banking app for the transfer, then continues below.</p>
                )}

                {stage !== 'confirming' ? (
                    <div className="flex gap-2">
                        <button
                            onClick={() => setStage('confirming')}
                            className="flex-1 py-2.5 rounded-lg bg-[#b38a36] hover:bg-[#a17a2d] text-white text-sm font-bold transition"
                        >
                            I&rsquo;ve received the payment →
                        </button>
                        <button onClick={onClose} className="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-medium">Cancel</button>
                    </div>
                ) : isAdmin ? (
                    <form onSubmit={submitConfirm} className="flex flex-col gap-3">
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">
                                Bank transaction ID (from your app)
                            </label>
                            <input
                                autoFocus
                                type="text"
                                value={txnId}
                                onChange={e => setTxnId(e.target.value)}
                                placeholder="e.g. 482917"
                                className="w-full font-mono text-sm border border-gray-300 rounded-lg px-3 py-2"
                            />
                        </div>
                        {error && <p className="text-xs text-red-600">{error}</p>}
                        <p className="text-[11px] text-gray-400">Confirming locks this invoice as paid immediately — it can&rsquo;t be undone or repeated.</p>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={confirming || !txnId.trim()}
                                className="flex-1 py-2.5 rounded-lg bg-[#b38a36] hover:bg-[#a17a2d] disabled:opacity-50 text-white text-sm font-bold transition"
                            >
                                {confirming ? 'Confirming…' : 'Confirm payment received'}
                            </button>
                            <button type="button" onClick={() => setStage('showing')} className="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-medium">Back</button>
                        </div>
                    </form>
                ) : (
                    <div className="flex flex-col items-center gap-2 text-center bg-gray-50 border border-dashed border-gray-300 rounded-xl p-5">
                        <Lock className="h-6 w-6 text-gray-400" />
                        <h4 className="text-sm font-bold text-gray-800">Only an admin can confirm this</h4>
                        <p className="text-xs text-gray-500 max-w-[26ch]">The customer has been shown the pay screen. Ask an admin to sign in and enter the bank transaction ID to complete this sale.</p>
                        <p className="text-[11px] text-gray-400">This screen updates automatically once it's confirmed.</p>
                    </div>
                )}
            </div>
        </ModalShell>
    );
}

function ModalShell({ title, onClose, children }: { title: string; onClose: () => void; children: React.ReactNode }) {
    return (
        <div className="fixed inset-0 z-[100] bg-black/50 flex items-center justify-center p-4" onMouseDown={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-5" onMouseDown={e => e.stopPropagation()}>
                <div className="flex items-center justify-between mb-4">
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
            <span className="font-mono font-semibold text-gray-900 text-right">{value}</span>
        </div>
    );
}
