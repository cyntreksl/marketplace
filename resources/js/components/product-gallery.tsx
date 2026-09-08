import { ChevronLeft, ChevronRight, Maximize2, ImageOff } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { StorefrontListing } from '@/types';

export function ProductGallery({
    listing,
    featuredImageUrl,
}: {
    listing: StorefrontListing;
    featuredImageUrl?: string;
}) {
    const [index, setIndex] = useState(0);
    const media = featuredImageUrl
        ? [
              {
                  path: 'selected-variant',
                  url: featuredImageUrl,
                  thumbnailUrl: featuredImageUrl,
                  cardUrl: featuredImageUrl,
                  card2xUrl: featuredImageUrl,
              },
              ...listing.media.filter(
                  (item) => item.cardUrl !== featuredImageUrl,
              ),
          ]
        : listing.media;
    const selected = media[index];
    const move = (direction: number) =>
        setIndex(
            (current) => (current + direction + media.length) % media.length,
        );

    return (
        <div className="grid min-w-0 gap-2 sm:grid-cols-[4.5rem_minmax(0,1fr)] sm:gap-3">
            <Dialog>
                <div className="relative order-1 overflow-hidden rounded-xl border border-slate-200 bg-white sm:order-2">
                    {selected ? (
                        <DialogTrigger asChild>
                            <button
                                className="group relative block aspect-square w-full cursor-zoom-in focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:outline-none"
                                aria-label="Open fullscreen gallery"
                            >
                                <img
                                    src={selected.card2xUrl}
                                    srcSet={`${selected.cardUrl} 640w, ${selected.card2xUrl} 1280w`}
                                    sizes="(min-width: 1280px) 30vw, (min-width: 1024px) 45vw, 100vw"
                                    alt={listing.title}
                                    width={1280}
                                    height={1280}
                                    fetchPriority="high"
                                    className="size-full object-contain p-2 transition-transform duration-300 group-hover:scale-105 motion-reduce:transform-none sm:p-5"
                                />
                                <span className="absolute right-3 bottom-3 grid size-10 place-items-center rounded-full border border-slate-100 bg-white/90 text-slate-600 shadow-sm sm:right-4 sm:bottom-4 sm:size-11">
                                    <Maximize2 className="size-5" />
                                </span>
                            </button>
                        </DialogTrigger>
                    ) : (
                        <div className="grid aspect-square place-content-center gap-3 text-center text-slate-500">
                            <ImageOff className="mx-auto size-10" />
                            <p className="text-base">
                                Product image coming soon
                            </p>
                        </div>
                    )}
                    {media.length > 1 && (
                        <span className="absolute bottom-3 left-3 rounded-full bg-white/90 px-2.5 py-1.5 text-sm font-semibold text-slate-600 sm:bottom-4 sm:left-4 sm:px-3 sm:py-2">
                            {index + 1} / {media.length}
                        </span>
                    )}
                </div>
                {selected && (
                    <DialogContent
                        className="max-w-[95vw] border-0 bg-white p-4 sm:max-w-5xl"
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowLeft') {
                                move(-1);
                            }

                            if (event.key === 'ArrowRight') {
                                move(1);
                            }
                        }}
                    >
                        <DialogTitle className="pr-8 text-base">
                            {listing.title}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            Product gallery. Use the arrow keys to browse
                            images.
                        </DialogDescription>
                        <div className="relative flex items-center justify-center">
                            <img
                                src={selected.url}
                                alt={`${listing.title}, image ${index + 1}`}
                                className="max-h-[75vh] w-full object-contain"
                            />
                            {media.length > 1 && (
                                <>
                                    <button
                                        type="button"
                                        aria-label="Previous image"
                                        onClick={() => move(-1)}
                                        className="absolute left-0 grid size-11 place-items-center rounded-full border bg-white shadow"
                                    >
                                        <ChevronLeft />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Next image"
                                        onClick={() => move(1)}
                                        className="absolute right-0 grid size-11 place-items-center rounded-full border bg-white shadow"
                                    >
                                        <ChevronRight />
                                    </button>
                                </>
                            )}
                        </div>
                    </DialogContent>
                )}
            </Dialog>
            {media.length > 0 && (
                <div
                    aria-label="Product images"
                    className="order-2 flex gap-2 overflow-x-auto px-0.5 py-1 sm:order-1 sm:max-h-[32rem] sm:flex-col sm:overflow-y-auto sm:p-1"
                >
                    {media.map((item, mediaIndex) => (
                        <button
                            key={item.path}
                            type="button"
                            onClick={() => setIndex(mediaIndex)}
                            aria-label={`View image ${mediaIndex + 1}`}
                            aria-pressed={index === mediaIndex}
                            className={`size-16 shrink-0 overflow-hidden rounded-lg border bg-white p-1 transition ${index === mediaIndex ? 'border-orange-500 ring-2 ring-orange-100' : 'border-slate-200 hover:border-orange-300'}`}
                        >
                            <img
                                src={item.thumbnailUrl}
                                alt=""
                                width={80}
                                height={80}
                                loading="lazy"
                                className="size-full object-contain"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
