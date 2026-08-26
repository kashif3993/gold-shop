import AppLogoIcon from '@/components/app-logo-icon';
import ThemeSwitch from '@/components/theme-switch';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background px-4 py-10 sm:px-6">
            <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-gold/20 blur-3xl" />
                <div className="absolute -right-24 -bottom-24 h-72 w-72 rounded-full bg-gold/10 blur-3xl" />
            </div>

            <div className="relative z-10 w-full max-w-sm">
                <div className="flex flex-col gap-6 rounded-2xl border border-border bg-card p-5 shadow-xl shadow-black/5 sm:p-8 dark:shadow-black/40">
                    <div className="flex items-center justify-end">
                        <ThemeSwitch />
                    </div>

                    <div className="flex flex-col items-center gap-3 text-center">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-gold shadow-sm sm:h-12 sm:w-12">
                                <AppLogoIcon className="h-6 w-6 fill-gold-foreground sm:h-7 sm:w-7" />
                            </span>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-1">
                            <h1 className="text-lg font-semibold tracking-tight text-balance sm:text-xl">{title}</h1>
                            {description && (
                                <p className="text-xs text-balance text-muted-foreground sm:text-sm">{description}</p>
                            )}
                        </div>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
