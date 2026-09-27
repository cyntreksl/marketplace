import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    MapPin,
    PackageCheck,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { claimDeliveryDetailsModal } from '@/lib/delivery-details';
import { index as listingsIndex } from '@/routes/listings';

const deliveryBenefits = [
    {
        icon: ShieldCheck,
        title: 'Safe & secure',
        description: 'Protected delivery',
    },
    {
        icon: MapPin,
        title: 'All locations',
        description: 'Across Sri Lanka',
    },
    {
        icon: PackageCheck,
        title: 'Handled with care',
        description: 'Your order, our priority',
    },
] as const;

function DeliveryIllustration() {
    return (
        <div
            aria-hidden="true"
            className="relative hidden min-h-[36rem] overflow-hidden bg-[radial-gradient(circle_at_68%_28%,#fff7d6_0,transparent_24%),linear-gradient(145deg,#fff7ed_0%,#ffedd5_45%,#fdba74_100%)] lg:block"
        >
            <div className="absolute top-12 left-12 h-12 w-28 rounded-full bg-white/70 blur-sm" />
            <div className="absolute top-24 right-10 h-8 w-20 rounded-full bg-white/75 blur-sm" />

            <div
                className="absolute top-10 left-1/2 h-[27rem] w-52 -translate-x-1/2 rotate-[-7deg] bg-gradient-to-b from-orange-200 via-orange-300 to-orange-400 shadow-[0_30px_70px_rgba(234,88,12,0.22)]"
                style={{
                    clipPath:
                        'polygon(48% 0, 64% 8%, 71% 20%, 86% 31%, 80% 43%, 95% 57%, 86% 69%, 81% 85%, 62% 100%, 48% 92%, 39% 78%, 22% 65%, 29% 51%, 15% 36%, 27% 25%, 31% 10%)',
                }}
            />

            <svg
                viewBox="0 0 220 330"
                className="absolute top-20 left-1/2 h-80 w-52 -translate-x-1/2 overflow-visible"
            >
                <path
                    d="M78 32 C154 75, 164 118, 91 142 S60 211, 144 224 S158 278, 105 300"
                    fill="none"
                    stroke="#f0440b"
                    strokeDasharray="7 9"
                    strokeLinecap="round"
                    strokeWidth="2"
                />
            </svg>

            {[
                ['top-20', 'left-[49%]'],
                ['top-52', 'left-[64%]'],
                ['top-80', 'left-[45%]'],
                ['top-[25rem]', 'left-[62%]'],
            ].map(([top, left], index) => (
                <span
                    key={`${top}-${left}`}
                    className={`absolute ${top} ${left} grid size-10 -translate-x-1/2 place-items-center rounded-full bg-[#ff4b0a] text-white shadow-lg ring-4 ring-white/35`}
                >
                    <MapPin className="size-5 fill-current" />
                    <span className="sr-only">Delivery point {index + 1}</span>
                </span>
            ))}

            <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-white via-white/90 to-transparent" />
            <div className="absolute right-6 bottom-12 left-8 flex items-end justify-center gap-3">
                <span className="grid size-20 place-items-center rounded-2xl border-4 border-white bg-orange-500 text-white shadow-xl">
                    <PackageCheck className="size-10" />
                </span>
                <span className="relative flex h-28 w-72 items-center justify-center rounded-[2.25rem] border-4 border-white bg-[#ff5a0a] text-white shadow-2xl">
                    <Truck className="size-24" strokeWidth={1.7} />
                    <span className="absolute -bottom-3 left-12 size-7 rounded-full border-4 border-white bg-slate-800" />
                    <span className="absolute right-12 -bottom-3 size-7 rounded-full border-4 border-white bg-slate-800" />
                </span>
            </div>
        </div>
    );
}

export function DeliveryDetailsModal() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (claimDeliveryDetailsModal(window.localStorage)) {
                setOpen(true);
            }
        }, 0);

        return () => window.clearTimeout(timeout);
    }, []);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-h-[92dvh] gap-0 overflow-y-auto rounded-3xl border-0 bg-white p-0 shadow-[0_32px_100px_rgba(15,23,42,0.38)] sm:max-w-[calc(100%-3rem)] lg:max-w-6xl [&>button]:top-4 [&>button]:right-4 [&>button]:z-20 [&>button]:grid [&>button]:size-10 [&>button]:place-items-center [&>button]:rounded-full [&>button]:bg-white/90 [&>button]:text-slate-700 [&>button]:opacity-100 [&>button]:shadow-md [&>button]:hover:bg-white [&>button_svg]:size-5">
                <div className="grid lg:grid-cols-[1.08fr_0.92fr]">
                    <div className="flex flex-col justify-center px-6 py-10 sm:px-10 sm:py-12 lg:px-14 lg:py-14">
                        <p className="text-xs font-black tracking-[0.25em] text-[#f0440b] uppercase sm:text-sm">
                            Shop anywhere in Sri Lanka
                        </p>
                        <DialogTitle className="mt-3 text-4xl leading-[0.96] font-black tracking-[-0.05em] text-[#08264c] sm:text-5xl lg:text-6xl">
                            Island Wide{' '}
                            <span className="text-[#f0440b]">Delivery</span>
                        </DialogTitle>
                        <DialogDescription className="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">
                            Your favourite products, delivered safely to your
                            doorstep — anywhere in Sri Lanka.
                        </DialogDescription>

                        <div className="mt-7 flex items-center gap-4 rounded-3xl border border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50 px-5 py-5 sm:gap-6 sm:px-7">
                            <span className="grid size-16 shrink-0 place-items-center rounded-2xl bg-white text-[#f0440b] shadow-sm sm:size-20">
                                <Truck className="size-9 sm:size-11" />
                            </span>
                            <div>
                                <p className="text-sm font-black text-[#08264c] sm:text-base">
                                    Only
                                </p>
                                <p className="text-4xl leading-none font-black tracking-[-0.05em] text-[#f0440b] sm:text-5xl">
                                    Rs. 200
                                </p>
                                <p className="mt-1 text-sm font-bold text-[#08264c] sm:text-base">
                                    Island Wide Delivery Charges
                                </p>
                            </div>
                        </div>

                        <div className="mt-7 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-0 sm:divide-x sm:divide-slate-200">
                            {deliveryBenefits.map(
                                ({ icon: Icon, title, description }) => (
                                    <div
                                        key={title}
                                        className="flex items-center gap-3 sm:px-4 sm:first:pl-0 sm:last:pr-0"
                                    >
                                        <Icon className="size-8 shrink-0 text-[#f0440b]" />
                                        <span className="text-sm leading-5 text-[#08264c]">
                                            <strong className="block font-extrabold">
                                                {title}
                                            </strong>
                                            {description}
                                        </span>
                                    </div>
                                ),
                            )}
                        </div>

                        <Link
                            href={listingsIndex()}
                            onClick={() => setOpen(false)}
                            className="mt-8 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#ff4b0a] to-[#ff6d00] px-8 text-base font-black text-white shadow-[0_12px_28px_rgba(240,68,11,0.25)] transition hover:brightness-95 focus-visible:ring-2 focus-visible:ring-[#f0440b] focus-visible:ring-offset-2 focus-visible:outline-none sm:w-fit sm:min-w-72 sm:text-lg"
                        >
                            Start Shopping
                            <ArrowRight className="size-5" />
                        </Link>
                    </div>

                    <DeliveryIllustration />
                </div>
            </DialogContent>
        </Dialog>
    );
}
