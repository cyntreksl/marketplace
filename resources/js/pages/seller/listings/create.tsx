import { Form, Head, Link } from '@inertiajs/react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/SellerListingController';
import { StorefrontLayout } from '@/components/storefront-layout';

type Category = { id: number; name: string; commission_percentage: string };
type Brand = { id: number; name: string };

export default function CreateSellerListing({
    categories,
    brands,
}: {
    categories: Category[];
    brands: Brand[];
}) {
    return (
        <StorefrontLayout title="Create listing">
            <Head title="Create listing" />
            <main className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                <Link
                    href={index()}
                    className="text-sm font-bold text-amber-700"
                >
                    ← Your listings
                </Link>
                <h1 className="mt-4 text-4xl font-black">Create a listing</h1>
                <p className="mt-2 text-stone-600 dark:text-stone-300">
                    Save a complete draft, then send it to our team when your
                    seller account is approved.
                </p>
                <Form
                    {...store.form()}
                    className="mt-8 grid gap-5 rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <label className="grid gap-2 font-semibold">
                                    Title
                                    <input
                                        required
                                        name="title"
                                        className="rounded-lg border bg-transparent p-3"
                                    />
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Location
                                    <input
                                        required
                                        name="location"
                                        placeholder="Colombo"
                                        className="rounded-lg border bg-transparent p-3"
                                    />
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Category
                                    <select
                                        required
                                        name="category_id"
                                        className="rounded-lg border bg-transparent p-3"
                                    >
                                        <option value="">
                                            Choose category
                                        </option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name} (
                                                {category.commission_percentage}
                                                % commission)
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Brand
                                    <select
                                        name="brand_id"
                                        className="rounded-lg border bg-transparent p-3"
                                    >
                                        <option value="">No brand</option>
                                        {brands.map((brand) => (
                                            <option
                                                key={brand.id}
                                                value={brand.id}
                                            >
                                                {brand.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Condition
                                    <select
                                        required
                                        name="condition"
                                        className="rounded-lg border bg-transparent p-3"
                                    >
                                        <option value="new">New</option>
                                        <option value="used">Used</option>
                                        <option value="refurbished">
                                            Refurbished
                                        </option>
                                    </select>
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Sale method
                                    <select
                                        required
                                        name="listing_type"
                                        className="rounded-lg border bg-transparent p-3"
                                    >
                                        <option value="buy_now">Buy Now</option>
                                        <option value="auction">Auction</option>
                                    </select>
                                </label>
                            </div>
                            <label className="grid gap-2 font-semibold">
                                Description
                                <textarea
                                    required
                                    name="description"
                                    className="min-h-36 rounded-lg border bg-transparent p-3"
                                />
                            </label>
                            <label className="grid gap-2 font-semibold">
                                Warranty (optional)
                                <input
                                    name="warranty"
                                    className="rounded-lg border bg-transparent p-3"
                                />
                            </label>
                            <section className="grid gap-4 rounded-xl bg-stone-50 p-4 dark:bg-stone-950">
                                <p className="font-bold">Buy Now details</p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="grid gap-2">
                                        Price (LKR)
                                        <input
                                            name="price"
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-2">
                                        Stock
                                        <input
                                            name="stock_quantity"
                                            type="number"
                                            min="1"
                                            step="1"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                </div>
                            </section>
                            <section className="grid gap-4 rounded-xl bg-stone-50 p-4 dark:bg-stone-950">
                                <p className="font-bold">Auction details</p>
                                <p className="text-sm text-stone-500">
                                    Complete these only when the sale method is
                                    Auction.
                                </p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="grid gap-2">
                                        Starting price
                                        <input
                                            name="starting_price"
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-2">
                                        Reserve price
                                        <input
                                            name="reserve_price"
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-2">
                                        Minimum increment
                                        <input
                                            name="minimum_increment"
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-2">
                                        Starts at
                                        <input
                                            name="starts_at"
                                            type="datetime-local"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-2 sm:col-span-2">
                                        Ends at
                                        <input
                                            name="ends_at"
                                            type="datetime-local"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                </div>
                            </section>
                            {Object.values(errors).map((error) => (
                                <p className="text-sm text-red-600" key={error}>
                                    {error}
                                </p>
                            ))}
                            <button
                                disabled={processing}
                                className="rounded-full bg-amber-400 px-5 py-3 font-bold text-stone-950 disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Save draft'}
                            </button>
                        </>
                    )}
                </Form>
            </main>
        </StorefrontLayout>
    );
}
