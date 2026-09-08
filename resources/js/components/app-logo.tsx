import { Gem } from 'lucide-react';

export default function AppLogo() {
    return (
        <div className="flex items-center px-2 py-2 group-data-[collapsible=icon]:px-0">
            <div className="flex aspect-square size-[42px] items-center justify-center rounded-[10px] bg-[#e4b248] group-data-[collapsible=icon]:size-8">
                <Gem className="size-[22px] fill-transparent stroke-[2.5] text-black group-data-[collapsible=icon]:size-4" />
            </div>
            <div className="ml-3 flex flex-col justify-center text-left group-data-[collapsible=icon]:hidden">
                <span className="text-[17px] font-bold leading-tight tracking-wide text-sidebar-foreground">
                    ZAR & NOOR
                </span>
                <span className="mt-1 text-[10px] font-bold uppercase leading-none tracking-widest text-[#b5892f]">
                    Jewelry ERP
                </span>
            </div>
        </div>
    );
}
