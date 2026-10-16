import { usePage } from '@inertiajs/react';
import { Gem } from 'lucide-react';

export default function AppLogo() {
    // Shared on every page (see HandleInertiaRequests) — falls back to the
    // same text this always showed, so an install that hasn't touched
    // Admin → Settings looks identical.
    const { shop_name } = usePage().props as { shop_name?: string };

    return (
        <div className="flex items-center px-2 py-2 group-data-[collapsible=icon]:px-0">
            <div className="flex aspect-square size-[42px] items-center justify-center rounded-[10px] bg-[#e4b248] group-data-[collapsible=icon]:size-8">
                <Gem className="size-[22px] fill-transparent stroke-[2.5] text-black group-data-[collapsible=icon]:size-4" />
            </div>
            <div className="ml-3 flex flex-col justify-center text-left group-data-[collapsible=icon]:hidden">
                <span className="text-sidebar-foreground truncate text-[17px] leading-tight font-bold tracking-wide uppercase">
                    {shop_name || 'Zar & Noor'}
                </span>
                <span className="mt-1 text-[10px] leading-none font-bold tracking-widest text-[#b5892f] uppercase">Jewelry ERP</span>
            </div>
        </div>
    );
}
