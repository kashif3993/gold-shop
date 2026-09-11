import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * A client-only id for React keys / temp cart rows — never sent to the server
 * as a real identifier. `crypto.randomUUID` only exists in secure contexts
 * (HTTPS, or localhost); over plain http (e.g. a local *.test domain) or in
 * older browsers it's undefined, so fall back to `crypto.getRandomValues`
 * and finally to Math.random rather than crashing.
 */
export function genId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }
    return `id-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}
