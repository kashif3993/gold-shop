import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function ThemeSwitch({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        setIsDark(document.documentElement.classList.contains('dark'));
    }, [appearance]);

    const toggleTheme = () => {
        const newTheme = !isDark;
        setIsDark(newTheme);
        updateAppearance(newTheme ? 'dark' : 'light');
    };

    return (
        <button
            onClick={toggleTheme}
            aria-label="Toggle dark mode"
            className={cn(
                'relative flex h-[34px] w-[68px] items-center rounded-full transition-colors duration-300',
                isDark ? 'bg-slate-800' : 'bg-slate-100',
                className,
            )}
        >
            <div className="flex w-full justify-between px-2.5">
                <Sun className={cn('z-10 h-3.5 w-3.5 transition-colors', !isDark ? 'text-gold' : 'text-slate-400')} />
                <Moon className={cn('z-10 h-3.5 w-3.5 transition-colors', isDark ? 'text-gold' : 'text-slate-400')} />
            </div>

            <div
                className={cn(
                    'absolute top-1/2 h-[26px] w-[26px] -translate-y-1/2 rounded-full shadow-sm transition-transform duration-300 ease-in-out',
                    isDark ? 'translate-x-[36px] bg-slate-900' : 'translate-x-[4px] bg-white',
                )}
            />
        </button>
    );
}
