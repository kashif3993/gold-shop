/**
 * Small dependency-free charts for Reports. Plain SVG/CSS instead of a
 * charting library — the data is a handful of points at most, and this
 * keeps the white/black/gold theme without fighting a library's defaults.
 */
import '../../css/simple-charts.css';

interface LinePoint {
    x: string;
    y: number;
}

interface LineSeries {
    name: string;
    color: string;
    points: LinePoint[];
}

export function LineChart({ series }: { series: LineSeries[] }) {
    const allX = Array.from(new Set(series.flatMap(s => s.points.map(p => p.x)))).sort();
    const allY = series.flatMap(s => s.points.map(p => p.y));

    if (allX.length === 0) {
        return <div className="chart-empty">No data for this range.</div>;
    }

    const maxY = Math.max(...allY, 0);
    const minY = Math.min(...allY, 0);
    const range = maxY - minY || 1;

    const W = 100;
    const H = 40;
    const xStep = allX.length > 1 ? W / (allX.length - 1) : 0;
    const yToPixel = (y: number) => H - ((y - minY) / range) * H;

    return (
        <div className="chart-wrap">
            <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" className="chart-svg">
                {series.map(s => {
                    const byX = new Map(s.points.map(p => [p.x, p.y]));
                    const coords = allX
                        .map((x, i) => {
                            const y = byX.get(x);
                            return y === undefined ? null : `${i * xStep},${yToPixel(y)}`;
                        })
                        .filter((c): c is string => c !== null);

                    if (coords.length === 0) return null;

                    return (
                        <polyline
                            key={s.name}
                            points={coords.join(' ')}
                            fill="none"
                            stroke={s.color}
                            strokeWidth={0.6}
                            vectorEffect="non-scaling-stroke"
                        />
                    );
                })}
            </svg>

            <div className="chart-legend">
                {series.map(s => (
                    <span key={s.name} className="chart-legend-item">
                        <span className="chart-swatch" style={{ background: s.color }} />
                        {s.name}
                    </span>
                ))}
            </div>

            <div className="chart-x-labels">
                <span>{new Date(allX[0]).toLocaleDateString()}</span>
                {allX.length > 1 && <span>{new Date(allX[allX.length - 1]).toLocaleDateString()}</span>}
            </div>
        </div>
    );
}

interface BarDatum {
    label: string;
    value: number;
    color?: string;
}

export function BarChart({ data }: { data: BarDatum[] }) {
    if (data.length === 0) {
        return <div className="chart-empty">No data for this range.</div>;
    }

    const max = Math.max(...data.map(d => Math.abs(d.value)), 1);

    return (
        <div className="bar-chart">
            {data.map(d => (
                <div className="bar-chart-row" key={d.label}>
                    <div className="bar-chart-label">{d.label}</div>
                    <div className="bar-chart-track">
                        <div
                            className={`bar-chart-fill ${d.value < 0 ? 'negative' : ''}`}
                            style={{ width: `${(Math.abs(d.value) / max) * 100}%`, background: d.color }}
                        />
                    </div>
                    <div className="bar-chart-value">{Math.round(d.value).toLocaleString()}</div>
                </div>
            ))}
        </div>
    );
}
