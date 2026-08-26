import { Gem } from 'lucide-react';

export default function AppLogo() {
    return (
        <div className="flex items-center py-2 px-2">
            <div className="bg-[#e4b248] flex aspect-square size-[42px] items-center justify-center rounded-[10px]">
                <Gem className="size-[22px] text-black fill-transparent stroke-[2.5]" />
            </div>
            <div className="ml-3 flex flex-col justify-center text-left">
                <span className="text-[17px] font-bold leading-tight text-[#000000] tracking-wide">
                    ZAR & NOOR
                </span>
                <span className="text-[10px] font-bold uppercase leading-none text-[#e4b248] tracking-widest mt-1">
                    Jewelry ERP
                </span>
            </div>
        </div>
    );
}

