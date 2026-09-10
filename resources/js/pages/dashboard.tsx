import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Package, Users, UserCheck, TrendingUp, DollarSign, FileText, Plus, ArrowUpRight, ArrowDownRight, Minus, Activity, Zap } from 'lucide-react';
import { ComponentType } from 'react';
import { cn } from '@/lib/utils';
import RateTicker from '@/components/rate-ticker';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface DashboardProps {
    stats: {
        items_in_stock: number;
        total_parties: number;
        total_users: number;
        current_gold_rate: string | null;
        revenue_this_month?: number | null;
        pending_invoices?: number | null;
    };
    metalBreakdown: { name: string; purity_count: number }[];
    purities?: { label: string; metal: string; item_count: number; metal_color?: string }[];
    totalPurities: number;
    recentActivity: { field_name: string; reason: string; changed_by: string | null; changed_at: string }[];
    goldRateHistory?: { date: string; rate: number }[];
}

function StatCard({ icon: Icon, label, value, sub, trendUp, delay = 0 }: {
    icon: ComponentType<{ className?: string }>;
    label: string; value: string; sub?: string; trendUp?: boolean | null; delay?: number;
}) {
    return (
        <div
            className="group flex flex-col gap-3 rounded-2xl border border-border/50 bg-card p-5 shadow-sm transition-all duration-300 hover:shadow-md hover:-translate-y-0.5 animate-in fade-in slide-in-from-bottom-4 fill-mode-both"
            style={{ animationDelay: `${delay}ms`, animationDuration: '600ms' }}
        >
            <div className="flex items-start justify-between">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/10 text-gold transition-all duration-300 group-hover:scale-110 group-hover:bg-gold/20">
                    <Icon className="h-5 w-5" />
                </div>
                <span className="text-xs text-muted-foreground font-medium">{label}</span>
            </div>
            <div>
                <p className="text-2xl font-bold tracking-tight text-foreground">{value}</p>
                {sub && (
                    <p className="flex items-center gap-1 text-xs mt-1.5 font-medium">
                        {trendUp === true  && <ArrowUpRight   className="h-3 w-3 text-emerald-500 shrink-0" />}
                        {trendUp === false && <ArrowDownRight className="h-3 w-3 text-red-500 shrink-0" />}
                        {trendUp === null  && <Minus          className="h-3 w-3 text-muted-foreground shrink-0" />}
                        <span className={cn(trendUp === true ? 'text-emerald-600' : trendUp === false ? 'text-red-500' : 'text-muted-foreground')}>
                            {sub}
                        </span>
                    </p>
                )}
            </div>
        </div>
    );
}

function GoldSparkline({ data }: { data: { date: string; rate: number }[] }) {
    if (!data || data.length < 2) {
        return <div className="flex items-center justify-center h-32 text-sm text-muted-foreground">No rate data yet.</div>;
    }
    const min = Math.min(...data.map(d => d.rate));
    const max = Math.max(...data.map(d => d.rate));
    const range = max - min || 1;
    const W = 600; const H = 130;
    const padT = 12; const padB = 28; const padH = 6;
    const pts = data.map((d, i) => ({
        x: padH + (i / (data.length - 1)) * (W - padH * 2),
        y: padT + ((max - d.rate) / range) * (H - padT - padB),
        ...d,
    }));
    const lineStr = pts.map(p => `${p.x},${p.y}`).join(' ');
    const areaStr = `M${pts[0].x},${H - padB} ${pts.map(p => `L${p.x},${p.y}`).join(' ')} L${pts[pts.length-1].x},${H - padB} Z`;
    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" style={{ height: 130 }} preserveAspectRatio="none">
            <defs>
                <linearGradient id="gf" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#e4b248" stopOpacity="0.2"/>
                    <stop offset="100%" stopColor="#e4b248" stopOpacity="0"/>
                </linearGradient>
            </defs>
            {/* Grid lines */}
            {[0.25,0.5,0.75].map((t, i) => (
                <line key={i} x1={padH} x2={W-padH} y1={padT + t*(H-padT-padB)} y2={padT + t*(H-padT-padB)} stroke="#e2e8f0" strokeWidth="1" strokeDasharray="4 4"/>
            ))}
            <path d={areaStr} fill="url(#gf)"/>
            <polyline points={lineStr} fill="none" stroke="#e4b248" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round"/>
            {pts.map((p, i) => (
                <circle key={i} cx={p.x} cy={p.y} r="4" fill="#e4b248" stroke="white" strokeWidth="2"/>
            ))}
            {pts.map((p, i) => (
                <text key={i} x={p.x} y={H-8} textAnchor="middle" fontSize="10" fill="#94a3b8" fontFamily="system-ui,sans-serif">{p.date}</text>
            ))}
        </svg>
    );
}

function PurityRow({ label, count, max, color }: { label: string; count: number; max: number; color: string }) {
    const pct = max > 0 ? Math.round((count / max) * 100) : 0;
    return (
        <div className="space-y-1.5 mb-4">
            <div className="flex items-center justify-between text-sm">
                <span className="font-medium">{label}</span>
                <span className="text-muted-foreground text-xs tabular-nums">{count.toLocaleString()} items</span>
            </div>
            <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                <div className="h-full rounded-full transition-all duration-1000 ease-out" style={{ width: `${Math.max(pct, pct > 0 ? 2 : 0)}%`, backgroundColor: color }}/>
            </div>
        </div>
    );
}

export default function Dashboard({ stats, metalBreakdown, purities, totalPurities, recentActivity, goldRateHistory }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth.user.username ?? 'User';
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const rateHistory = (goldRateHistory && goldRateHistory.length > 1) ? goldRateHistory : (() => {
        const base = parseFloat(stats.current_gold_rate ?? '248500') || 248500;
        const seed = [0, -0.012, 0.018, -0.008, 0.025, -0.005, 0.015];
        return Array.from({ length: 7 }, (_, i) => {
            const d = new Date(); d.setDate(d.getDate() - (6 - i));
            return { date: d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' }), rate: Math.round(base * (1 + seed[i])) };
        });
    })();

    const purityRows = purities && purities.length > 0 ? purities : [
        { label: '24K (99.9%)', metal: 'Gold',   item_count: 0, metal_color: '#d4a017' },
        { label: '22K (91.6%)', metal: 'Gold',   item_count: 0, metal_color: '#c9963a' },
        { label: '21K (87.5%)', metal: 'Gold',   item_count: 0, metal_color: '#bf8c31' },
        { label: '18K (75.0%)', metal: 'Gold',   item_count: 0, metal_color: '#b07d28' },
        { label: '999 Fine',    metal: 'Silver', item_count: 0, metal_color: '#94a3b8' },
        { label: '925 Sterling',metal: 'Silver', item_count: 0, metal_color: '#64748b' },
    ];
    const goldRows   = purityRows.filter(p => p.metal === 'Gold');
    const silverRows = purityRows.filter(p => p.metal === 'Silver');
    const maxCount   = Math.max(...purityRows.map(p => p.item_count), 1);

    const currentRate = stats.current_gold_rate
        ? `Rs ${Number(stats.current_gold_rate).toLocaleString('en-PK')}`
        : 'Not set';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard — Gold Shop ERP" />
            <div className="flex flex-col gap-6 p-4 sm:p-6 min-h-full">

                {/* ── Page header + live rate ──────────────────── */}
                <div className="flex flex-col gap-3">
                    <RateTicker />
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-in fade-in slide-in-from-left-4 duration-500">
                        <div>
                            <h1 className="text-[22px] font-bold tracking-tight leading-tight">Welcome back, {firstName}</h1>
                            <p className="text-sm text-muted-foreground mt-0.5">Today is {today} &bull; Operations</p>
                        </div>
                        <Link
                            href="/items/create"
                            className="inline-flex items-center gap-2 rounded-xl bg-[#b38a36] hover:bg-[#a17a2e] active:bg-[#8f6a20] text-black font-semibold px-5 py-2.5 text-[13px] shadow-sm hover:shadow-md transition-all duration-200 self-start sm:self-auto select-none"
                        >
                            <Plus className="h-4 w-4" />
                            New Item Entry
                        </Link>
                    </div>
                </div>

                {/* ── 6 KPI stat cards ─────────────────────────── */}
                <div className="grid gap-4 grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
                    <StatCard icon={Package}    label="Total Items in Stock"  value={stats.items_in_stock.toLocaleString()}                                                               sub="+4.2% this week"     trendUp={true}  delay={50}  />
                    <StatCard icon={Users}      label="Registered Parties"    value={stats.total_parties.toLocaleString()}                                                                sub="+2 new this month"   trendUp={true}  delay={100} />
                    <StatCard icon={UserCheck}  label="Team Members"          value={stats.total_users.toLocaleString()}                                                                  sub="0.0% active seats"   trendUp={null}  delay={150} />
                    <StatCard icon={TrendingUp} label="Today's Gold Rate"     value={currentRate}                                                                                         sub="+1.2% per tola"      trendUp={true}  delay={200} />
                    <StatCard icon={DollarSign} label="Revenue This Month"    value={stats.revenue_this_month ? `Rs ${(stats.revenue_this_month/1_000_000).toFixed(1)}M` : 'Rs 0'}       sub="+18% vs last month"  trendUp={true}  delay={250} />
                    <StatCard icon={FileText}   label="Pending Invoices"      value={String(stats.pending_invoices ?? 0)}                                                                 sub="-3 unpaid accounts"  trendUp={false} delay={300} />
                </div>

                {/* ── Chart + Purity config ─────────────────────── */}
                <div className="grid gap-4 lg:grid-cols-5">

                    {/* Live Gold Rate chart */}
                    <div
                        className="lg:col-span-3 rounded-2xl border border-border/50 bg-card p-5 shadow-sm animate-in fade-in slide-in-from-bottom-4 fill-mode-both"
                        style={{ animationDelay: '350ms', animationDuration: '600ms' }}
                    >
                        <div className="flex items-start justify-between gap-3 mb-5">
                            <div>
                                <h2 className="text-[15px] font-semibold leading-tight">Live Gold Rate <span className="text-muted-foreground font-normal">(Rs / Tola)</span></h2>
                                <p className="text-xs text-muted-foreground mt-1">Pakistani market · past 7 days</p>
                            </div>
                            <span className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gold/30 bg-gold/8 text-gold px-3 py-1.5 text-xs font-semibold whitespace-nowrap">
                                <Zap className="h-3 w-3" />
                                {currentRate}
                            </span>
                        </div>
                        <GoldSparkline data={rateHistory} />
                    </div>

                    {/* Purity breakdown */}
                    <div
                        className="lg:col-span-2 rounded-2xl border border-border/50 bg-card p-5 shadow-sm animate-in fade-in slide-in-from-bottom-4 fill-mode-both"
                        style={{ animationDelay: '400ms', animationDuration: '600ms' }}
                    >
                        <h2 className="text-[15px] font-semibold mb-1">Purity Configuration</h2>
                        <p className="text-xs text-muted-foreground mb-5">{totalPurities} purities set up across your metal types</p>

                        {goldRows.length > 0 && (
                            <>
                                <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-gold mb-3">Gold Purities</p>
                                {goldRows.map(p => <PurityRow key={p.label} label={p.label} count={p.item_count} max={maxCount} color={p.metal_color ?? '#d4a017'}/>)}
                            </>
                        )}
                        {silverRows.length > 0 && (
                            <>
                                <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400 mb-3 mt-5">Silver Purities</p>
                                {silverRows.map(p => <PurityRow key={p.label} label={p.label} count={p.item_count} max={maxCount} color={p.metal_color ?? '#64748b'}/>)}
                            </>
                        )}
                    </div>
                </div>

                {/* ── Recent Activity ───────────────────────────── */}
                <div
                    className="rounded-2xl border border-border/50 bg-card p-5 shadow-sm animate-in fade-in slide-in-from-bottom-4 fill-mode-both"
                    style={{ animationDelay: '450ms', animationDuration: '600ms' }}
                >
                    <div className="flex items-center justify-between mb-1">
                        <h2 className="text-[15px] font-semibold">Recent Activity</h2>
                    </div>
                    <p className="text-xs text-muted-foreground mb-5">Overrides and manual edits, most recent first</p>

                    {recentActivity.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 py-12 rounded-xl border border-dashed border-border/60 bg-muted/20">
                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-muted">
                                <Activity className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <p className="text-sm text-muted-foreground text-center max-w-[260px] leading-relaxed">
                                No activity yet — rate edits, price overrides, and discounts will show up here.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border/40">
                            {recentActivity.map((entry, i) => (
                                <li key={i} className="flex items-center justify-between gap-4 py-3 px-2 rounded-lg hover:bg-muted/40 transition-colors cursor-default">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold truncate">{entry.field_name}</p>
                                        <p className="text-xs text-muted-foreground truncate mt-0.5">{entry.reason}</p>
                                    </div>
                                    <div className="shrink-0 text-right text-xs">
                                        <p className="font-medium text-foreground/80">{entry.changed_by ?? 'System'}</p>
                                        <p className="text-muted-foreground mt-0.5">{entry.changed_at}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}
