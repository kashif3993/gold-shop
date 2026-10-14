import { useEffect, useState } from 'react';

// Local bridge started by scale-bridge/server.js — reads the USB scale over
// serial and exposes the latest reading here, since the browser itself has
// no serial port access. See scale-bridge/README.md to start it.
const SCALE_BRIDGE_URL = 'http://127.0.0.1:8787/weight';

export function useScaleReading() {
    const [scale, setScale] = useState<{ ok: boolean; grams: number | null }>({ ok: false, grams: null });

    useEffect(() => {
        let cancelled = false;

        const poll = async () => {
            try {
                const res = await fetch(SCALE_BRIDGE_URL, { signal: AbortSignal.timeout(1500) });
                const json = await res.json();
                if (!cancelled) setScale({ ok: !!json.ok, grams: typeof json.grams === 'number' ? json.grams : null });
            } catch {
                if (!cancelled) setScale({ ok: false, grams: null });
            }
        };

        poll();
        const id = setInterval(poll, 1000);
        return () => { cancelled = true; clearInterval(id); };
    }, []);

    return scale;
}
