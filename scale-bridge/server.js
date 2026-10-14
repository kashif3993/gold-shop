/**
 * Scale bridge — reads the digital weighing scale over USB/serial and
 * serves the latest weight as JSON on localhost, so the Item Entry page
 * (running in the browser, which has no serial port access) can poll it.
 *
 * Run: node server.js
 * Config (env vars, all optional):
 *   SCALE_COM_PORT   default "COM3"
 *   SCALE_BAUD_RATE  default 2400
 *   BRIDGE_HTTP_PORT default 8787
 */
const http = require('http');
const { SerialPort } = require('serialport');

const COM_PORT = process.env.SCALE_COM_PORT || 'COM3';
const BAUD_RATE = parseInt(process.env.SCALE_BAUD_RATE || '2400', 10);
const HTTP_PORT = parseInt(process.env.BRIDGE_HTTP_PORT || '8787', 10);

// Matches a line like "-    0.04 g" or "  1.27 g".
const WEIGHT_LINE = /([+-]?)\s*([0-9]+\.[0-9]+)\s*g/i;

let port = null;
let portOpen = false;
let portOpenedAt = 0;
let buffer = '';
let lastReading = null; // { grams, raw, at }
let reconnectTimer = null;

function scheduleReconnect() {
    if (reconnectTimer) return;
    reconnectTimer = setTimeout(() => {
        reconnectTimer = null;
        openPort();
    }, 2000);
}

function openPort() {
    if (port) {
        try { port.removeAllListeners(); } catch {}
    }

    port = new SerialPort({ path: COM_PORT, baudRate: BAUD_RATE, dataBits: 8, parity: 'none', stopBits: 1 }, (err) => {
        if (err) {
            portOpen = false;
            console.error(`[scale] could not open ${COM_PORT}: ${err.message}`);
            scheduleReconnect();
        }
    });

    port.on('open', () => {
        portOpen = true;
        portOpenedAt = Date.now();
        buffer = '';
        console.log(`[scale] connected on ${COM_PORT} at ${BAUD_RATE} baud`);
    });

    port.on('data', (chunk) => {
        buffer += chunk.toString('latin1');
        const lines = buffer.split(/\r\n|\n/);
        buffer = lines.pop() || ''; // keep the trailing partial line, if any

        for (const line of lines) {
            const m = line.match(WEIGHT_LINE);
            if (m) {
                const grams = parseFloat((m[1] === '-' ? '-' : '') + m[2]);
                lastReading = { grams, raw: line.trim(), at: Date.now() };
            }
        }
    });

    port.on('error', (err) => {
        console.error(`[scale] serial error: ${err.message}`);
        portOpen = false;
        scheduleReconnect();
    });

    port.on('close', () => {
        portOpen = false;
        console.log('[scale] port closed, will retry');
        scheduleReconnect();
    });
}

openPort();

// Watchdog: the scale streams a reading every ~50-100ms whenever the cable
// is genuinely connected, so any longer silence means the connection broke.
// Unplugging and replugging the USB cable doesn't reliably fire a Windows
// 'error'/'close' event on an already-open handle — it can keep reporting
// "open" while producing nothing — so this forces a fresh reopen instead of
// waiting for an event that may never come.
const SILENCE_LIMIT_MS = 8000;
setInterval(() => {
    if (!portOpen || reconnectTimer) return;
    const sinceLastData = Date.now() - (lastReading ? lastReading.at : portOpenedAt);
    if (sinceLastData > SILENCE_LIMIT_MS) {
        console.log(`[scale] no data for ${Math.round(sinceLastData / 1000)}s — forcing a reconnect`);
        // The 'close' handler registered in openPort() picks up the reconnect
        // from here; this is just a safety net in case close() itself errors
        // out silently without ever emitting that event.
        port.close(() => { if (portOpen) { portOpen = false; scheduleReconnect(); } });
    }
}, 2000);

const server = http.createServer((req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    if (req.url === '/weight') {
        const ageMs = lastReading ? Date.now() - lastReading.at : null;
        // A reading older than 5s means the scale stopped sending data even
        // though the port itself may still show "open" (the exact failure
        // mode seen during setup — Windows reports OK while reads stall).
        const fresh = ageMs !== null && ageMs < 5000;

        res.setHeader('Content-Type', 'application/json');
        res.writeHead(200);
        res.end(JSON.stringify({
            ok: portOpen && fresh,
            port_open: portOpen,
            grams: lastReading ? lastReading.grams : null,
            raw: lastReading ? lastReading.raw : null,
            age_ms: ageMs,
        }));
        return;
    }

    res.writeHead(404);
    res.end();
});

server.listen(HTTP_PORT, '127.0.0.1', () => {
    console.log(`[scale] bridge listening on http://127.0.0.1:${HTTP_PORT}/weight`);
});
