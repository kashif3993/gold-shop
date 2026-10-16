import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import '../../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Backups', href: '/admin/backups' },
];

interface BackupRow {
    disk: string;
    location: string;
    path: string;
    filename: string;
    size_mb: number;
    created_at: number; // unix timestamp
}

// URL-safe base64 — the disk path can contain "/", which a plain base64
// value would turn into extra path segments.
const encodePath = (path: string) => btoa(path).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

interface Props {
    backups: BackupRow[];
    secondaryConfigured: boolean;
    secondaryAvailable: boolean;
    totalSizeMb: number;
    newestAgeHours: number | null;
}

export default function BackupsIndex({ backups, secondaryConfigured, secondaryAvailable, totalSizeMb, newestAgeHours }: Props) {
    const { flash } = usePage().props as any;
    const [running, setRunning] = useState(false);

    const runNow = () => {
        setRunning(true);
        router.post(
            '/admin/backups/run',
            {},
            {
                preserveScroll: true,
                onFinish: () => setRunning(false),
            },
        );
    };

    const isStale = newestAgeHours !== null && newestAgeHours > 26;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Backups" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Backups</h1>
                    <p className="adm-desc">
                        A full database backup is taken automatically every night — no download needed, and it doesn't depend on the internet.{' '}
                        {secondaryConfigured
                            ? "Every backup is saved in two places at once: this computer, and a second location, so a problem with one doesn't lose both."
                            : 'It saves to this computer. A second, physically separate location can be added later (see BACKUP_SECOND_DRIVE_PATH) for extra protection against this drive itself failing.'}
                    </p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                {secondaryConfigured && !secondaryAvailable && (
                    <div className="adm-flash adm-flash-error">
                        The second backup location isn't reachable right now — backups are still being saved to this computer, but that copy will be
                        skipped until it's back. Check that the drive is connected / powered on.
                    </div>
                )}

                <div className="adm-card">
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1.5rem', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ display: 'flex', gap: '2rem', flexWrap: 'wrap' }}>
                            <div>
                                <div className="adm-muted" style={{ fontSize: '.75rem', textTransform: 'uppercase', letterSpacing: '.05em' }}>
                                    Last backup
                                </div>
                                <div style={{ fontSize: '1.1rem', fontWeight: 600 }}>
                                    {newestAgeHours === null
                                        ? 'None yet'
                                        : newestAgeHours < 1
                                          ? 'Less than an hour ago'
                                          : `${newestAgeHours} hours ago`}
                                </div>
                            </div>
                            <div>
                                <div className="adm-muted" style={{ fontSize: '.75rem', textTransform: 'uppercase', letterSpacing: '.05em' }}>
                                    Backups kept
                                </div>
                                <div style={{ fontSize: '1.1rem', fontWeight: 600 }}>{backups.length}</div>
                            </div>
                            <div>
                                <div className="adm-muted" style={{ fontSize: '.75rem', textTransform: 'uppercase', letterSpacing: '.05em' }}>
                                    Total size
                                </div>
                                <div style={{ fontSize: '1.1rem', fontWeight: 600 }}>{totalSizeMb} MB</div>
                            </div>
                        </div>
                        <button type="button" className="adm-btn adm-btn-gold" onClick={runNow} disabled={running}>
                            {running ? 'Backing up…' : 'Back up now'}
                        </button>
                    </div>

                    {isStale && (
                        <div className="adm-flash adm-flash-error" style={{ marginTop: '1rem' }}>
                            No backup in over a day — the scheduled backup only runs while the server's task scheduler (cron) is active. If this shop
                            is on a real server, confirm `php artisan schedule:run` is wired up; on a local machine that isn't left running overnight,
                            use "Back up now" before closing for the day.
                        </div>
                    )}
                </div>

                <div className="adm-card">
                    <h2 className="adm-card-title">Saved backups</h2>
                    <p className="adm-card-sub">Newest first. Older ones are cleaned up automatically to keep disk usage in check.</p>

                    {backups.length === 0 ? (
                        <p className="adm-muted">No backups yet — click "Back up now" above to create the first one.</p>
                    ) : (
                        <div className="adm-table-wrap">
                            <table className="adm-table">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Location</th>
                                        <th>Created</th>
                                        <th>Size</th>
                                        <th style={{ textAlign: 'right' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {backups.map((b) => (
                                        <tr key={`${b.disk}:${b.path}`}>
                                            <td>{b.filename}</td>
                                            <td>
                                                <span className={`adm-badge ${b.disk === 'backups' ? 'adm-badge-active' : ''}`}>{b.location}</span>
                                            </td>
                                            <td className="adm-muted">{new Date(b.created_at * 1000).toLocaleString()}</td>
                                            <td className="adm-muted">{b.size_mb} MB</td>
                                            <td style={{ textAlign: 'right' }}>
                                                <a
                                                    className="adm-btn adm-btn-sm adm-btn-ghost"
                                                    href={`/admin/backups/${b.disk}/${encodePath(b.path)}/download`}
                                                >
                                                    Download
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <p className="adm-card-sub" style={{ maxWidth: '65ch' }}>
                    Every backup is a complete snapshot of the database at that moment — sales, inventory, invoices, rates, and users.{' '}
                    {secondaryConfigured ? 'Both copies are made automatically every night; downloading' : 'Downloading'} one here is entirely
                    optional, e.g. to also keep a copy on a USB drive.
                </p>
            </div>
        </AdminLayout>
    );
}
