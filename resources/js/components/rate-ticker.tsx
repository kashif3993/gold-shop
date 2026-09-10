import { useEffect, useState } from 'react';
import axios from 'axios';
import '../../css/rate-ticker.css';

interface TickerRate {
    metal: string;
    purity: string;
    per_gram: number;
    per_tola: number;
    source: string | null;
    is_stale: boolean;
}

const money = (n: number) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { maximumFractionDigits: 0 }).format(Math.round(Number(n) || 0));

// "Silver" + "835 Silver" → "835 Silver"; "Gold" + "22K" → "Gold 22K"
const rateLabel = (metal: string, purity: string) =>
    purity.toLowerCase().includes(metal.toLowerCase()) ? purity : `${metal} ${purity}`;

/**
 * Sliding marquee of the current gold/silver rates (per tola, with per-gram
 * underneath). Polls every 2 minutes, pauses on hover, freezes on
 * prefers-reduced-motion. Renders nothing until it has data.
 */
export default function RateTicker({ className = '' }: { className?: string }) {
    const [rates, setRates] = useState<TickerRate[]>([]);
    const [feedStale, setFeedStale] = useState(false);

    useEffect(() => {
        let alive = true;

        const load = () =>
            axios
                .get('/api/v1/rates/current')
                .then(({ data }) => {
                    if (!alive) return;
                    setRates(Array.isArray(data.rates) ? data.rates : []);
                    setFeedStale(Boolean(data.feed_stale));
                })
                .catch(() => {
                    /* keep whatever we have */
                });

        load();
        const id = window.setInterval(load, 120_000);
        return () => {
            alive = false;
            window.clearInterval(id);
        };
    }, []);

    if (rates.length === 0) return null;

    const cells = rates.map((r, i) => (
        <span className="rtk-item" key={`${r.purity}-${i}`}>
            <span className="rtk-label">{rateLabel(r.metal, r.purity)}</span>
            <span className="rtk-val">
                {money(r.per_tola)}
                <span className="rtk-unit">/tola</span>
            </span>
            <span className="rtk-sub">{money(r.per_gram)}/g</span>
            {r.is_stale && <span className="rtk-stale">stale</span>}
        </span>
    ));

    return (
        <div className={`rtk${feedStale ? ' rtk-is-stale' : ''}${className ? ' ' + className : ''}`} aria-label="Current metal rates">
            <span className="rtk-tag">{feedStale ? 'Rate · stale' : 'Live rate'}</span>
            <div className="rtk-viewport">
                {/* duplicated once for a seamless -50% loop */}
                <div className="rtk-track">
                    {cells}
                    {cells}
                </div>
            </div>
        </div>
    );
}
