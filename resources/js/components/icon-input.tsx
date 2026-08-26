import { LucideIcon } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface IconInputProps extends React.ComponentProps<typeof Input> {
    icon: LucideIcon;
}

const IconInput = React.forwardRef<HTMLInputElement, IconInputProps>(({ icon: Icon, className, ...props }, ref) => (
    <div className="relative">
        <Icon className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <Input ref={ref} className={cn('pl-9', className)} {...props} />
    </div>
));
IconInput.displayName = 'IconInput';

export { IconInput };
