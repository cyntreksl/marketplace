import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronRight,
    MapPin,
    Package,
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
        desktopIcon: ShieldCheck,
        desktopTitle: 'Safe & Secure',
        desktopDescription: 'Delivery',
    },
    {
        icon: MapPin,
        title: 'All locations',
        description: 'Across Sri Lanka',
        desktopIcon: MapPin,
        desktopTitle: 'To All',
        desktopDescription: 'Locations',
    },
    {
        icon: PackageCheck,
        title: 'Handled with care',
        description: 'Your order, our priority',
        desktopIcon: Package,
        desktopTitle: 'Your Orders,',
        desktopDescription: 'Our Priority',
    },
] as const;

export function DeliveryDetailsModal() {
    const { deliveryArtworkUrl } = usePage().props;
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
            <DialogContent className="max-h-[92dvh] gap-0 overflow-y-auto rounded-3xl border-0 bg-white p-0 shadow-[0_32px_100px_rgba(15,23,42,0.38)] sm:max-w-[calc(100%-3rem)] lg:max-w-[1000px] lg:rounded-xl [&>button]:top-4 [&>button]:right-4 [&>button]:z-20 [&>button]:grid [&>button]:size-10 [&>button]:place-items-center [&>button]:rounded-full [&>button]:bg-white/90 [&>button]:text-slate-700 [&>button]:opacity-100 [&>button]:shadow-md [&>button]:hover:bg-white lg:[&>button]:bg-transparent lg:[&>button]:shadow-none [&>button_svg]:size-5 lg:[&>button_svg]:size-7">
                <div className="relative isolate grid lg:min-h-[618px] lg:overflow-hidden lg:[font-family:Arial,sans-serif]">
                    <div className="flex flex-col justify-center px-6 py-10 sm:px-10 sm:py-12 lg:block lg:px-12 lg:pt-28 lg:pb-32">
                        <p className="text-xs font-black tracking-[0.25em] text-[#f0440b] uppercase sm:text-sm lg:text-base lg:font-semibold lg:tracking-[0.18em]">
                            Shop anywhere in Sri Lanka
                        </p>
                        <DialogTitle className="mt-3 text-4xl leading-[0.96] font-black tracking-[-0.05em] text-[#08264c] sm:text-5xl lg:relative lg:z-10 lg:mt-2 lg:text-[60px] lg:leading-[1.1] lg:tracking-[-0.055em] lg:whitespace-nowrap">
                            Island Wide{' '}
                            <span className="text-[#f0440b]">Delivery</span>
                        </DialogTitle>
                        <DialogDescription className="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg lg:relative lg:z-10 lg:mt-3 lg:max-w-[490px] lg:text-[19px] lg:leading-[1.35] lg:text-[#505050]">
                            <span className="lg:hidden">
                                Your favourite products, delivered safely to
                                your doorstep — anywhere in Sri Lanka.
                            </span>
                            <span className="hidden lg:inline">
                                Your favourite products, delivered to your
                                doorstep — anywhere in Sri Lanka.
                            </span>
                        </DialogDescription>

                        <div className="mt-7 flex items-center gap-4 rounded-3xl border border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50 px-5 py-5 sm:gap-6 sm:px-7 lg:relative lg:z-10 lg:mt-5 lg:h-[136px] lg:w-[510px] lg:gap-3 lg:rounded-[22px] lg:border-0 lg:bg-[#fff3ed] lg:bg-none lg:px-5 lg:py-4">
                            <span className="grid size-16 shrink-0 place-items-center rounded-2xl bg-white text-[#f0440b] shadow-sm sm:size-20 lg:w-28 lg:bg-transparent lg:shadow-none">
                                <Truck
                                    aria-hidden="true"
                                    className="size-9 sm:size-11 lg:size-24 lg:stroke-[1.4]"
                                />
                            </span>
                            <div className="lg:relative lg:flex lg:flex-wrap lg:items-start lg:gap-x-1">
                                <p className="text-sm font-black text-[#08264c] sm:text-base lg:pt-1 lg:text-[25px] lg:tracking-tight">
                                    Only
                                </p>
                                <p className="text-4xl leading-none font-black tracking-[-0.05em] text-[#f0440b] sm:text-5xl lg:text-[72px] lg:leading-none lg:tracking-[-0.055em]">
                                    Rs. 200
                                </p>
                                <p className="mt-1 text-sm font-bold text-[#08264c] sm:text-base lg:mt-0 lg:ml-auto lg:text-[18px] lg:tracking-tight">
                                    Island Wide Delivery Charges
                                </p>
                            </div>
                        </div>

                        <div className="mt-7 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-0 sm:divide-x sm:divide-slate-200 lg:relative lg:z-10 lg:mt-8 lg:w-[510px]">
                            {deliveryBenefits.map(
                                ({
                                    icon: Icon,
                                    title,
                                    description,
                                    desktopIcon: DesktopIcon,
                                    desktopTitle,
                                    desktopDescription,
                                }) => (
                                    <div
                                        key={title}
                                        className="flex items-center gap-3 sm:px-4 sm:first:pl-0 sm:last:pr-0 lg:gap-2 lg:px-5"
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="size-8 shrink-0 text-[#f0440b] lg:hidden"
                                        />
                                        <DesktopIcon
                                            aria-hidden="true"
                                            className="hidden size-10 shrink-0 text-[#ff4b0a] lg:block"
                                            strokeWidth={1.8}
                                        />
                                        <span className="text-sm leading-5 text-[#08264c] lg:hidden">
                                            <strong className="block font-extrabold">
                                                {title}
                                            </strong>
                                            {description}
                                        </span>
                                        <span className="hidden text-[14px] leading-[1.2] text-[#061d40] lg:block">
                                            {desktopTitle}
                                            <br />
                                            {desktopDescription}
                                        </span>
                                    </div>
                                ),
                            )}
                        </div>

                        <Link
                            href={listingsIndex()}
                            onClick={() => setOpen(false)}
                            className="mt-8 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#ff4b0a] to-[#ff6d00] px-8 text-base font-black text-white shadow-[0_12px_28px_rgba(240,68,11,0.25)] transition hover:brightness-95 focus-visible:ring-2 focus-visible:ring-[#f0440b] focus-visible:ring-offset-2 focus-visible:outline-none sm:w-fit sm:min-w-72 sm:text-lg lg:absolute lg:bottom-7 lg:left-1/2 lg:z-10 lg:mt-0 lg:h-[60px] lg:w-[308px] lg:-translate-x-1/2 lg:gap-4 lg:bg-[#ff4b00] lg:bg-none lg:text-[23px] lg:font-semibold lg:shadow-none"
                        >
                            Start Shopping
                            <ArrowRight
                                aria-hidden="true"
                                className="size-5 lg:hidden"
                            />
                            <ChevronRight
                                aria-hidden="true"
                                className="hidden size-7 lg:block"
                            />
                        </Link>
                    </div>

                    <picture
                        className="pointer-events-none absolute top-5 right-0 -z-10 hidden h-[565px] w-[460px] lg:block"
                        aria-hidden="true"
                    >
                        <source
                            media="(min-width: 1024px)"
                            srcSet={deliveryArtworkUrl}
                        />
                        <img
                            src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                            alt=""
                            width={1145}
                            height={1374}
                            className="size-full object-contain"
                        />
                    </picture>
                </div>
            </DialogContent>
        </Dialog>
    );
}
