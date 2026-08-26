import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ComponentProps } from 'react';

type LinkProps = ComponentProps<typeof Link>;

export default function TextLink({ className = '', children, ...props }: LinkProps) {
    return (
        <Link
            className={cn(
                'text-gold font-medium underline decoration-gold/40 underline-offset-4 transition-colors duration-300 ease-out hover:text-gold/80 hover:decoration-current!',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
