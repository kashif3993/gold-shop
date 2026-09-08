import { Link } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';

interface PartyType {
    id: number;
    name: string;
}

export interface PartyFormData {
    party_type_id: number | string;
    name: string;
    phone: string;
    address: string;
    notes: string;
    is_active: boolean;
    [key: string]: any;
}

interface PartyFormProps {
    data: PartyFormData;
    setData: (key: string, value: any) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    partyTypes: PartyType[];
    onSubmit: (e: React.FormEvent) => void;
    submitLabel: string;
    showActiveToggle?: boolean;
    /** When provided, the Cancel control calls this instead of linking back to /parties (used in the modal). */
    onCancel?: () => void;
}

export default function PartyForm({
    data, setData, errors, processing, partyTypes, onSubmit, submitLabel, showActiveToggle = false, onCancel,
}: PartyFormProps) {
    return (
        <form onSubmit={onSubmit} style={{ maxWidth: '720px' }}>
            <div className="pty-card" style={{ marginBottom: '1.5rem' }}>
                <h2 className="pty-card-title">Party Details</h2>

                <div className="pty-form-grid">
                    <div className="pty-form-group">
                        <label className="pty-form-label">Name <span className="pty-req">*</span></label>
                        <input
                            type="text"
                            className="pty-input"
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            placeholder="Full name or business name"
                        />
                        {errors.name && <div className="pty-error">{errors.name}</div>}
                    </div>

                    <div className="pty-form-group">
                        <label className="pty-form-label">Party Type <span className="pty-req">*</span></label>
                        <select
                            className="pty-select"
                            value={String(data.party_type_id)}
                            onChange={e => setData('party_type_id', e.target.value)}
                        >
                            <option value="">Select type</option>
                            {partyTypes.map(t => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                        {errors.party_type_id && <div className="pty-error">{errors.party_type_id}</div>}
                    </div>

                    <div className="pty-form-group">
                        <label className="pty-form-label">Phone</label>
                        <input
                            type="text"
                            className="pty-input"
                            value={data.phone}
                            onChange={e => setData('phone', e.target.value)}
                            placeholder="e.g. 0300 1234567"
                        />
                        {errors.phone && (
                            <div className="pty-error">
                                <AlertCircle size={14} /><span>{errors.phone}</span>
                            </div>
                        )}
                    </div>

                    <div className="pty-form-group">
                        <label className="pty-form-label">Address</label>
                        <input
                            type="text"
                            className="pty-input"
                            value={data.address}
                            onChange={e => setData('address', e.target.value)}
                            placeholder="Shop / home address"
                        />
                        {errors.address && <div className="pty-error">{errors.address}</div>}
                    </div>
                </div>

                <div className="pty-form-group" style={{ marginTop: '1.25rem' }}>
                    <label className="pty-form-label">Notes</label>
                    <textarea
                        className="pty-input"
                        rows={3}
                        value={data.notes}
                        onChange={e => setData('notes', e.target.value)}
                        placeholder="Anything worth remembering about this party"
                        style={{ resize: 'vertical' }}
                    />
                    {errors.notes && <div className="pty-error">{errors.notes}</div>}
                </div>

                {showActiveToggle && (
                    <label className="pty-check">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={e => setData('is_active', e.target.checked)}
                        />
                        Active (unchecked = archived, hidden from pickers)
                    </label>
                )}
            </div>

            <div className="pty-form-actions">
                <button type="submit" className="pty-btn-primary" disabled={processing}>
                    {processing ? 'Saving...' : submitLabel}
                </button>
                {onCancel ? (
                    <button type="button" className="pty-filter-clear" onClick={onCancel}>
                        Cancel
                    </button>
                ) : (
                    <Link href="/parties" className="pty-filter-clear">
                        Cancel
                    </Link>
                )}
            </div>
        </form>
    );
}
