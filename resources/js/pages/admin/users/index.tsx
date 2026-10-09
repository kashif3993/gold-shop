import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import '../../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }, { title: 'Users', href: '/admin/users' }];

interface UserRow {
    id: number;
    username: string;
    email: string | null;
    role: 'admin' | 'manager' | 'shopkeeper';
    is_active: boolean;
    created_at: string;
}

export default function UsersIndex({ users }: { users: UserRow[] }) {
    const { flash, auth } = usePage().props as any;
    const currentUserId = auth?.user?.id;

    const changeRole = (user: UserRow, role: string) => {
        if (role === user.role) return;
        if (!confirm(`Make "${user.username}" ${role}?`)) return;
        router.post(`/admin/users/${user.id}/role`, { role }, { preserveScroll: true });
    };

    const toggleActive = (user: UserRow) => {
        const verb = user.is_active ? 'deactivate' : 'reactivate';
        if (!confirm(`Are you sure you want to ${verb} "${user.username}"?`)) return;
        router.post(`/admin/users/${user.id}/toggle-active`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Users</h1>
                    <p className="adm-desc">Every login the shop has — who's an admin, a manager, or a shopkeeper, and who's still active.</p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <div className="adm-card">
                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map(user => {
                                    const isSelf = user.id === currentUserId;
                                    return (
                                        <tr key={user.id} className={user.is_active ? '' : 'inactive'}>
                                            <td>{user.username} {isSelf && <span className="adm-muted">(you)</span>}</td>
                                            <td className="adm-muted">{user.email ?? '—'}</td>
                                            <td>
                                                <select
                                                    className="adm-select"
                                                    value={user.role}
                                                    disabled={isSelf}
                                                    onChange={e => changeRole(user, e.target.value)}
                                                    style={{ minWidth: '120px' }}
                                                >
                                                    <option value="shopkeeper">Shopkeeper</option>
                                                    <option value="manager">Manager</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                            </td>
                                            <td>
                                                <span className={`adm-badge ${user.is_active ? 'adm-badge-active' : 'adm-badge-inactive'}`}>
                                                    {user.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="adm-muted">{new Date(user.created_at).toLocaleDateString()}</td>
                                            <td style={{ textAlign: 'right' }}>
                                                <button
                                                    type="button"
                                                    disabled={isSelf}
                                                    className={`adm-btn adm-btn-sm ${user.is_active ? 'adm-btn-danger' : 'adm-btn-ghost'}`}
                                                    onClick={() => toggleActive(user)}
                                                >
                                                    {user.is_active ? 'Deactivate' : 'Reactivate'}
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <p className="adm-card-sub" style={{ maxWidth: '60ch' }}>
                    New logins are created from the sign-up screen (always as a shopkeeper) — promote someone to admin here afterward. You can't change your own role or deactivate your own account, so the shop can never lock itself out.
                </p>
            </div>
        </AdminLayout>
    );
}
