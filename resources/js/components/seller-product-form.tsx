import { Link, useForm } from '@inertiajs/react';
import {
    Boxes,
    Check,
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
import type { ClipboardEvent, KeyboardEvent, ReactNode } from 'react';
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
    selling_price: string;
    market_price: string;
    stock_quantity: number | '';
    is_active: boolean;
    image: File | null;
    image_crop: ListingImageCrop | null;
    image_size: ListingImageSize | null;
    remove_image: boolean;
    existing_image: ListingMedia | null;
};

type CropTarget =
    | { kind: 'product'; index: number; required: boolean }
    | {
          kind: 'variant';
          index: number;
          combination: string;
          file: File;
          size: ListingImageSize;
          url: string;
      };

type StoredVariantOption = {
    name: string;
    position: number;
    values: { value: string; position: number }[];
};

type StoredVariant = {
    sku: string | null;
    selling_price: string | null;
    market_price: string | null;
    stock_quantity: number;
    is_active: boolean;
    position: number;
    option_values: {
        value: string;
        option: { position: number };
    }[];
    image: ListingMedia | null;
};
type Specifications = Record<string, string | number | boolean>;

const PRODUCT_IMAGE_MAXIMUM_CROP_ZOOM = 3;

export type SellerProductFormListing = {
    title: string | null;
    sku: string | null;
    barcode: string | null;
    model: string | null;
    category_id: number | null;
    brand_id: number | null;
    brand_name: string | null;
    short_description: string | null;
    description: string | null;
    specifications: Specifications | null;
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
    specifications_text: string;
    condition: string;
    model: string;
    product_type: 'simple' | 'variant';
    warranty: string;
    stock_quantity: number | '';
    selling_price: string;
    compare_price: string;
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

type SegmentedChoiceOption<Value extends string | boolean> = {
    description?: string;
    icon?: ReactNode;
    label: string;
    value: Value;
};

const PRODUCT_TYPE_OPTIONS: SegmentedChoiceOption<
    ProductFormData['product_type']
>[] = [
    {
        description: 'One price, one SKU, one stock quantity',
        icon: <PackageCheck className="size-4" />,
        label: 'Simple',
        value: 'simple',
    },
    {
        description: 'Options like color, size, storage, or capacity',
        icon: <Boxes className="size-4" />,
        label: 'Variants',
        value: 'variant',
    },
];

const CONDITION_OPTIONS: SegmentedChoiceOption<string>[] = [
    { label: 'New', value: 'new' },
    { label: 'Used', value: 'used' },
    { label: 'Refurbished', value: 'refurbished' },
];

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
    const [cropTarget, setCropTarget] = useState<CropTarget | null>(null);
    const [pendingProductCropIndexes, setPendingProductCropIndexes] = useState<
        number[]
    >([]);
    const [variantImageErrors, setVariantImageErrors] = useState<
        Record<string, string>
    >({});
    const [cropPosition, setCropPosition] = useState<Point>({ x: 0, y: 0 });
    const [cropZoom, setCropZoom] = useState(1);
    const [draftCrop, setDraftCrop] = useState<ListingImageCrop | null>(null);
    const [cropError, setCropError] = useState<string | null>(null);
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
                    selling_price: variant.selling_price ?? '',
                    market_price: variant.market_price ?? '',
                    stock_quantity: variant.stock_quantity,
                    is_active: variant.is_active,
                    image: null,
                    image_crop: null,
                    image_size: null,
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
        model: listing?.model ?? '',
        title: listing?.title ?? '',
        short_description: listing?.short_description ?? '',
        description: listing?.description ?? '',
        specifications_text: specificationsText(listing?.specifications),
        condition: listing?.condition ?? 'new',
        product_type: listing?.product_type ?? 'simple',
        warranty: listing?.warranty ?? '',
        stock_quantity: listing?.stock_quantity ?? '',
        selling_price: listing?.sale_price ?? listing?.price ?? '',
        compare_price: listing?.sale_price ? (listing.price ?? '') : '',
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

    useEffect(
        () => () => {
            if (cropTarget?.kind === 'variant') {
                URL.revokeObjectURL(cropTarget.url);
            }
        },
        [cropTarget],
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
    const baseSellingPrice = form.data.selling_price;
    const baseStockQuantity = form.data.stock_quantity;
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
        let nextVariants = combinations.map((selections) => {
            const key = combinationKey(selections, variantOptions);
            const existing = existingByKey.get(key);

            return {
                selections,
                sku:
                    existing && manuallyEditedSkus.current.has(key)
                        ? existing.sku
                        : suggestedSku(baseSku, selections),
                stock_quantity: existing?.stock_quantity ?? 0,
                selling_price: existing?.selling_price ?? baseSellingPrice,
                market_price: existing?.market_price ?? '',
                is_active: existing?.is_active ?? true,
                image: existing?.image ?? null,
                image_crop: existing?.image_crop ?? null,
                image_size: existing?.image_size ?? null,
                remove_image: existing?.remove_image ?? false,
                existing_image: existing?.existing_image ?? null,
            };
        });

        if (
            variantRows.length === 0 &&
            nextVariants.length > 0 &&
            baseStockQuantity !== ''
        ) {
            nextVariants = distributeStockEvenly(
                nextVariants,
                Number(baseStockQuantity),
            );
        }

        if (JSON.stringify(nextVariants) !== JSON.stringify(variantRows)) {
            setFormData('variants', nextVariants);
        }
    }, [
        baseSku,
        baseSellingPrice,
        baseStockQuantity,
        combinations,
        productType,
        setFormData,
        variantOptions,
        variantRows,
    ]);

    const aggregateStock =
        form.data.product_type === 'variant'
            ? form.data.variants.length > 0
                ? totalVariantStock(form.data.variants)
                : Number(form.data.stock_quantity || 0)
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
        cropTarget === null
            ? null
            : cropTarget.kind === 'product'
              ? {
                    url: imagePreviewUrls[cropTarget.index],
                    size: imageSizes[cropTarget.index],
                    crop: form.data.image_crops[cropTarget.index],
                }
              : {
                    url: cropTarget.url,
                    size: cropTarget.size,
                    crop: draftCrop ?? centeredSquareCrop(cropTarget.size),
                };
    const maximumCropZoom = cropImage ? PRODUCT_IMAGE_MAXIMUM_CROP_ZOOM : 1;
    const selectedBrand =
        brands.find((brand) => brand.id === form.data.brand_id) ?? null;
    const brandInputValue = selectedBrand?.name ?? form.data.brand_name;
    const brandError = errorFor('brand_id') ?? errorFor('brand_name');
    const brandStatus =
        selectedBrand !== null
            ? 'Using catalog brand'
            : form.data.brand_name.trim() !== ''
              ? 'New brand request'
              : 'Choose an existing brand or type a new brand.';
    const isVariantProduct = form.data.product_type === 'variant';

    function setField<Key extends keyof ProductFormData>(
        key: Key,
        value: ProductFormData[Key],
    ): void {
        form.setData({ ...form.data, [key]: value });
        form.clearErrors(key);
    }

    function updateBrand(value: string): void {
        const exactBrand = brands.find(
            (brand) =>
                brand.name.toLocaleLowerCase() === value.toLocaleLowerCase(),
        );

        form.setData({
            ...form.data,
            brand_id: exactBrand?.id ?? null,
            brand_name: exactBrand ? '' : value,
        });
        form.clearErrors('brand_id', 'brand_name');
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

    function addPresetOption(name: string): void {
        if (
            form.data.variant_options.length >= 3 ||
            form.data.variant_options.some(
                (option) =>
                    option.name.toLocaleLowerCase() ===
                    name.toLocaleLowerCase(),
            )
        ) {
            return;
        }

        setField('variant_options', [
            ...form.data.variant_options,
            { name, values: [] },
        ]);
    }

    function updateVariant(index: number, changes: Partial<VariantRow>): void {
        setField(
            'variants',
            form.data.variants.map((variant, variantIndex) =>
                variantIndex === index ? { ...variant, ...changes } : variant,
            ),
        );
    }

    function updateStockQuantity(value: number | ''): void {
        if (form.data.product_type !== 'variant' || value === '') {
            setField('stock_quantity', value);

            return;
        }

        const stockQuantity = Math.max(0, Math.floor(value));
        const variants = distributeStockEvenly(
            form.data.variants,
            stockQuantity,
        );

        form.setData({
            ...form.data,
            stock_quantity: stockQuantity,
            variants,
        });
        form.clearErrors('stock_quantity');
    }

    function updateVariantStock(
        index: number,
        stockQuantity: number | '',
    ): void {
        const variants = form.data.variants.map((variant, variantIndex) =>
            variantIndex === index
                ? { ...variant, stock_quantity: stockQuantity }
                : variant,
        );

        form.setData({
            ...form.data,
            stock_quantity: totalVariantStock(variants),
            variants,
        });
        form.clearErrors('stock_quantity', `variants.${index}.stock_quantity`);
    }

    function updateVariantStatus(index: number, isActive: boolean): void {
        const variants = form.data.variants.map((variant, variantIndex) =>
            variantIndex === index
                ? { ...variant, is_active: isActive }
                : variant,
        );

        form.setData({
            ...form.data,
            stock_quantity: totalVariantStock(variants),
            variants,
        });
        form.clearErrors(`variants.${index}.is_active`);
    }

    function openProductCrop(
        index: number,
        required: boolean,
        initialCrop = form.data.image_crops[index],
    ): void {
        setCropTarget({ kind: 'product', index, required });
        setDraftCrop(initialCrop);
        setCropError(null);
        setCropPosition({ x: 0, y: 0 });
        setCropZoom(1);
    }

    async function openVariantCrop(
        index: number,
        combination: string,
        file: File,
        initialCrop?: ListingImageCrop | null,
        knownSize?: ListingImageSize | null,
    ): Promise<void> {
        const key = combinationKey(
            form.data.variants[index]?.selections ?? [],
            form.data.variant_options,
        );

        const size = knownSize ?? (await readImageSize(file).catch(() => null));
        const crop = size === null ? null : centeredSquareCrop(size);

        if (size === null || crop === null) {
            setVariantImageErrors((errors) => ({
                ...errors,
                [key]: 'The selected file could not be opened as an image.',
            }));

            return;
        }

        setVariantImageErrors((errors) => {
            const nextErrors = { ...errors };
            delete nextErrors[key];

            return nextErrors;
        });

        if (hasSquareRatio(size)) {
            updateVariant(index, {
                image: file,
                image_crop: crop,
                image_size: size,
                remove_image: false,
            });

            return;
        }

        setCropTarget({
            kind: 'variant',
            index,
            combination,
            file,
            size,
            url: URL.createObjectURL(file),
        });
        setDraftCrop(initialCrop ?? crop);
        setCropError(null);
        setCropPosition({ x: 0, y: 0 });
        setCropZoom(1);
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
                    const size = await readImageSize(file).catch(() => null);
                    const crop =
                        size === null ? null : centeredSquareCrop(size);

                    if (size === null || crop === null) {
                        return null;
                    }

                    return {
                        file,
                        size,
                        crop,
                        requiresCrop: !hasSquareRatio(size),
                    };
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
        const firstNewIndex = form.data.images.length;
        const cropIndexes = prepared.flatMap(
            ({ requiresCrop }, preparedIndex) =>
                requiresCrop ? [firstNewIndex + preparedIndex] : [],
        );
        setPendingProductCropIndexes(cropIndexes);

        if (cropIndexes.length > 0) {
            const firstCropIndex = cropIndexes[0];
            openProductCrop(
                firstCropIndex,
                true,
                prepared[firstCropIndex - firstNewIndex].crop,
            );
        }

        setImageError(
            prepared.length === incoming.length
                ? null
                : 'Some files could not be opened as images.',
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
        setPendingProductCropIndexes((indexes) =>
            indexes
                .filter((pendingIndex) => pendingIndex !== index)
                .map((pendingIndex) =>
                    pendingIndex > index ? pendingIndex - 1 : pendingIndex,
                ),
        );
    }

    function closeCrop(): void {
        if (cropTarget?.kind !== 'product' || !cropTarget.required) {
            setCropTarget(null);

            return;
        }

        const removedIndex = cropTarget.index;
        const nextImages = form.data.images.filter(
            (_, imageIndex) => imageIndex !== removedIndex,
        );
        const nextCrops = form.data.image_crops.filter(
            (_, imageIndex) => imageIndex !== removedIndex,
        );
        const nextSizes = imageSizes.filter(
            (_, imageIndex) => imageIndex !== removedIndex,
        );
        const remainingIndexes = pendingProductCropIndexes
            .filter((pendingIndex) => pendingIndex !== removedIndex)
            .map((pendingIndex) =>
                pendingIndex > removedIndex ? pendingIndex - 1 : pendingIndex,
            );

        form.setData({
            ...form.data,
            images: nextImages,
            image_crops: nextCrops,
        });
        setImageSizes(nextSizes);
        setPendingProductCropIndexes(remainingIndexes);

        if (remainingIndexes.length === 0) {
            setCropTarget(null);

            return;
        }

        const nextIndex = remainingIndexes[0];
        openProductCrop(nextIndex, true, nextCrops[nextIndex]);
    }

    function applyCrop(): void {
        if (cropTarget === null || draftCrop === null) {
            return;
        }

        const normalizedCrop = normalizeCrop(draftCrop);

        if (cropTarget.kind === 'variant') {
            const key = combinationKey(
                form.data.variants[cropTarget.index]?.selections ?? [],
                form.data.variant_options,
            );
            updateVariant(cropTarget.index, {
                image: cropTarget.file,
                image_crop: normalizedCrop,
                image_size: cropTarget.size,
                remove_image: false,
            });
            setVariantImageErrors((errors) => {
                const nextErrors = { ...errors };
                delete nextErrors[key];

                return nextErrors;
            });
            setCropTarget(null);

            return;
        }

        const updatedCrops = form.data.image_crops.map((crop, index) =>
            index === cropTarget.index ? normalizedCrop : crop,
        );
        form.setData({ ...form.data, image_crops: updatedCrops });
        setImageError(null);

        if (!cropTarget.required) {
            setCropTarget(null);

            return;
        }

        const remainingIndexes = pendingProductCropIndexes.filter(
            (pendingIndex) => pendingIndex !== cropTarget.index,
        );
        setPendingProductCropIndexes(remainingIndexes);

        if (remainingIndexes.length === 0) {
            setCropTarget(null);

            return;
        }

        const nextIndex = remainingIndexes[0];
        openProductCrop(nextIndex, true, updatedCrops[nextIndex]);
    }

    function submit(submitForReview: boolean): void {
        form.transform((data) => ({
            ...data,
            description: sanitizeRichText(data.description),
            specifications_text: sanitizeRichText(data.specifications_text),
            variants: data.variants.map((variant) => ({
                selections: variant.selections,
                sku: variant.sku,
                selling_price: variant.selling_price,
                market_price: variant.market_price,
                stock_quantity: variant.stock_quantity,
                is_active: variant.is_active,
                image: variant.image,
                image_crop: variant.image_crop,
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
                            <Field label="Model" error={errorFor('model')}>
                                <TextInput
                                    value={form.data.model}
                                    onChange={(value) =>
                                        setField('model', value)
                                    }
                                    placeholder="Enter model (optional)"
                                    error={errorFor('model')}
                                />
                            </Field>
                            <Field label="Brand" error={brandError} required>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        list="product-brand-options"
                                        value={brandInputValue}
                                        onChange={(event) =>
                                            updateBrand(event.target.value)
                                        }
                                        placeholder="Search catalog brands or type a new one"
                                        aria-invalid={
                                            brandError ? true : undefined
                                        }
                                        className={inputClass(
                                            brandError,
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
                                <p
                                    className={cn(
                                        'mt-1.5 text-xs font-semibold',
                                        selectedBrand
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : form.data.brand_name.trim() !== ''
                                              ? 'text-primary'
                                              : 'text-slate-500',
                                    )}
                                >
                                    {brandStatus}
                                </p>
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
                            <div className="md:col-span-3">
                                <span className="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    Product Type{' '}
                                    <span className="text-red-500">*</span>
                                </span>
                                <SegmentedChoice
                                    value={form.data.product_type}
                                    options={PRODUCT_TYPE_OPTIONS}
                                    onChange={(value) =>
                                        setField('product_type', value)
                                    }
                                />
                            </div>
                            <div className="md:col-span-2">
                                <span className="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    Condition{' '}
                                    <span className="text-red-500">*</span>
                                </span>
                                <SegmentedChoice
                                    value={form.data.condition}
                                    options={CONDITION_OPTIONS}
                                    error={errorFor('condition')}
                                    onChange={(value) =>
                                        setField('condition', value)
                                    }
                                />
                                {errorFor('condition') && (
                                    <ErrorText>
                                        {errorFor('condition')}
                                    </ErrorText>
                                )}
                            </div>
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
                            <Field
                                label="Specifications"
                                error={errorFor('specifications_text')}
                                className="md:col-span-3"
                            >
                                <RichTextEditor
                                    id="product-specifications"
                                    value={form.data.specifications_text}
                                    onChange={(value) =>
                                        setField('specifications_text', value)
                                    }
                                    placeholder="Enter product specifications (optional)"
                                    error={errorFor('specifications_text')}
                                />
                            </Field>
                        </div>
                    </FormCard>

                    <FormCard
                        title="Pricing & Stock"
                        icon={<Tags className="size-5" />}
                    >
                        <div className="grid gap-5 md:grid-cols-3">
                            {!isVariantProduct ? (
                                <>
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
                                            ariaLabel="Selling price in LKR"
                                        />
                                    </Field>
                                    <Field
                                        label="Market Price (LKR)"
                                        error={errorFor('compare_price')}
                                    >
                                        <MoneyInput
                                            value={form.data.compare_price}
                                            onChange={(value) =>
                                                setField('compare_price', value)
                                            }
                                            error={errorFor('compare_price')}
                                            ariaLabel="Optional market price in LKR"
                                        />
                                        <p className="mt-1 truncate text-xs leading-4 text-slate-500">
                                            Optional discount reference price.
                                        </p>
                                    </Field>
                                    <Field
                                        label="Stock Quantity"
                                        error={errorFor('stock_quantity')}
                                        required
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            value={form.data.stock_quantity}
                                            placeholder="Available units"
                                            onChange={(event) =>
                                                updateStockQuantity(
                                                    event.target.value === ''
                                                        ? ''
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                )
                                            }
                                            className={inputClass(
                                                errorFor('stock_quantity'),
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label="Low Stock Alert"
                                        error={errorFor('low_stock_threshold')}
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            value={
                                                form.data.low_stock_threshold
                                            }
                                            placeholder="Alert threshold"
                                            onChange={(event) =>
                                                setField(
                                                    'low_stock_threshold',
                                                    event.target.value === ''
                                                        ? ''
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
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
                                            description="Let customers order after stock reaches zero."
                                            checked={form.data.allow_backorders}
                                            onChange={(checked) =>
                                                setField(
                                                    'allow_backorders',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 md:col-span-3">
                                        <p className="font-bold text-primary">
                                            Pricing and inventory are managed
                                            per variant.
                                        </p>
                                        <p className="mt-1 text-sm leading-6 text-slate-500">
                                            Add option values below, then set
                                            each variant&apos;s SKU, price,
                                            stock, image, and status. Active
                                            stock total: {aggregateStock}.
                                        </p>
                                    </div>
                                    <Field
                                        label="Aggregate Low Stock Alert"
                                        error={errorFor('low_stock_threshold')}
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            value={
                                                form.data.low_stock_threshold
                                            }
                                            placeholder="Alert threshold"
                                            onChange={(event) =>
                                                setField(
                                                    'low_stock_threshold',
                                                    event.target.value === ''
                                                        ? ''
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                )
                                            }
                                            className={inputClass(
                                                errorFor('low_stock_threshold'),
                                            )}
                                        />
                                    </Field>
                                    <Field label="Aggregate Stock Status">
                                        <div className="flex h-12 items-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-emerald-400">
                                            {stockStatus}
                                        </div>
                                    </Field>
                                    <div className="md:col-span-3">
                                        <ToggleRow
                                            label="Allow Backorders"
                                            description="Let customers order after aggregate variant stock reaches zero."
                                            checked={form.data.allow_backorders}
                                            onChange={(checked) =>
                                                setField(
                                                    'allow_backorders',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>
                                </>
                            )}
                        </div>
                    </FormCard>

                    {isVariantProduct && (
                        <FormCard
                            title="Product Variants"
                            icon={<Boxes className="size-5" />}
                        >
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

                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs font-semibold text-slate-500">
                                        Quick add:
                                    </span>
                                    {['Color', 'Size', 'Capacity'].map(
                                        (name) => (
                                            <button
                                                key={name}
                                                type="button"
                                                onClick={() =>
                                                    addPresetOption(name)
                                                }
                                                disabled={
                                                    form.data.variant_options
                                                        .length >= 3 ||
                                                    form.data.variant_options.some(
                                                        (option) =>
                                                            option.name.toLocaleLowerCase() ===
                                                            name.toLocaleLowerCase(),
                                                    )
                                                }
                                                className="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700"
                                            >
                                                + {name}
                                            </button>
                                        ),
                                    )}
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
                                            <div>
                                                <span className="mb-1.5 block text-xs font-bold text-slate-500">
                                                    Option name
                                                </span>
                                                <TextInput
                                                    value={option.name}
                                                    onChange={(value) =>
                                                        updateOption(index, {
                                                            name: value,
                                                        })
                                                    }
                                                    placeholder="Color, size, capacity"
                                                    error={errorFor(
                                                        `variant_options.${index}.name`,
                                                    )}
                                                />
                                            </div>
                                            <div>
                                                <span className="mb-1.5 block text-xs font-bold text-slate-500">
                                                    Values
                                                </span>
                                                <ChipValueInput
                                                    values={option.values}
                                                    onChange={(values) =>
                                                        updateOption(index, {
                                                            values,
                                                        })
                                                    }
                                                    placeholder="Type a value, then press Enter"
                                                    error={errorFor(
                                                        `variant_options.${index}.values`,
                                                    )}
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeOption(index)
                                                }
                                                aria-label={`Remove option ${index + 1}`}
                                                className="grid size-12 place-items-center rounded-xl border border-red-200 text-red-600 transition hover:bg-red-50 md:self-end dark:border-red-900 dark:hover:bg-red-950/30"
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
                                        <table className="w-full min-w-[78rem] text-left text-sm">
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
                                                    <th className="w-40 px-4 py-3">
                                                        Market price
                                                    </th>
                                                    <th className="w-40 px-4 py-3">
                                                        Selling price
                                                    </th>
                                                    <th className="w-36 px-4 py-3">
                                                        Stock
                                                    </th>
                                                    <th className="w-36 px-4 py-3">
                                                        Status
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
                                                                        crop={
                                                                            variant.image_crop
                                                                        }
                                                                        size={
                                                                            variant.image_size
                                                                        }
                                                                        onSelect={(
                                                                            image,
                                                                        ) =>
                                                                            void openVariantCrop(
                                                                                index,
                                                                                variant.selections.join(
                                                                                    ' / ',
                                                                                ),
                                                                                image,
                                                                            )
                                                                        }
                                                                        onEdit={() => {
                                                                            if (
                                                                                variant.image
                                                                            ) {
                                                                                void openVariantCrop(
                                                                                    index,
                                                                                    variant.selections.join(
                                                                                        ' / ',
                                                                                    ),
                                                                                    variant.image,
                                                                                    variant.image_crop,
                                                                                    variant.image_size,
                                                                                );
                                                                            }
                                                                        }}
                                                                        onRemove={() =>
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    image: null,
                                                                                    image_crop:
                                                                                        null,
                                                                                    image_size:
                                                                                        null,
                                                                                    remove_image:
                                                                                        variant.existing_image !==
                                                                                        null,
                                                                                },
                                                                            )
                                                                        }
                                                                        error={
                                                                            variantImageErrors[
                                                                                key
                                                                            ] ??
                                                                            errorFor(
                                                                                `variants.${index}.image_crop`,
                                                                            ) ??
                                                                            errorFor(
                                                                                `variants.${index}.image`,
                                                                            )
                                                                        }
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        aria-label={`${variant.selections.join(' / ')} SKU`}
                                                                        value={
                                                                            variant.sku
                                                                        }
                                                                        placeholder="Variant SKU"
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
                                                                    <MoneyInput
                                                                        ariaLabel={`${variant.selections.join(' / ')} market price`}
                                                                        value={
                                                                            variant.market_price
                                                                        }
                                                                        onChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    market_price:
                                                                                        value,
                                                                                },
                                                                            )
                                                                        }
                                                                        error={errorFor(
                                                                            `variants.${index}.market_price`,
                                                                        )}
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <MoneyInput
                                                                        ariaLabel={`${variant.selections.join(' / ')} selling price`}
                                                                        value={
                                                                            variant.selling_price
                                                                        }
                                                                        onChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateVariant(
                                                                                index,
                                                                                {
                                                                                    selling_price:
                                                                                        value,
                                                                                },
                                                                            )
                                                                        }
                                                                        error={errorFor(
                                                                            `variants.${index}.selling_price`,
                                                                        )}
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        aria-label={`${variant.selections.join(' / ')} stock quantity`}
                                                                        type="number"
                                                                        min="0"
                                                                        value={
                                                                            variant.stock_quantity
                                                                        }
                                                                        placeholder="0"
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateVariantStock(
                                                                                index,
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
                                                                <td className="px-4 py-3">
                                                                    <select
                                                                        aria-label={`${variant.selections.join(' / ')} status`}
                                                                        value={
                                                                            variant.is_active
                                                                                ? 'active'
                                                                                : 'inactive'
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateVariantStatus(
                                                                                index,
                                                                                event
                                                                                    .target
                                                                                    .value ===
                                                                                    'active',
                                                                            )
                                                                        }
                                                                        className={inputClass(
                                                                            errorFor(
                                                                                `variants.${index}.is_active`,
                                                                            ),
                                                                            'h-10',
                                                                        )}
                                                                    >
                                                                        <option value="active">
                                                                            Active
                                                                        </option>
                                                                        <option value="inactive">
                                                                            Inactive
                                                                        </option>
                                                                    </select>
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
                        </FormCard>
                    )}
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
                                accept="image/*"
                                className="sr-only"
                                onChange={(event) => {
                                    void addImages(event.target.files);
                                    event.target.value = '';
                                }}
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
                                Square crops work best.
                                <br />
                                Maximum 5 images.
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
                                        key={`${file.name}-${file.lastModified}-${index}`}
                                        src={imagePreviewUrls[index]}
                                        alt={file.name}
                                        crop={form.data.image_crops[index]}
                                        size={imageSizes[index]}
                                        onEdit={() =>
                                            openProductCrop(index, false)
                                        }
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
                open={cropTarget !== null}
                onOpenChange={(open) => !open && closeCrop()}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {cropTarget?.kind === 'variant'
                                ? `Crop ${cropTarget.combination} image`
                                : 'Crop product image'}
                        </DialogTitle>
                        <DialogDescription>
                            Keep the product centered inside the square frame,
                            then save the crop to continue.
                        </DialogDescription>
                    </DialogHeader>
                    {cropImage && (
                        <>
                            <div className="relative mx-auto aspect-square w-full max-w-[32rem] overflow-hidden rounded-xl bg-slate-950">
                                <Cropper
                                    key={cropImage.url}
                                    image={cropImage.url}
                                    crop={cropPosition}
                                    zoom={cropZoom}
                                    aspect={1}
                                    minZoom={1}
                                    maxZoom={maximumCropZoom}
                                    initialCroppedAreaPixels={cropImage.crop}
                                    onCropChange={setCropPosition}
                                    onZoomChange={setCropZoom}
                                    onCropComplete={(_, croppedAreaPixels) => {
                                        setDraftCrop(
                                            normalizeCrop(croppedAreaPixels),
                                        );
                                        setCropError(null);
                                    }}
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
                            {cropError && <ErrorText>{cropError}</ErrorText>}
                        </>
                    )}
                    <DialogFooter>
                        <button
                            type="button"
                            onClick={closeCrop}
                            className="rounded-xl border px-4 py-2 text-sm font-bold"
                        >
                            {cropTarget?.kind === 'product' &&
                            cropTarget.required
                                ? 'Discard image'
                                : 'Cancel'}
                        </button>
                        <button
                            type="button"
                            onClick={applyCrop}
                            className="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                        >
                            Save crop
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

function SegmentedChoice<Value extends string | boolean>({
    className,
    error,
    onChange,
    options,
    value,
}: {
    className?: string;
    error?: string;
    onChange: (value: Value) => void;
    options: SegmentedChoiceOption<Value>[];
    value: Value;
}) {
    return (
        <div
            aria-invalid={error ? true : undefined}
            className={cn(
                'grid gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-950',
                error && 'border-red-500',
                options.length === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
                className,
            )}
        >
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <button
                        key={String(option.value)}
                        type="button"
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 py-2 text-center text-sm font-bold transition',
                            selected
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-900',
                            option.description && 'min-h-16 flex-col gap-1',
                        )}
                    >
                        <span className="flex items-center justify-center gap-2">
                            {option.icon}
                            {option.label}
                            {selected && <Check className="size-3.5" />}
                        </span>
                        {option.description && (
                            <span
                                className={cn(
                                    'text-xs leading-4 font-medium',
                                    selected
                                        ? 'text-primary-foreground/80'
                                        : 'text-slate-500',
                                )}
                            >
                                {option.description}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
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

function ChipValueInput({
    error,
    onChange,
    placeholder,
    values,
}: {
    error?: string;
    onChange: (values: string[]) => void;
    placeholder: string;
    values: string[];
}) {
    const [draftValue, setDraftValue] = useState('');
    const normalizedValues = values
        .map((value) => value.trim())
        .filter(Boolean);

    function addValues(incomingValues: string[]): void {
        const existingValues = new Set(
            normalizedValues.map((value) => value.toLocaleLowerCase()),
        );
        const nextValues = [...normalizedValues];

        incomingValues
            .map((value) => value.trim())
            .filter(Boolean)
            .forEach((value) => {
                const normalizedValue = value.toLocaleLowerCase();

                if (!existingValues.has(normalizedValue)) {
                    existingValues.add(normalizedValue);
                    nextValues.push(value);
                }
            });

        if (nextValues.length !== values.length) {
            onChange(nextValues);
        }

        setDraftValue('');
    }

    function removeValue(index: number): void {
        onChange(
            normalizedValues.filter((_, valueIndex) => valueIndex !== index),
        );
    }

    function handleKeyDown(event: KeyboardEvent<HTMLInputElement>): void {
        if (event.key !== 'Enter' && event.key !== ',') {
            return;
        }

        event.preventDefault();
        addValues([draftValue]);
    }

    function handlePaste(event: ClipboardEvent<HTMLInputElement>): void {
        const pastedText = event.clipboardData.getData('text');

        if (!pastedText.includes(',') && !pastedText.includes('\n')) {
            return;
        }

        event.preventDefault();
        addValues([...pastedText.split(/[,\n]/), draftValue]);
    }

    return (
        <div
            className={cn(
                'min-h-12 rounded-xl border px-2 py-2 transition focus-within:border-primary focus-within:ring-4 focus-within:ring-primary/10 dark:bg-slate-950',
                error
                    ? 'border-red-500'
                    : 'border-slate-300 dark:border-slate-700',
            )}
            aria-invalid={error ? true : undefined}
        >
            <div className="flex flex-wrap items-center gap-2">
                {normalizedValues.map((value, index) => (
                    <span
                        key={`${value}-${index}`}
                        className="inline-flex min-h-8 items-center gap-1.5 rounded-lg bg-primary/10 px-2.5 text-xs font-bold text-primary"
                    >
                        {value}
                        <button
                            type="button"
                            onClick={() => removeValue(index)}
                            aria-label={`Remove ${value}`}
                            className="grid size-4 place-items-center rounded-full transition hover:bg-primary/15"
                        >
                            <X className="size-3" />
                        </button>
                    </span>
                ))}
                <input
                    value={draftValue}
                    onBlur={() => addValues([draftValue])}
                    onChange={(event) => setDraftValue(event.target.value)}
                    onKeyDown={handleKeyDown}
                    onPaste={handlePaste}
                    placeholder={
                        normalizedValues.length === 0
                            ? placeholder
                            : 'Add another'
                    }
                    className="h-8 min-w-36 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-slate-400"
                />
            </div>
        </div>
    );
}

function MoneyInput({
    ariaLabel,
    error,
    onChange,
    value,
}: {
    ariaLabel?: string;
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
                aria-label={ariaLabel}
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
    crop,
    error,
    existingImage,
    file,
    onEdit,
    onRemove,
    onSelect,
    removeExisting,
    size,
}: {
    combination: string;
    crop: ListingImageCrop | null;
    error?: string;
    existingImage: ListingMedia | null;
    file: File | null;
    onEdit: () => void;
    onRemove: () => void;
    onSelect: (file: File) => void;
    removeExisting: boolean;
    size: ListingImageSize | null;
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
        <div className="grid w-24 gap-1.5">
            <label
                className={cn(
                    'group relative grid aspect-square cursor-pointer place-items-center overflow-hidden rounded-lg border border-dashed bg-white text-slate-400 transition hover:border-primary hover:text-primary dark:bg-slate-950',
                    error
                        ? 'border-red-500'
                        : 'border-slate-300 dark:border-slate-700',
                )}
            >
                <input
                    type="file"
                    accept="image/*"
                    aria-label={`Upload image for ${combination}`}
                    className="sr-only"
                    onChange={(event) => {
                        const selectedFile = event.target.files?.[0];

                        if (selectedFile) {
                            onSelect(selectedFile);
                        }

                        event.target.value = '';
                    }}
                />
                {displayedUrl ? (
                    file && crop && size ? (
                        <CroppedImagePreview
                            src={displayedUrl}
                            alt={`${combination} variant`}
                            crop={crop}
                            size={size}
                        />
                    ) : (
                        <img
                            src={displayedUrl}
                            alt={`${combination} variant`}
                            className="h-full w-full object-contain p-1"
                        />
                    )
                ) : (
                    <ImagePlus className="size-5" />
                )}
                <span className="pointer-events-none absolute inset-x-0 bottom-0 bg-slate-950/65 py-1 text-center text-[0.6rem] font-bold text-white opacity-0 transition group-hover:opacity-100">
                    {displayedUrl ? 'Replace' : 'Add image'}
                </span>
            </label>
            {displayedUrl && (
                <div className="flex items-center justify-between gap-2 text-[0.65rem] font-semibold">
                    {file && crop && size ? (
                        <button
                            type="button"
                            onClick={onEdit}
                            className="text-primary hover:underline"
                        >
                            Crop
                        </button>
                    ) : (
                        <span />
                    )}
                    <button
                        type="button"
                        onClick={onRemove}
                        className="text-red-600 hover:underline"
                    >
                        Remove
                    </button>
                </div>
            )}
            {error && (
                <span className="text-[0.65rem] text-red-600">{error}</span>
            )}
        </div>
    );
}

function ImageThumbnail({
    alt,
    crop,
    onEdit,
    onRemove,
    size,
    src,
}: {
    alt: string;
    crop?: ListingImageCrop;
    onEdit?: () => void;
    onRemove: () => void;
    size?: ListingImageSize;
    src: string;
}) {
    return (
        <div className="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950">
            <button
                type="button"
                onClick={onEdit}
                disabled={!onEdit}
                className="h-full w-full disabled:cursor-default"
            >
                {crop && size ? (
                    <CroppedImagePreview
                        src={src}
                        alt={alt}
                        crop={crop}
                        size={size}
                    />
                ) : (
                    <img
                        src={src}
                        alt={alt}
                        className="h-full w-full object-contain p-1"
                    />
                )}
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

function CroppedImagePreview({
    alt,
    crop,
    size,
    src,
}: {
    alt: string;
    crop: ListingImageCrop;
    size: ListingImageSize;
    src: string;
}) {
    return (
        <span className="relative block h-full w-full overflow-hidden">
            <img
                src={src}
                alt={alt}
                className="absolute max-w-none"
                style={{
                    width: `${(size.width / crop.width) * 100}%`,
                    height: `${(size.height / crop.height) * 100}%`,
                    left: `${-(crop.x / crop.width) * 100}%`,
                    top: `${-(crop.y / crop.height) * 100}%`,
                }}
            />
        </span>
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

function distributeStockEvenly(
    variants: VariantRow[],
    totalStock: number,
): VariantRow[] {
    if (variants.length === 0) {
        return variants;
    }

    const normalizedTotal = Math.max(0, Math.floor(totalStock));
    const stockPerVariant = Math.floor(normalizedTotal / variants.length);
    const remainder = normalizedTotal % variants.length;

    return variants.map((variant, index) => ({
        ...variant,
        stock_quantity: stockPerVariant + (index < remainder ? 1 : 0),
    }));
}

function totalVariantStock(variants: VariantRow[]): number {
    return variants.reduce(
        (total, variant) =>
            total +
            (variant.is_active ? Number(variant.stock_quantity || 0) : 0),
        0,
    );
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

function centeredSquareCrop(size: ListingImageSize): ListingImageCrop {
    if (size.width > size.height) {
        const width = size.height;

        return {
            x: Math.round((size.width - width) / 2),
            y: 0,
            width,
            height: width,
        };
    }

    const height = size.width;

    return {
        x: 0,
        y: Math.round((size.height - height) / 2),
        width: height,
        height,
    };
}

function specificationsText(specifications?: Specifications | null): string {
    if (!specifications) {
        return '';
    }

    if (typeof specifications.Details === 'string') {
        return specifications.Details;
    }

    return Object.entries(specifications)
        .map(([name, value]) => `${name}: ${String(value)}`)
        .join('\n');
}

function hasSquareRatio(size: ListingImageSize): boolean {
    return Math.abs(size.width - size.height) <= 2;
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
