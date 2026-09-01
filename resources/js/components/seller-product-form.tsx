import { Link, useForm } from '@inertiajs/react';
import {
    Boxes,
    ImagePlus,
    Info,
    PackageCheck,
    Plus,
    Save,
    Search,
    Send,
    Settings2,
    Tags,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import { CategoryPicker } from '@/components/category-picker';
import type { CategoryOption } from '@/components/category-picker';
import {
    RichTextEditor,
    sanitizeRichText,
} from '@/components/rich-text-editor';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { index as productsIndex } from '@/routes/seller/listings';

type Brand = { id: number; name: string };
type ListingMedia = { id: number; path: string; url: string };
type ListingImageCrop = { x: number; y: number; width: number; height: number };
type ListingImageSize = { width: number; height: number };
type VariantOption = { name: string; values: string[] };
type VariantRow = {
    selections: string[];
    sku: string;
    stock_quantity: number | '';
    image: File | null;
    remove_image: boolean;
    existing_image: ListingMedia | null;
};

type StoredVariantOption = {
    name: string;
    position: number;
    values: { value: string; position: number }[];
};

type StoredVariant = {
    sku: string | null;
    stock_quantity: number;
    position: number;
    option_values: {
        value: string;
        option: { position: number };
    }[];
    image: ListingMedia | null;
};

export type SellerProductFormListing = {
    title: string | null;
    sku: string | null;
    barcode: string | null;
    category_id: number | null;
    brand_id: number | null;
    brand_name: string | null;
    short_description: string | null;
    description: string | null;
    condition: string | null;
    product_type: 'simple' | 'variant';
    location: string | null;
    warranty: string | null;
    stock_quantity: number;
    reserved_quantity: number;
    low_stock_threshold: number;
    allow_backorders: boolean;
    is_active: boolean;
    is_featured: boolean;
    is_best_seller: boolean;
    is_new_arrival: boolean;
    price: string | null;
    sale_price: string | null;
    cost_price: string | null;
    meta_title: string | null;
    meta_description: string | null;
    media?: ListingMedia[];
    variant_options?: StoredVariantOption[];
    variants?: StoredVariant[];
};

type FormDefinition = { action: string; method: 'post' | 'put' };
type ProductFormData = {
    category_id: number | '';
    brand_id: number | null;
    brand_name: string;
    sku: string;
    barcode: string;
    title: string;
    short_description: string;
    description: string;
    condition: string;
    product_type: 'simple' | 'variant';
    location: string;
    warranty: string;
    stock_quantity: number | '';
    selling_price: string;
    compare_price: string;
    cost_price: string;
    low_stock_threshold: number | '';
    allow_backorders: boolean;
    is_active: boolean;
    is_featured: boolean;
    is_best_seller: boolean;
    is_new_arrival: boolean;
    meta_title: string;
    meta_description: string;
    variant_options: VariantOption[];
    variants: VariantRow[];
    images: File[];
    image_crops: ListingImageCrop[];
    removed_media_ids: number[];
    submit_for_review: boolean;
};

export function SellerProductForm({
    form: formDefinition,
    initialCategory,
    brands,
    listing,
    canSubmit,
}: {
    form: FormDefinition;
    initialCategory: CategoryOption | null;
    brands: Brand[];
    listing?: SellerProductFormListing;
    canSubmit: boolean;
}) {
    const [selectedCategory, setSelectedCategory] =
        useState<CategoryOption | null>(initialCategory);
    const [isDraggingImages, setIsDraggingImages] = useState(false);
    const [imageSizes, setImageSizes] = useState<ListingImageSize[]>([]);
    const [imageError, setImageError] = useState<string | null>(null);
    const [cropImageIndex, setCropImageIndex] = useState<number | null>(null);
    const [cropPosition, setCropPosition] = useState<Point>({ x: 0, y: 0 });
    const [cropZoom, setCropZoom] = useState(1);
    const [draftCrop, setDraftCrop] = useState<ListingImageCrop | null>(null);
    const existingOptions = useMemo(
        () =>
            [...(listing?.variant_options ?? [])]
                .sort((first, second) => first.position - second.position)
                .map((option) => ({
                    name: option.name,
                    values: [...option.values]
                        .sort(
                            (first, second) => first.position - second.position,
                        )
                        .map((value) => value.value),
                })),
        [listing?.variant_options],
    );
    const existingVariants = useMemo(
        () =>
            [...(listing?.variants ?? [])]
                .sort((first, second) => first.position - second.position)
                .map((variant) => ({
                    selections: [...variant.option_values]
                        .sort(
                            (first, second) =>
                                first.option.position - second.option.position,
                        )
                        .map((value) => value.value),
                    sku: variant.sku ?? '',
                    stock_quantity: variant.stock_quantity,
                    image: null,
                    remove_image: false,
                    existing_image: variant.image,
                })),
        [listing?.variants],
    );
    const manuallyEditedSkus = useRef(
        new Set(
            existingVariants.map((variant) =>
                combinationKey(variant.selections, existingOptions),
            ),
        ),
    );
    const form = useForm<ProductFormData>({
        category_id: listing?.category_id ?? '',
        brand_id: listing?.brand_id ?? null,
        brand_name: listing?.brand_name ?? '',
        sku: listing?.sku ?? '',
        barcode: listing?.barcode ?? '',
        title: listing?.title ?? '',
        short_description: listing?.short_description ?? '',
        description: listing?.description ?? '',
        condition: listing?.condition ?? 'new',
        product_type: listing?.product_type ?? 'simple',
        location: listing?.location ?? '',
        warranty: listing?.warranty ?? '',
        stock_quantity: listing?.stock_quantity ?? '',
        selling_price: listing?.sale_price ?? listing?.price ?? '',
        compare_price: listing?.sale_price ? (listing.price ?? '') : '',
        cost_price: listing?.cost_price ?? '',
        low_stock_threshold: listing?.low_stock_threshold ?? 0,
        allow_backorders: listing?.allow_backorders ?? false,
        is_active: listing?.is_active ?? true,
        is_featured: listing?.is_featured ?? false,
        is_best_seller: listing?.is_best_seller ?? false,
        is_new_arrival: listing?.is_new_arrival ?? false,
        meta_title: listing?.meta_title ?? '',
        meta_description: listing?.meta_description ?? '',
        variant_options: existingOptions,
        variants: existingVariants,
        images: [],
        image_crops: [],
        removed_media_ids: [],
        submit_for_review: false,
    });
    const imagePreviewUrls = useMemo(
        () => form.data.images.map((file) => URL.createObjectURL(file)),
        [form.data.images],
    );

    useEffect(
        () => () => imagePreviewUrls.forEach(URL.revokeObjectURL),
        [imagePreviewUrls],
    );

    const visibleExistingMedia = (listing?.media ?? []).filter(
        (media) => !form.data.removed_media_ids.includes(media.id),
    );
    const totalPhotoCount =
        visibleExistingMedia.length + form.data.images.length;
    const combinationCount = calculateCombinationCount(
        form.data.variant_options,
    );
    const combinations = useMemo(
        () =>
            combinationCount > 100
                ? []
                : buildCombinations(form.data.variant_options),
        [combinationCount, form.data.variant_options],
    );
    const variantOptions = form.data.variant_options;
    const variantRows = form.data.variants;
    const productType = form.data.product_type;
    const baseSku = form.data.sku;
    const setFormData = form.setData;

    useEffect(() => {
        if (productType !== 'variant') {
            if (variantRows.length > 0) {
                setFormData('variants', []);
            }

            return;
        }

        const existingByKey = new Map(
            variantRows.map((variant) => [
                combinationKey(variant.selections, variantOptions),
                variant,
            ]),
        );
        const nextVariants = combinations.map((selections) => {
            const key = combinationKey(selections, variantOptions);
            const existing = existingByKey.get(key);

            return {
                selections,
                sku:
                    existing && manuallyEditedSkus.current.has(key)
                        ? existing.sku
                        : suggestedSku(baseSku, selections),
                stock_quantity: existing?.stock_quantity ?? 0,
                image: existing?.image ?? null,
                remove_image: existing?.remove_image ?? false,
                existing_image: existing?.existing_image ?? null,
            };
        });

        if (JSON.stringify(nextVariants) !== JSON.stringify(variantRows)) {
            setFormData('variants', nextVariants);
        }
    }, [
        baseSku,
        combinations,
        productType,
        setFormData,
        variantOptions,
        variantRows,
    ]);

    const aggregateStock =
        form.data.product_type === 'variant'
            ? form.data.variants.reduce(
                  (total, variant) =>
                      total + Number(variant.stock_quantity || 0),
                  0,
              )
            : Number(form.data.stock_quantity || 0);
    const availableStock = Math.max(
        0,
        aggregateStock - (listing?.reserved_quantity ?? 0),
    );
    const stockStatus =
        availableStock <= 0
            ? form.data.allow_backorders
                ? 'Backorder'
                : 'Out of stock'
            : availableStock <= Number(form.data.low_stock_threshold || 0)
              ? 'Low stock'
              : 'In stock';
    const cropImage =
        cropImageIndex === null
            ? null
            : {
                  url: imagePreviewUrls[cropImageIndex],
                  size: imageSizes[cropImageIndex],
                  crop: form.data.image_crops[cropImageIndex],
              };
    const maximumCropZoom = cropImage?.size
        ? Math.max(
              1,
              Math.min(
                  centeredFourByThreeCrop(cropImage.size).width / 800,
                  centeredFourByThreeCrop(cropImage.size).height / 600,
              ),
          )
        : 1;

    function setField<Key extends keyof ProductFormData>(
        key: Key,
        value: ProductFormData[Key],
    ): void {
        form.setData({ ...form.data, [key]: value });
        form.clearErrors(key);
    }

    function updateOption(
        index: number,
        changes: Partial<VariantOption>,
    ): void {
        setField(
            'variant_options',
            form.data.variant_options.map((option, optionIndex) =>
                optionIndex === index ? { ...option, ...changes } : option,
            ),
        );
    }

    function removeOption(index: number): void {
        setField(
            'variant_options',
            form.data.variant_options.filter(
                (_, optionIndex) => optionIndex !== index,
            ),
        );
    }

    function updateVariant(index: number, changes: Partial<VariantRow>): void {
        setField(
            'variants',
            form.data.variants.map((variant, variantIndex) =>
                variantIndex === index ? { ...variant, ...changes } : variant,
            ),
        );
    }

    async function addImages(files: FileList | null): Promise<void> {
        if (!files) {
            return;
        }

        const remainingSlots = Math.max(0, 5 - totalPhotoCount);
        const incoming = Array.from(files).slice(0, remainingSlots);
        const prepared = (
            await Promise.all(
                incoming.map(async (file) => {
                    if (
                        !['image/jpeg', 'image/png', 'image/webp'].includes(
                            file.type,
                        ) ||
                        file.size > 5 * 1024 * 1024
                    ) {
                        return null;
                    }

                    const size = await readImageSize(file).catch(() => null);

                    if (
                        size === null ||
                        size.width < 800 ||
                        size.height < 600 ||
                        size.width > 6000 ||
                        size.height > 6000
                    ) {
                        return null;
                    }

                    return { file, size, crop: centeredFourByThreeCrop(size) };
                }),
            )
        ).filter((image) => image !== null);

        form.setData({
            ...form.data,
            images: [...form.data.images, ...prepared.map(({ file }) => file)],
            image_crops: [
                ...form.data.image_crops,
                ...prepared.map(({ crop }) => crop),
            ],
        });
        setImageSizes((sizes) => [
            ...sizes,
            ...prepared.map(({ size }) => size),
        ]);
        setImageError(
            prepared.length === incoming.length
                ? null
                : 'Some images were skipped. Use JPG, PNG, or WebP files from 800 × 600 to 6000 × 6000 pixels and no larger than 5 MB.',
        );
    }

    function removeNewImage(index: number): void {
        form.setData({
            ...form.data,
            images: form.data.images.filter(
                (_, imageIndex) => imageIndex !== index,
            ),
            image_crops: form.data.image_crops.filter(
                (_, imageIndex) => imageIndex !== index,
            ),
        });
        setImageSizes((sizes) =>
            sizes.filter((_, imageIndex) => imageIndex !== index),
        );
    }

    function submit(submitForReview: boolean): void {
        form.transform((data) => ({
            ...data,
            description: sanitizeRichText(data.description),
            variants: data.variants.map((variant) => ({
                selections: variant.selections,
                sku: variant.sku,
                stock_quantity: variant.stock_quantity,
                image: variant.image,
                remove_image: variant.remove_image,
            })),
            submit_for_review: submitForReview,
        }));
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onError: () => {
                window.requestAnimationFrame(() => {
                    document
                        .querySelector('[aria-invalid="true"]')
                        ?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                });
            },
        };

        if (formDefinition.method === 'put') {
            form.put(formDefinition.action, options);
        } else {
            form.post(formDefinition.action, options);
        }
    }

    function errorFor(field: string): string | undefined {
        return Object.entries(form.errors).find(
            ([errorField]) =>
                errorField === field || errorField.startsWith(`${field}.`),
        )?.[1];
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit(true);
            }}
            className="mt-6 pb-4"
        >
            <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_23rem]">
                <div className="grid gap-5">
                    <FormCard
                        title="Basic Information"
                        icon={<Info className="size-5" />}
                    >
                        <div className="grid gap-5 md:grid-cols-3">
                            <Field
                                label="Product Name"
                                error={errorFor('title')}
                                required
                                className="md:col-span-3"
                            >
                                <TextInput
                                    value={form.data.title}
                                    onChange={(value) =>
                                        setField('title', value)
                                    }
                                    placeholder="Enter product name"
                                    error={errorFor('title')}
                                />
                            </Field>
                            <Field label="SKU" error={errorFor('sku')} required>
                                <TextInput
                                    value={form.data.sku}
                                    onChange={(value) => setField('sku', value)}
                                    placeholder="Enter SKU"
                                    error={errorFor('sku')}
                                />
                            </Field>
                            <Field label="Barcode" error={errorFor('barcode')}>
                                <TextInput
                                    value={form.data.barcode}
                                    onChange={(value) =>
                                        setField('barcode', value)
                                    }
                                    placeholder="Enter barcode (optional)"
                                    error={errorFor('barcode')}
                                />
                            </Field>
                            <Field
                                label="Brand"
                                error={errorFor('brand_id')}
                                required
                            >
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        list="product-brand-options"
                                        value={
                                            brands.find(
                                                (brand) =>
                                                    brand.id ===
                                                    form.data.brand_id,
                                            )?.name ?? form.data.brand_name
                                        }
                                        onChange={(event) => {
                                            const value = event.target.value;
                                            const exactBrand = brands.find(
                                                (brand) =>
                                                    brand.name.toLocaleLowerCase() ===
                                                    value.toLocaleLowerCase(),
                                            );
                                            form.setData({
                                                ...form.data,
                                                brand_id:
                                                    exactBrand?.id ?? null,
                                                brand_name: exactBrand
                                                    ? ''
                                                    : value,
                                            });
                                            form.clearErrors(
                                                'brand_id',
                                                'brand_name',
                                            );
                                        }}
                                        placeholder="Select or enter brand"
                                        aria-invalid={
                                            errorFor('brand_id')
                                                ? true
                                                : undefined
                                        }
                                        className={inputClass(
                                            errorFor('brand_id'),
                                            'pl-10',
                                        )}
                                    />
                                    <datalist id="product-brand-options">
                                        {brands.map((brand) => (
                                            <option
                                                key={brand.id}
                                                value={brand.name}
                                            />
                                        ))}
                                    </datalist>
                                </div>
                            </Field>
                            <div className="md:col-span-2">
                                <CategoryPicker
                                    selected={selectedCategory}
                                    onSelect={(category) => {
                                        setSelectedCategory(category);
                                        setField(
                                            'category_id',
                                            category?.id ?? '',
                                        );
                                    }}
                                    error={errorFor('category_id')}
                                    label="Category *"
                                />
                            </div>
                            <Field label="Product Type" required>
                                <select
                                    value={form.data.product_type}
                                    onChange={(event) =>
                                        setField(
                                            'product_type',
                                            event.target.value as
                                                'simple' | 'variant',
                                        )
                                    }
                                    className={inputClass()}
                                >
                                    <option value="simple">
                                        Simple Product
                                    </option>
                                    <option value="variant">
                                        Variant Product
                                    </option>
                                </select>
                            </Field>
                            <Field
                                label="Condition"
                                error={errorFor('condition')}
                                required
                            >
                                <select
                                    value={form.data.condition}
                                    onChange={(event) =>
                                        setField(
                                            'condition',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass(
                                        errorFor('condition'),
                                    )}
                                >
                                    <option value="new">New</option>
                                    <option value="used">Used</option>
                                    <option value="refurbished">
                                        Refurbished
                                    </option>
                                </select>
                            </Field>
                            <Field
                                label="Location"
                                error={errorFor('location')}
                                required
                            >
                                <TextInput
                                    value={form.data.location}
                                    onChange={(value) =>
                                        setField('location', value)
                                    }
                                    placeholder="e.g. Colombo"
                                    error={errorFor('location')}
                                />
                            </Field>
                            <Field
                                label="Warranty"
                                error={errorFor('warranty')}
                            >
                                <TextInput
                                    value={form.data.warranty}
                                    onChange={(value) =>
                                        setField('warranty', value)
                                    }
                                    placeholder="Warranty details"
                                    error={errorFor('warranty')}
                                />
                            </Field>
                            <Field
                                label="Short Description"
                                error={errorFor('short_description')}
                                className="md:col-span-3"
                            >
                                <div className="relative">
                                    <input
                                        value={form.data.short_description}
                                        onChange={(event) =>
                                            setField(
                                                'short_description',
                                                event.target.value.slice(
                                                    0,
                                                    160,
                                                ),
                                            )
                                        }
                                        placeholder="Enter a short product summary"
                                        className={inputClass(
                                            errorFor('short_description'),
                                            'pr-16',
                                        )}
                                    />
                                    <span className="absolute top-1/2 right-3 -translate-y-1/2 text-xs text-slate-400">
                                        {form.data.short_description.length}/160
                                    </span>
                                </div>
                            </Field>
                            <Field
                                label="Full Description"
                                error={errorFor('description')}
                                required
                                className="md:col-span-3"
                            >
                                <RichTextEditor
                                    id="product-description"
                                    value={form.data.description}
                                    onChange={(value) =>
                                        setField('description', value)
                                    }
                                    placeholder="Enter product full description..."
                                    error={errorFor('description')}
                                />
                            </Field>
                        </div>
                    </FormCard>

                    <FormCard
                        title="Pricing & Stock"
                        icon={<Tags className="size-5" />}
                    >
                        <div className="grid gap-5 md:grid-cols-3">
                            <Field
                                label="Selling Price (LKR)"
                                error={errorFor('selling_price')}
                                required
                            >
                                <MoneyInput
                                    value={form.data.selling_price}
                                    onChange={(value) =>
                                        setField('selling_price', value)
                                    }
                                    error={errorFor('selling_price')}
                                />
                            </Field>
                            <Field
                                label="Compare Price (LKR)"
                                error={errorFor('compare_price')}
                            >
                                <MoneyInput
                                    value={form.data.compare_price}
                                    onChange={(value) =>
                                        setField('compare_price', value)
                                    }
                                    error={errorFor('compare_price')}
                                />
                            </Field>
                            <Field
                                label="Cost Price (LKR)"
                                error={errorFor('cost_price')}
                            >
                                <MoneyInput
                                    value={form.data.cost_price}
                                    onChange={(value) =>
                                        setField('cost_price', value)
                                    }
                                    error={errorFor('cost_price')}
                                />
                            </Field>
                            <Field
                                label="Stock Quantity"
                                error={errorFor('stock_quantity')}
                                required
                            >
                                <input
                                    type="number"
                                    min="0"
                                    value={
                                        form.data.product_type === 'variant'
                                            ? aggregateStock
                                            : form.data.stock_quantity
                                    }
                                    onChange={(event) =>
                                        setField(
                                            'stock_quantity',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                    disabled={
                                        form.data.product_type === 'variant'
                                    }
                                    className={inputClass(
                                        errorFor('stock_quantity'),
                                    )}
                                />
                                {form.data.product_type === 'variant' && (
                                    <p className="mt-1.5 text-xs text-slate-500">
                                        Calculated from the generated variants.
                                    </p>
                                )}
                            </Field>
                            <Field
                                label="Low Stock Alert"
                                error={errorFor('low_stock_threshold')}
                            >
                                <input
                                    type="number"
                                    min="0"
                                    value={form.data.low_stock_threshold}
                                    onChange={(event) =>
                                        setField(
                                            'low_stock_threshold',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                    className={inputClass(
                                        errorFor('low_stock_threshold'),
                                    )}
                                />
                            </Field>
                            <Field label="Stock Status">
                                <div className="flex h-12 items-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-emerald-400">
                                    {stockStatus}
                                </div>
                            </Field>
                            <div className="md:col-span-3">
                                <ToggleRow
                                    label="Allow Backorders"
                                    description="Allow customers to order when aggregate stock is exhausted."
                                    checked={form.data.allow_backorders}
                                    onChange={(checked) =>
                                        setField('allow_backorders', checked)
                                    }
                                />
                            </div>
                        </div>
                    </FormCard>

                    <FormCard
                        title="Product Variants"
                        icon={<Boxes className="size-5" />}
                    >
                        {form.data.product_type === 'simple' ? (
                            <button
                                type="button"
                                onClick={() =>
                                    setField('product_type', 'variant')
                                }
                                className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-primary/40 bg-primary/5 px-4 py-8 text-sm font-bold text-primary transition hover:bg-primary/10"
                            >
                                <Plus className="size-4" />
                                Add variants to this product
                            </button>
                        ) : (
                            <div className="grid gap-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p className="font-bold">
                                            Option groups
                                        </p>
                                        <p className="mt-1 text-sm text-slate-500">
                                            Add up to three groups. Combinations
                                            are generated automatically.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={
                                            form.data.variant_options.length >=
                                            3
                                        }
                                        onClick={() =>
                                            setField('variant_options', [
                                                ...form.data.variant_options,
                                                { name: '', values: [] },
                                            ])
                                        }
                                        className="inline-flex items-center justify-center gap-2 rounded-xl border border-primary px-4 py-2 text-sm font-bold text-primary disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        <Plus className="size-4" /> Add option
                                    </button>
                                </div>

                                {form.data.variant_options.length === 0 && (
                                    <div className="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500 dark:bg-slate-950">
                                        Start with an option such as Color,
                                        Size, or Material.
                                    </div>
                                )}

                                {form.data.variant_options.map(
                                    (option, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-[12rem_1fr_auto] dark:border-slate-700"
                                        >
                                            <TextInput
                                                value={option.name}
                                                onChange={(value) =>
                                                    updateOption(index, {
                                                        name: value,
                                                    })
                                                }
                                                placeholder="Option name"
                                                error={errorFor(
                                                    `variant_options.${index}.name`,
                                                )}
                                            />
                                            <TextInput
                                                value={option.values.join(', ')}
                                                onChange={(value) =>
                                                    updateOption(index, {
                                                        values: value
                                                            .split(',')
                                                            .map((item) =>
                                                                item.trim(),
                                                            ),
                                                    })
                                                }
                                                placeholder="Values separated by commas"
                                                error={errorFor(
                                                    `variant_options.${index}.values`,
                                                )}
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeOption(index)
                                                }
                                                aria-label={`Remove option ${index + 1}`}
                                                className="grid size-12 place-items-center rounded-xl border border-red-200 text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    ),
                                )}

                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-950">
                                    <span className="font-semibold">
                                        {combinationCount} generated combination
                                        {combinationCount === 1 ? '' : 's'}
                                    </span>
                                    <span
                                        className={cn(
                                            'font-bold',
                                            combinationCount > 100
                                                ? 'text-red-600'
                                                : 'text-slate-500',
                                        )}
                                    >
                                        Maximum 100
                                    </span>
                                </div>

                                {errorFor('variant_options') && (
                                    <ErrorText>
                                        {errorFor('variant_options')}
                                    </ErrorText>
                                )}
                                {errorFor('variants') && (
                                    <ErrorText>
                                        {errorFor('variants')}
                                    </ErrorText>
                                )}

                                {form.data.variants.length > 0 && (
                                    <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                                        <table className="w-full min-w-[48rem] text-left text-sm">
                                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950">
                                                <tr>
                                                    <th className="px-4 py-3">
                                                        Combination
                                                    </th>
                                                    <th className="w-28 px-4 py-3">
                                                        Image
                                                        <span className="ml-1 font-normal tracking-normal normal-case">
                                                            (optional)
                                                        </span>
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        SKU
                                                    </th>
                                                    <th className="w-36 px-4 py-3">
                                                        Stock
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                                {form.data.variants.map(
                                                    (variant, index) => {
                                                        const key =
                                                            combinationKey(
                                                                variant.selections,
                                                                form.data
                                                                    .variant_options,
                                                            );

                                                        return (
                                                            <tr key={key}>
                                                                <td className="px-4 py-3 font-semibold">
                                                                    {variant.selections.join(
                                                                        ' / ',
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <VariantImageInput
                                                                        combination={variant.selections.join(
                                                                            ' / ',
                                                                        )}
                                                                        file={
                                                                            variant.image
                                                                        }
                                                                        existingImage={
                                                                            variant.existing_image
                                                                        }
                                                                        removeExisting={
                                                                            variant.remove_image
                                                                        }
                                                                        onChange={(
                                                                            image,
                                                                        ) =>
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    image,
                                                                                    remove_image: false,
                                                                                },
                                                                            )
                                                                        }
                                                                        onRemove={() =>
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    image: null,
                                                                                    remove_image:
                                                                                        variant.existing_image !==
                                                                                        null,
                                                                                },
                                                                            )
                                                                        }
                                                                        error={errorFor(
                                                                            `variants.${index}.image`,
                                                                        )}
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        value={
                                                                            variant.sku
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) => {
                                                                            manuallyEditedSkus.current.add(
                                                                                key,
                                                                            );
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    sku: event
                                                                                        .target
                                                                                        .value,
                                                                                },
                                                                            );
                                                                        }}
                                                                        className={inputClass(
                                                                            errorFor(
                                                                                `variants.${index}.sku`,
                                                                            ),
                                                                            'h-10',
                                                                        )}
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        value={
                                                                            variant.stock_quantity
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    stock_quantity:
                                                                                        event
                                                                                            .target
                                                                                            .value ===
                                                                                        ''
                                                                                            ? ''
                                                                                            : Number(
                                                                                                  event
                                                                                                      .target
                                                                                                      .value,
                                                                                              ),
                                                                                },
                                                                            )
                                                                        }
                                                                        className={inputClass(
                                                                            errorFor(
                                                                                `variants.${index}.stock_quantity`,
                                                                            ),
                                                                            'h-10',
                                                                        )}
                                                                    />
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}
                    </FormCard>
                </div>

                <aside className="grid content-start gap-5 xl:sticky xl:top-24">
                    <FormCard
                        title="Product Images"
                        icon={<ImagePlus className="size-5" />}
                    >
                        <label
                            onDragOver={(event) => {
                                event.preventDefault();
                                setIsDraggingImages(true);
                            }}
                            onDragLeave={() => setIsDraggingImages(false)}
                            onDrop={(event) => {
                                event.preventDefault();
                                setIsDraggingImages(false);
                                void addImages(event.dataTransfer.files);
                            }}
                            className={cn(
                                'flex min-h-56 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-5 py-8 text-center transition',
                                isDraggingImages
                                    ? 'border-primary bg-primary/10'
                                    : 'border-primary/50 bg-primary/5 hover:border-primary hover:bg-primary/10',
                            )}
                        >
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(event) =>
                                    void addImages(event.target.files)
                                }
                            />
                            <span className="grid size-14 place-items-center rounded-full bg-white text-primary shadow-sm dark:bg-slate-900">
                                <UploadCloud className="size-7" />
                            </span>
                            <span className="mt-4 font-bold">
                                Upload Product Images
                            </span>
                            <span className="mt-1 text-sm text-slate-500">
                                Drag & drop images here or choose files
                            </span>
                            <span className="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground">
                                Choose Files
                            </span>
                            <span className="mt-4 text-xs leading-5 text-slate-500">
                                800×600 minimum, JPG/PNG/WebP
                                <br />5 MB per image · maximum 5
                            </span>
                        </label>

                        {(visibleExistingMedia.length > 0 ||
                            form.data.images.length > 0) && (
                            <div className="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5 xl:grid-cols-3">
                                {visibleExistingMedia.map((media) => (
                                    <ImageThumbnail
                                        key={media.id}
                                        src={media.url}
                                        alt="Existing product image"
                                        onRemove={() =>
                                            setField('removed_media_ids', [
                                                ...form.data.removed_media_ids,
                                                media.id,
                                            ])
                                        }
                                    />
                                ))}
                                {form.data.images.map((file, index) => (
                                    <ImageThumbnail
                                        key={`${file.name}-${file.lastModified}`}
                                        src={imagePreviewUrls[index]}
                                        alt={file.name}
                                        onEdit={() => {
                                            setCropImageIndex(index);
                                            setDraftCrop(
                                                form.data.image_crops[index],
                                            );
                                            setCropPosition({ x: 0, y: 0 });
                                            setCropZoom(1);
                                        }}
                                        onRemove={() => removeNewImage(index)}
                                    />
                                ))}
                            </div>
                        )}
                        {(imageError || errorFor('images')) && (
                            <ErrorText>
                                {imageError ?? errorFor('images')}
                            </ErrorText>
                        )}
                    </FormCard>

                    <FormCard
                        title="Product Status"
                        icon={<PackageCheck className="size-5" />}
                    >
                        <p className="text-sm font-semibold">
                            Availability <span className="text-red-500">*</span>
                        </p>
                        <div className="mt-2 grid grid-cols-2 rounded-xl border border-slate-200 p-1 dark:border-slate-700">
                            {[
                                [true, 'Active'],
                                [false, 'Inactive'],
                            ].map(([value, label]) => (
                                <button
                                    key={label as string}
                                    type="button"
                                    onClick={() =>
                                        setField('is_active', value as boolean)
                                    }
                                    className={cn(
                                        'rounded-lg px-3 py-2.5 text-sm font-bold transition',
                                        form.data.is_active === value
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800',
                                    )}
                                >
                                    {label as string}
                                </button>
                            ))}
                        </div>
                        <div className="mt-5 grid gap-4">
                            <ToggleRow
                                label="Featured"
                                description="Save as a featured product flag."
                                checked={form.data.is_featured}
                                onChange={(checked) =>
                                    setField('is_featured', checked)
                                }
                            />
                            <ToggleRow
                                label="Best Seller"
                                description="Mark this product as a best seller."
                                checked={form.data.is_best_seller}
                                onChange={(checked) =>
                                    setField('is_best_seller', checked)
                                }
                            />
                            <ToggleRow
                                label="New Arrival"
                                description="Mark this product as a new arrival."
                                checked={form.data.is_new_arrival}
                                onChange={(checked) =>
                                    setField('is_new_arrival', checked)
                                }
                            />
                        </div>
                    </FormCard>

                    <FormCard
                        title="Meta Information"
                        icon={<Settings2 className="size-5" />}
                    >
                        <div className="grid gap-4">
                            <Field
                                label="Meta Title"
                                error={errorFor('meta_title')}
                            >
                                <TextInput
                                    value={form.data.meta_title}
                                    onChange={(value) =>
                                        setField(
                                            'meta_title',
                                            value.slice(0, 60),
                                        )
                                    }
                                    placeholder="Enter meta title"
                                    error={errorFor('meta_title')}
                                />
                                <CharacterCount
                                    value={form.data.meta_title.length}
                                    maximum={60}
                                />
                            </Field>
                            <Field
                                label="Meta Description"
                                error={errorFor('meta_description')}
                            >
                                <textarea
                                    value={form.data.meta_description}
                                    onChange={(event) =>
                                        setField(
                                            'meta_description',
                                            event.target.value.slice(0, 160),
                                        )
                                    }
                                    rows={5}
                                    placeholder="Enter meta description"
                                    className={inputClass(
                                        errorFor('meta_description'),
                                        'h-auto resize-y py-3',
                                    )}
                                />
                                <CharacterCount
                                    value={form.data.meta_description.length}
                                    maximum={160}
                                />
                            </Field>
                        </div>
                    </FormCard>
                </aside>
            </div>

            <div className="sticky bottom-3 z-20 mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-2xl shadow-slate-950/10 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-900/95">
                {form.progress && (
                    <div className="h-1 bg-slate-100 dark:bg-slate-800">
                        <div
                            className="h-full bg-primary transition-[width]"
                            style={{ width: `${form.progress.percentage}%` }}
                        />
                    </div>
                )}
                <div className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                    <Link
                        href={productsIndex()}
                        className="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-6 text-sm font-bold transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        Cancel
                    </Link>
                    <div className="grid gap-3 sm:flex">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => submit(false)}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-primary px-6 text-sm font-bold text-primary transition hover:bg-primary/5 disabled:opacity-50"
                        >
                            <Save className="size-4" /> Save as Draft
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing || !canSubmit}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-primary px-7 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send className="size-4" />
                            {form.processing ? 'Saving…' : 'Submit for Review'}
                        </button>
                    </div>
                </div>
                {!canSubmit && (
                    <p className="border-t border-slate-200 px-4 py-2 text-center text-xs text-slate-500 dark:border-slate-700">
                        You may save drafts now. Submission unlocks after your
                        seller account is approved.
                    </p>
                )}
            </div>

            <Dialog
                open={cropImageIndex !== null}
                onOpenChange={(open) => !open && setCropImageIndex(null)}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Crop product image</DialogTitle>
                        <DialogDescription>
                            Keep the important part of the image inside the 4:3
                            frame.
                        </DialogDescription>
                    </DialogHeader>
                    {cropImage && (
                        <>
                            <div className="relative h-[28rem] overflow-hidden rounded-xl bg-slate-950">
                                <Cropper
                                    image={cropImage.url}
                                    crop={cropPosition}
                                    zoom={cropZoom}
                                    aspect={4 / 3}
                                    minZoom={1}
                                    maxZoom={maximumCropZoom}
                                    initialCroppedAreaPixels={cropImage.crop}
                                    onCropChange={setCropPosition}
                                    onZoomChange={setCropZoom}
                                    onCropComplete={(_, croppedAreaPixels) =>
                                        setDraftCrop(
                                            normalizeCrop(croppedAreaPixels),
                                        )
                                    }
                                />
                            </div>
                            <input
                                type="range"
                                min="1"
                                max={maximumCropZoom}
                                step="0.01"
                                value={cropZoom}
                                onChange={(event) =>
                                    setCropZoom(Number(event.target.value))
                                }
                                className="w-full accent-primary"
                            />
                        </>
                    )}
                    <DialogFooter>
                        <button
                            type="button"
                            onClick={() => setCropImageIndex(null)}
                            className="rounded-xl border px-4 py-2 text-sm font-bold"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                if (
                                    cropImageIndex === null ||
                                    draftCrop === null
                                ) {
                                    return;
                                }

                                if (
                                    draftCrop.width < 800 ||
                                    draftCrop.height < 600
                                ) {
                                    setImageError(
                                        'Keep at least 800 × 600 source pixels inside the crop.',
                                    );

                                    return;
                                }

                                setField(
                                    'image_crops',
                                    form.data.image_crops.map((crop, index) =>
                                        index === cropImageIndex
                                            ? draftCrop
                                            : crop,
                                    ),
                                );
                                setCropImageIndex(null);
                            }}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                        >
                            Apply crop
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </form>
    );
}

function FormCard({
    children,
    icon,
    title,
}: {
    children: ReactNode;
    icon: ReactNode;
    title: string;
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-5 flex items-center gap-3">
                <span className="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                    {icon}
                </span>
                <h2 className="font-black tracking-tight">{title}</h2>
            </div>
            {children}
        </section>
    );
}

function Field({
    children,
    className,
    error,
    label,
    required = false,
}: {
    children: ReactNode;
    className?: string;
    error?: string;
    label: string;
    required?: boolean;
}) {
    return (
        <label className={cn('block', className)}>
            <span className="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-100">
                {label} {required && <span className="text-red-500">*</span>}
            </span>
            {children}
            {error && <ErrorText>{error}</ErrorText>}
        </label>
    );
}

function TextInput({
    error,
    onChange,
    placeholder,
    value,
}: {
    error?: string;
    onChange: (value: string) => void;
    placeholder: string;
    value: string;
}) {
    return (
        <input
            value={value}
            onChange={(event) => onChange(event.target.value)}
            placeholder={placeholder}
            aria-invalid={error ? true : undefined}
            className={inputClass(error)}
        />
    );
}

function MoneyInput({
    error,
    onChange,
    value,
}: {
    error?: string;
    onChange: (value: string) => void;
    value: string;
}) {
    return (
        <div className="relative">
            <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm font-semibold text-slate-500">
                Rs.
            </span>
            <input
                type="number"
                min="0"
                step="0.01"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder="0.00"
                aria-invalid={error ? true : undefined}
                className={inputClass(error, 'pl-10')}
            />
        </div>
    );
}

function ToggleRow({
    checked,
    description,
    label,
    onChange,
}: {
    checked: boolean;
    description: string;
    label: string;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex cursor-pointer items-start gap-3">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <span>
                <span className="block text-sm font-semibold">{label}</span>
                <span className="mt-0.5 block text-xs leading-5 text-slate-500">
                    {description}
                </span>
            </span>
        </label>
    );
}

function VariantImageInput({
    combination,
    error,
    existingImage,
    file,
    onChange,
    onRemove,
    removeExisting,
}: {
    combination: string;
    error?: string;
    existingImage: ListingMedia | null;
    file: File | null;
    onChange: (file: File) => void;
    onRemove: () => void;
    removeExisting: boolean;
}) {
    const previewUrl = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(
        () => () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        },
        [previewUrl],
    );

    const displayedUrl =
        previewUrl ?? (!removeExisting ? existingImage?.url : null);

    return (
        <div className="grid w-20 gap-1.5">
            <label
                className={cn(
                    'group relative grid aspect-square cursor-pointer place-items-center overflow-hidden rounded-lg border border-dashed bg-slate-50 text-slate-400 transition hover:border-primary hover:text-primary dark:bg-slate-950',
                    error
                        ? 'border-red-500'
                        : 'border-slate-300 dark:border-slate-700',
                )}
            >
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    aria-label={`Upload image for ${combination}`}
                    className="sr-only"
                    onChange={(event) => {
                        const selectedFile = event.target.files?.[0];

                        if (selectedFile) {
                            onChange(selectedFile);
                        }

                        event.target.value = '';
                    }}
                />
                {displayedUrl ? (
                    <img
                        src={displayedUrl}
                        alt={`${combination} variant`}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <ImagePlus className="size-5" />
                )}
                <span className="pointer-events-none absolute inset-x-0 bottom-0 bg-slate-950/65 py-1 text-center text-[0.6rem] font-bold text-white opacity-0 transition group-hover:opacity-100">
                    {displayedUrl ? 'Replace' : 'Add image'}
                </span>
            </label>
            {displayedUrl && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="text-[0.65rem] font-semibold text-red-600 hover:underline"
                >
                    Remove
                </button>
            )}
        </div>
    );
}

function ImageThumbnail({
    alt,
    onEdit,
    onRemove,
    src,
}: {
    alt: string;
    onEdit?: () => void;
    onRemove: () => void;
    src: string;
}) {
    return (
        <div className="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
            <button
                type="button"
                onClick={onEdit}
                disabled={!onEdit}
                className="h-full w-full disabled:cursor-default"
            >
                <img
                    src={src}
                    alt={alt}
                    className="h-full w-full object-cover"
                />
            </button>
            <button
                type="button"
                onClick={onRemove}
                aria-label={`Remove ${alt}`}
                className="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-white text-slate-700 shadow ring-1 ring-slate-200 transition hover:text-red-600 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700"
            >
                <X className="size-3.5" />
            </button>
        </div>
    );
}

function CharacterCount({
    value,
    maximum,
}: {
    value: number;
    maximum: number;
}) {
    return (
        <span className="mt-1 block text-right text-xs text-slate-400">
            {value}/{maximum}
        </span>
    );
}

function ErrorText({ children }: { children?: ReactNode }) {
    return (
        <p className="mt-1.5 text-sm font-medium text-red-600">{children}</p>
    );
}

function inputClass(error?: string, className?: string): string {
    return cn(
        'h-12 w-full rounded-xl border bg-transparent px-3 text-sm transition outline-none placeholder:text-slate-400 focus:border-primary focus:ring-4 focus:ring-primary/10 dark:bg-slate-950',
        error ? 'border-red-500' : 'border-slate-300 dark:border-slate-700',
        className,
    );
}

function buildCombinations(options: VariantOption[]): string[][] {
    const completeOptions = options
        .filter((option) => option.name.trim() !== '')
        .map((option) =>
            option.values.map((value) => value.trim()).filter(Boolean),
        );

    if (
        completeOptions.length === 0 ||
        completeOptions.some((values) => values.length === 0)
    ) {
        return [];
    }

    return completeOptions.reduce<string[][]>(
        (rows, values) =>
            rows.flatMap((row) => values.map((value) => [...row, value])),
        [[]],
    );
}

function calculateCombinationCount(options: VariantOption[]): number {
    const completeOptions = options
        .filter((option) => option.name.trim() !== '')
        .map((option) =>
            option.values.map((value) => value.trim()).filter(Boolean),
        );

    if (
        completeOptions.length === 0 ||
        completeOptions.some((values) => values.length === 0)
    ) {
        return 0;
    }

    return completeOptions.reduce((total, values) => total * values.length, 1);
}

function combinationKey(
    selections: string[],
    options: VariantOption[],
): string {
    return selections
        .map(
            (selection, index) =>
                `${options[index]?.name.trim().toLocaleLowerCase() ?? index}:${selection.trim().toLocaleLowerCase()}`,
        )
        .join('|');
}

function suggestedSku(baseSku: string, selections: string[]): string {
    const parts = [baseSku || 'PRODUCT', ...selections]
        .map((part) =>
            part
                .normalize('NFKD')
                .replace(/[^a-zA-Z0-9]+/g, '-')
                .replace(/^-|-$/g, '')
                .toLocaleUpperCase(),
        )
        .filter(Boolean);

    return parts.join('-').slice(0, 100);
}

function centeredFourByThreeCrop(size: ListingImageSize): ListingImageCrop {
    if (size.width / size.height > 4 / 3) {
        const width = Math.round((size.height * 4) / 3);

        return {
            x: Math.round((size.width - width) / 2),
            y: 0,
            width,
            height: size.height,
        };
    }

    const height = Math.round((size.width * 3) / 4);

    return {
        x: 0,
        y: Math.round((size.height - height) / 2),
        width: size.width,
        height,
    };
}

function normalizeCrop(crop: Area): ListingImageCrop {
    return {
        x: Math.round(crop.x),
        y: Math.round(crop.y),
        width: Math.round(crop.width),
        height: Math.round(crop.height),
    };
}

function readImageSize(file: File): Promise<ListingImageSize> {
    return new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error(`Could not read ${file.name}.`));
        };
        image.src = objectUrl;
    });
}
