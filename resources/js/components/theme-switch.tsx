import { useEffect, useState } from 'react';
import { Moon, Sun } from 'lucide-react';

import { cn } from '@/lib/utils';
import { useAppearance } from '@/hooks/use-appearance';

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
                className
            )}
        >
            <div className="flex w-full justify-between px-2.5">
                <Sun className={cn("h-3.5 w-3.5 transition-colors z-10", !isDark ? "text-gold" : "text-slate-400")} />
                <Moon className={cn("h-3.5 w-3.5 transition-colors z-10", isDark ? "text-gold" : "text-slate-400")} />
            </div>
            
            <div 
                className={cn(
                    "absolute top-1/2 h-[26px] w-[26px] -translate-y-1/2 rounded-full transition-transform duration-300 ease-in-out shadow-sm",
                    isDark ? "bg-slate-900 translate-x-[36px]" : "bg-white translate-x-[4px]"
                )} 
            />
        </button>
    );
}
