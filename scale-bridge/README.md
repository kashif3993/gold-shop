# Scale bridge

The digital weighing scale connects to the laptop over USB/serial. A browser
page can't talk to a serial port directly, so this small local service reads
the scale and exposes the latest weight as JSON on `http://127.0.0.1:8787/weight`.
The Item Entry page (Add/Edit Item) polls that URL once a second and shows a
"Scale: X g — use this" hint when it's connected.

## First-time setup

```
cd scale-bridge
npm install
npm rebuild @serialport/bindings-cpp
```

## Running it

Double-click `start.bat`, or:

```
cd scale-bridge
node server.js
```

Leave the window open while using Item Entry. To start it automatically with
Windows, put a shortcut to `start.bat` in:
`shell:startup` (Win+R → paste → Enter).

## Configuration

Set these as environment variables if the defaults don't match your setup:

| Variable | Default | Meaning |
|---|---|---|
| `SCALE_COM_PORT` | `COM3` | The COM port Windows assigned the scale (Device Manager → Ports) |
| `SCALE_BAUD_RATE` | `2400` | Confirmed working rate for the BBA-600 |
| `BRIDGE_HTTP_PORT` | `8787` | Local port the Item Entry page polls |

## Troubleshooting

- **Scale shows "disconnected" on the page but Device Manager says the port
  is OK** — the scale's serial output has gone quiet even though Windows
  still sees the USB device. Unplug and firmly reseat the cable at both
  ends, then restart `server.js`.
- **"Access denied" opening the port** — another instance of `server.js` (or
  a serial terminal) already has it open. Close it first.
