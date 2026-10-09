import { Gem } from 'lucide-react';
import { usePage } from '@inertiajs/react';

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
                <span className="truncate text-[17px] font-bold uppercase leading-tight tracking-wide text-sidebar-foreground">
                    {shop_name || 'Zar & Noor'}
                </span>
                <span className="mt-1 text-[10px] font-bold uppercase leading-none tracking-widest text-[#b5892f]">
                    Jewelry ERP
                </span>
            </div>
        </div>
    );
}
