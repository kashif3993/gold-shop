import { Head } from '@inertiajs/react';
import { Wrench } from 'lucide-react';

export default function Maintenance({ message }: { message: string }) {
    return (
        <>
            <Head title="Under Maintenance" />
            <div
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '1.25rem',
                    padding: '2rem',
                    textAlign: 'center',
                    background: '#0f0d0a',
                    color: '#f3ecdd',
                }}
            >
                <div
                    style={{
                        width: 56,
                        height: 56,
                        borderRadius: '50%',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: 'rgba(212,175,55,0.12)',
                        border: '1px solid rgba(212,175,55,0.35)',
                    }}
                >
                    <Wrench size={24} color="#d4af37" />
                </div>
                <h1 style={{ margin: 0, fontSize: '1.4rem', fontWeight: 700 }}>Under Maintenance</h1>
                <p style={{ margin: 0, maxWidth: '38ch', color: '#a89a80', lineHeight: 1.6 }}>{message}</p>
                <p style={{ margin: 0, fontSize: '0.8rem', color: '#766e5e' }}>An admin can sign in as usual to switch this off.</p>
            </div>
        </>
    );
}
