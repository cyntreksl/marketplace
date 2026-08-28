import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Gavel, MapPin, ShoppingBag, Star } from 'lucide-react';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { login } from '@/routes';
import { show as listingShow } from '@/routes/listings';
import type { StorefrontListing } from '@/types';

export function ListingCard({ listing }: { listing: StorefrontListing }) {
    const { auth } = usePage().props;
    const detailUrl = listingShow(listing.slug);

    return (
        <article className="group flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/50 transition duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl hover:shadow-primary/10 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
            <Link
                href={detailUrl}
                className="relative block aspect-[4/3] overflow-hidden bg-slate-100 dark:bg-slate-800"
            >
                {listing.media[0] ? (
                    <img
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        src={listing.media[0].cardUrl}
                        srcSet={`${listing.media[0].cardUrl} 480w, ${listing.media[0].card2xUrl} 960w`}
                        sizes="(min-width: 1280px) 20vw, (min-width: 640px) 33vw, 50vw"
                        alt={listing.title}
                        loading="lazy"
                    />
                ) : (
                    <div className="grid h-full place-items-center bg-gradient-to-br from-primary/15 via-white to-amber-100 px-5 text-center text-sm font-bold text-slate-500 dark:from-primary/20 dark:via-slate-900 dark:to-slate-800">
                        Product image coming soon
                    </div>
                )}
                <span className="absolute top-3 left-3 rounded-full bg-slate-950/85 px-2.5 py-1 text-[10px] font-extrabold tracking-wider text-white uppercase shadow-sm backdrop-blur">
                    {listing.listingType === 'auction'
                        ? 'Auction'
                        : listing.condition}
                </span>
                {listing.discountPercentage !== null && (
                    <span className="absolute top-3 right-3 rounded-full bg-amber-400 px-2.5 py-1 text-xs font-black text-slate-950 shadow-sm">
                        {listing.discountPercentage}% off
                    </span>
                )}
            </Link>

            <div className="flex flex-1 flex-col p-4">
                <div className="flex items-center justify-between gap-2 text-xs text-slate-500">
                    <span className="flex items-center gap-1 font-bold text-amber-600 dark:text-amber-400">
                        <Star className="size-3.5 fill-current" />
                        {listing.ratingAverage?.toFixed(1) ?? 'New'}
                        {listing.reviewCount > 0 && (
                            <span className="font-medium text-slate-400">
                                ({listing.reviewCount})
                            </span>
                        )}
                    </span>
                    <span className="flex min-w-0 items-center gap-1 truncate">
                        <MapPin className="size-3 shrink-0" />
                        {listing.location}
                    </span>
                </div>

                <Link
                    href={detailUrl}
                    className="mt-2 line-clamp-2 min-h-12 leading-6 font-bold text-slate-900 transition hover:text-primary dark:text-white"
                >
                    {listing.title}
                </Link>
                <p className="mt-1 truncate text-xs text-slate-500">
                    {listing.seller?.store_name ?? 'Marketplace seller'}
                </p>

                <div className="mt-3 flex min-h-12 items-end gap-2">
                    <p className="text-xl font-black text-primary">
                        Rs.{' '}
                        {Number(listing.effectivePrice ?? 0).toLocaleString()}
                    </p>
                    {listing.salePrice && (
                        <p className="pb-0.5 text-xs font-semibold text-slate-400 line-through">
                            Rs. {Number(listing.price ?? 0).toLocaleString()}
                        </p>
                    )}
                </div>

                <div className="mt-4">
                    {listing.listingType === 'auction' ? (
                        <Link
                            href={detailUrl}
                            className="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-primary dark:bg-white dark:text-slate-950 dark:hover:bg-amber-300"
                        >
                            <Gavel className="size-4" /> View auction
                        </Link>
                    ) : auth.user ? (
                        <Form
                            {...addCartItem.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="listing_id"
                                        value={listing.id}
                                    />
                                    <input
                                        type="hidden"
                                        name="quantity"
                                        value="1"
                                    />
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-black text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60"
                                    >
                                        <ShoppingBag className="size-4" />{' '}
                                        {processing ? 'Adding…' : 'Add to cart'}
                                    </button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <Link
                            href={login()}
                            className="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-primary/30 px-4 text-sm font-black text-primary transition hover:bg-primary hover:text-primary-foreground"
                        >
                            Sign in / view deal{' '}
                            <ArrowRight className="size-4" />
                        </Link>
                    )}
                </div>
            </div>
        </article>
    );
}
