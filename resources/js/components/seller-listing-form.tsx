import { useForm } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    Gavel,
    ImagePlus,
    MapPin,
    Search,
    ShoppingBag,
    Sparkles,
    Tag,
    UploadCloud,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import { CategoryPicker } from '@/components/category-picker';
import type { CategoryOption } from '@/components/category-picker';
import {
    RichTextContent,
    RichTextEditor,
    richTextPlainText,
    sanitizeRichText,
} from '@/components/rich-text-editor';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Brand = { id: number; name: string };
type ListingMedia = { id: number; path: string; url: string };
type ListingImageCrop = { x: number; y: number; width: number; height: number };
type ListingImageSize = { width: number; height: number };

export type SellerListingFormListing = {
    title: string;
    category_id: number;
    brand_id: number | null;
    brand_name: string | null;
    description: string;
    condition: string;
    listing_type: string;
    location: string;
    warranty: string | null;
    stock_quantity: number;
    price: string | null;
    sale_price: string | null;
    media?: ListingMedia[];
    auction: {
        starting_price: string;
        reserve_price: string | null;
        minimum_increment: string;
        starts_at: string;
        ends_at: string;
    } | null;
};

type FormDefinition = { action: string; method: 'post' | 'put' };
type ListingFormData = {
    category_id: number | '';
    brand_id: number | null;
    brand_name: string;
    title: string;
    description: string;
    condition: string;
    listing_type: 'buy_now' | 'auction';
    location: string;
    warranty: string;
    stock_quantity: number | '';
    price: string;
    sale_price: string;
    starting_price: string;
    reserve_price: string;
    minimum_increment: string;
    starts_at: string;
    ends_at: string;
    images: File[];
    image_crops: ListingImageCrop[];
    submit_for_review: number;
};

const steps = [
    ['Product', 'What are you selling?'],
    ['Details', 'Tell buyers about the item'],
    ['Pricing', 'Choose how you want to sell'],
    ['Photos', 'Show the item clearly'],
    ['Review', 'Check before saving'],
] as const;

const stepFields = [
    ['title', 'category_id', 'condition'],
    ['description', 'location'],
    [
        'listing_type',
        'price',
        'sale_price',
        'stock_quantity',
        'starting_price',
        'reserve_price',
        'minimum_increment',
        'starts_at',
        'ends_at',
    ],
    ['images', 'image_crops'],
] as const;

export function SellerListingForm({
    form: formDefinition,
    initialCategory,
    brands,
    listing,
    canSubmit,
}: {
    form: FormDefinition;
    initialCategory: CategoryOption | null;
    brands: Brand[];
    listing?: SellerListingFormListing;
    canSubmit: boolean;
}) {
    const isNewListing = listing === undefined;
    const [activeStep, setActiveStep] = useState(0);
    const [furthestStep, setFurthestStep] = useState(0);
    const [selectedCategory, setSelectedCategory] =
        useState<CategoryOption | null>(initialCategory);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );
    const existingBrandName = brands.find(
        (brand) => brand.id === listing?.brand_id,
    )?.name;
    const [brandQuery, setBrandQuery] = useState(
        listing?.brand_name ?? existingBrandName ?? '',
    );
    const [showBrandOptions, setShowBrandOptions] = useState(false);
    const [isDraggingImages, setIsDraggingImages] = useState(false);
    const form = useForm<ListingFormData>({
        category_id: listing?.category_id ?? '',
        brand_id: listing?.brand_id ?? null,
        brand_name: listing?.brand_name ?? '',
        title: listing?.title ?? '',
        description: listing?.description ?? '',
        condition: listing?.condition ?? 'new',
        listing_type:
            listing?.listing_type === 'auction' ? 'auction' : 'buy_now',
        location: listing?.location ?? '',
        warranty: listing?.warranty ?? '',
        stock_quantity: listing?.stock_quantity ?? '',
        price: listing?.price ?? '',
        sale_price: listing?.sale_price ?? '',
        starting_price: listing?.auction?.starting_price ?? '',
        reserve_price: listing?.auction?.reserve_price ?? '',
        minimum_increment: listing?.auction?.minimum_increment ?? '',
        starts_at: listing?.auction?.starts_at?.slice(0, 16) ?? '',
        ends_at: listing?.auction?.ends_at?.slice(0, 16) ?? '',
        images: [],
        image_crops: [],
        submit_for_review: 0,
    });
    const [imageSizes, setImageSizes] = useState<ListingImageSize[]>([]);
    const [cropImageIndex, setCropImageIndex] = useState<number | null>(null);
    const [cropPosition, setCropPosition] = useState<Point>({ x: 0, y: 0 });
    const [cropZoom, setCropZoom] = useState(1);
    const [draftCrop, setDraftCrop] = useState<ListingImageCrop | null>(null);
    const [cropEditorVersion, setCropEditorVersion] = useState(0);
    const imagePreviewUrls = useMemo(
        () => form.data.images.map((file) => URL.createObjectURL(file)),
        [form.data.images],
    );
    const imagePreviews = form.data.images.map((file, index) => ({
        file,
        url: imagePreviewUrls[index],
        size: imageSizes[index],
        crop: form.data.image_crops[index],
    }));

    useEffect(
        () => () => imagePreviewUrls.forEach(URL.revokeObjectURL),
        [imagePreviewUrls],
    );

    const existingMedia = listing?.media ?? [];
    const totalPhotoCount = existingMedia.length + form.data.images.length;
    const selectedBrand = brands.find(
        (brand) => brand.id === form.data.brand_id,
    );
    const brandOptions = brands.filter((brand) =>
        brand.name.toLocaleLowerCase().includes(brandQuery.toLocaleLowerCase()),
    );
    const primaryPhotoUrl = existingMedia[0]?.url;
    const cropImage =
        cropImageIndex === null ? undefined : imagePreviews[cropImageIndex];
    const maximumCropZoom = cropImage?.size
        ? Math.max(
              1,
              Math.min(
                  centeredFourByThreeCrop(cropImage.size).width / 1200,
                  centeredFourByThreeCrop(cropImage.size).height / 900,
              ),
          )
        : 1;
    const listingHealthChecks = [
        {
            label: 'Product title and category',
            complete:
                form.data.title.trim() !== '' && form.data.category_id !== '',
        },
        {
            label: 'Buyer-friendly description',
            complete: richTextPlainText(form.data.description) !== '',
        },
        {
            label:
                form.data.listing_type === 'auction'
                    ? 'Auction terms are ready'
                    : 'Price and stock are ready',
            complete:
                form.data.listing_type === 'auction'
                    ? Number(form.data.starting_price) > 0 &&
                      Number(form.data.minimum_increment) > 0 &&
                      form.data.starts_at !== '' &&
                      form.data.ends_at !== ''
                    : Number(form.data.price) > 0 &&
                      Number(form.data.stock_quantity) > 0,
        },
        {
            label: 'At least one clear product photo',
            complete: totalPhotoCount > 0,
        },
    ];

    function clearError(field: string): void {
        setClientErrors((errors) => {
            const remaining = { ...errors };
            delete remaining[field];

            return remaining;
        });
        form.clearErrors(field as keyof ListingFormData);
    }

    function focusField(field: string): void {
        const focusTarget = field.startsWith('image_crops') ? 'images' : field;

        window.setTimeout(() =>
            document.getElementById(`listing-${focusTarget}`)?.focus(),
        );
    }

    function validateStep(step: number): boolean {
        const errors: Record<string, string> = {};

        if (step === 0) {
            if (form.data.title.trim() === '') {
                errors.title = 'Add a title for your listing.';
            }

            if (form.data.category_id === '') {
                errors.category_id = 'Choose a category.';
            }
        }

        if (step === 1) {
            if (richTextPlainText(form.data.description) === '') {
                errors.description = 'Add a product description.';
            }

            if (form.data.location.trim() === '') {
                errors.location = 'Add the item location.';
            }
        }

        if (step === 2) {
            if (form.data.listing_type === 'buy_now') {
                if (form.data.price === '' || Number(form.data.price) < 1) {
                    errors.price = 'Enter a price of at least LKR 1.';
                }

                if (
                    form.data.sale_price !== '' &&
                    Number(form.data.sale_price) >= Number(form.data.price)
                ) {
                    errors.sale_price =
                        'The offer price must be lower than the regular price.';
                }

                if (
                    form.data.stock_quantity === '' ||
                    Number(form.data.stock_quantity) < 1
                ) {
                    errors.stock_quantity = 'Enter at least one item in stock.';
                }
            } else {
                if (
                    form.data.starting_price === '' ||
                    Number(form.data.starting_price) < 1
                ) {
                    errors.starting_price = 'Enter a starting price.';
                }

                if (
                    form.data.minimum_increment === '' ||
                    Number(form.data.minimum_increment) < 1
                ) {
                    errors.minimum_increment = 'Enter a minimum bid increment.';
                }

                if (form.data.starts_at === '') {
                    errors.starts_at = 'Choose when the auction starts.';
                }

                if (form.data.ends_at === '') {
                    errors.ends_at = 'Choose when the auction ends.';
                }
            }
        }

        if (step === 3) {
            if (totalPhotoCount === 0) {
                errors.images = 'Add at least one product photo.';
            }

            if (totalPhotoCount > 5) {
                errors.images = 'You can upload a maximum of five photos.';
            }

            if (
                form.data.images.some(
                    (image) =>
                        !['image/jpeg', 'image/png', 'image/webp'].includes(
                            image.type,
                        ) || image.size > 5 * 1024 * 1024,
                )
            ) {
                errors.images =
                    'Photos must be JPG, PNG, or WebP and 5 MB or smaller.';
            }
        }

        setClientErrors(errors);
        const firstError = Object.keys(errors)[0];

        if (firstError) {
            focusField(firstError);
        }

        return firstError === undefined;
    }

    function nextStep(): void {
        if (validateStep(activeStep)) {
            const nextStepIndex = Math.min(activeStep + 1, steps.length - 1);
            setActiveStep(nextStepIndex);
            setFurthestStep((step) => Math.max(step, nextStepIndex));
        }
    }

    function selectBrand(brand: Brand): void {
        form.setData({ ...form.data, brand_id: brand.id, brand_name: '' });
        setBrandQuery(brand.name);
        setShowBrandOptions(false);
        clearError('brand_name');
    }

    function typeBrand(value: string): void {
        setBrandQuery(value);
        form.setData({ ...form.data, brand_id: null, brand_name: value });
        clearError('brand_name');
    }

    async function addImages(files: FileList | null): Promise<void> {
        if (!files) {
            return;
        }

        const incomingImages = Array.from(files);
        const validFileTypes = incomingImages.filter(
            (image) =>
                ['image/jpeg', 'image/png', 'image/webp'].includes(
                    image.type,
                ) && image.size <= 5 * 1024 * 1024,
        );
        const remainingPhotoSlots = Math.max(0, 5 - totalPhotoCount);
        const preparedImages = (
            await Promise.all(
                validFileTypes
                    .slice(0, remainingPhotoSlots)
                    .map(async (file) => {
                        const size = await readImageSize(file).catch(
                            () => null,
                        );

                        if (
                            size === null ||
                            size.width < 1200 ||
                            size.height < 900 ||
                            size.width > 6000 ||
                            size.height > 6000
                        ) {
                            return null;
                        }

                        return {
                            file,
                            size,
                            crop: centeredFourByThreeCrop(size),
                        };
                    }),
            )
        ).filter((image) => image !== null);

        form.setData({
            ...form.data,
            images: [
                ...form.data.images,
                ...preparedImages.map(({ file }) => file),
            ],
            image_crops: [
                ...form.data.image_crops,
                ...preparedImages.map(({ crop }) => crop),
            ],
        });
        setImageSizes((sizes) => [
            ...sizes,
            ...preparedImages.map(({ size }) => size),
        ]);

        if (
            validFileTypes.length !== incomingImages.length ||
            preparedImages.length !==
                Math.min(validFileTypes.length, remainingPhotoSlots)
        ) {
            setClientErrors((errors) => ({
                ...errors,
                images: 'Some photos were skipped. Use JPG, PNG, or WebP files from 1200 × 900 to 6000 × 6000 pixels and no larger than 5 MB.',
            }));
            form.clearErrors('images');

            return;
        }

        if (validFileTypes.length > remainingPhotoSlots) {
            setClientErrors((errors) => ({
                ...errors,
                images: 'Only the first five photos were added.',
            }));
            form.clearErrors('images');

            return;
        }

        clearError('images');
    }

    function openCropEditor(index: number): void {
        setCropImageIndex(index);
        setDraftCrop(form.data.image_crops[index]);
        setCropPosition({ x: 0, y: 0 });
        setCropZoom(1);
        setCropEditorVersion((version) => version + 1);
    }

    function resetCropEditor(): void {
        if (!cropImage?.size) {
            return;
        }

        setDraftCrop(centeredFourByThreeCrop(cropImage.size));
        setCropPosition({ x: 0, y: 0 });
        setCropZoom(1);
        setCropEditorVersion((version) => version + 1);
    }

    function applyCrop(): void {
        if (cropImageIndex === null || draftCrop === null) {
            return;
        }

        const normalizedCrop = normalizeCrop(draftCrop);

        if (normalizedCrop.width < 1200 || normalizedCrop.height < 900) {
            setClientErrors((errors) => ({
                ...errors,
                images: 'Keep at least 1200 × 900 source pixels inside the crop.',
            }));

            return;
        }

        form.setData(
            'image_crops',
            form.data.image_crops.map((crop, index) =>
                index === cropImageIndex ? normalizedCrop : crop,
            ),
        );
        setCropImageIndex(null);
        clearError('images');
    }

    function removeImage(index: number): void {
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
        clearError('images');
    }

    function submit(submitForReview: boolean): void {
        const firstInvalidStep = [0, 1, 2, 3].find(
            (step) => !validateStep(step),
        );

        if (firstInvalidStep !== undefined) {
            setActiveStep(firstInvalidStep);

            return;
        }

        form.transform((data) => ({
            ...data,
            brand_name: data.brand_name.trim(),
            description: sanitizeRichText(data.description),
            submit_for_review: submitForReview ? 1 : 0,
        }));
        const options = {
            onError: (errors: Record<string, string>) => {
                const step = stepFields.findIndex((fields) =>
                    fields.some((field) =>
                        Object.keys(errors).some(
                            (errorField) =>
                                errorField === field ||
                                errorField.startsWith(`${field}.`),
                        ),
                    ),
                );

                if (step !== -1) {
                    setActiveStep(step);
                    const firstInvalidField = stepFields[step].find(
                        (field) => errors[field] !== undefined,
                    );

                    if (firstInvalidField) {
                        focusField(firstInvalidField);
                    }
                }
            },
        };

        if (formDefinition.method === 'put') {
            form.put(formDefinition.action, options);
        } else {
            form.post(formDefinition.action, options);
        }
    }

    function errorFor(field: keyof ListingFormData): string | undefined {
        const directError = clientErrors[field] ?? form.errors[field];

        if (directError || field !== 'images') {
            return directError;
        }

        return Object.entries(form.errors).find(([errorField]) =>
            errorField.startsWith('image_crops'),
        )?.[1];
    }

    const inputClass =
        'rounded-xl border border-stone-300 bg-transparent px-4 py-3 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10 dark:border-stone-700';

    return (
        <section className="mt-7 grid gap-6">
            <nav
                aria-label="Listing progress"
                className="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm dark:border-stone-800 dark:bg-stone-900"
            >
                <ol className="grid grid-cols-5 gap-1.5 sm:gap-2">
                    {steps.map(([title, description], index) => {
                        const complete =
                            index !== activeStep && index <= furthestStep;

                        return (
                            <li key={title}>
                                <button
                                    type="button"
                                    disabled={index > furthestStep}
                                    onClick={() => setActiveStep(index)}
                                    aria-current={
                                        index === activeStep
                                            ? 'step'
                                            : undefined
                                    }
                                    className={`flex min-h-16 w-full items-center gap-2 rounded-xl px-2 py-2.5 text-left transition sm:px-3 ${
                                        index === activeStep
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : complete
                                              ? 'bg-amber-50 text-amber-950 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-100'
                                              : 'text-stone-400 disabled:cursor-not-allowed dark:text-stone-600'
                                    }`}
                                >
                                    <span
                                        className={`flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-black sm:size-8 sm:text-sm ${
                                            index === activeStep
                                                ? 'bg-stone-950 text-white'
                                                : complete
                                                  ? 'bg-primary text-primary-foreground'
                                                  : 'bg-stone-100 text-stone-400 dark:bg-stone-800'
                                        }`}
                                    >
                                        {complete ? (
                                            <Check className="size-4" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-xs font-black sm:text-sm">
                                            {title}
                                        </span>
                                        <span
                                            className={`hidden truncate text-xs lg:block ${index === activeStep ? 'text-stone-800' : 'text-stone-500'}`}
                                        >
                                            {description}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </nav>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(false);
                }}
                className="grid gap-6"
                noValidate
            >
                {activeStep === 0 && (
                    <div className="grid gap-6 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-7 dark:border-stone-800 dark:bg-stone-900">
                        <StepIntro
                            step={1}
                            title="Tell buyers about your item"
                        />
                        <label className="grid gap-2 font-semibold">
                            Listing title
                            <input
                                id="listing-title"
                                value={form.data.title}
                                onChange={(event) => {
                                    form.setData('title', event.target.value);
                                    clearError('title');
                                }}
                                placeholder="e.g. Canon EOS R6 camera body"
                                className={inputClass}
                            />
                            <span className="text-xs font-normal text-stone-500">
                                Use the words a buyer would search for. Keep it
                                specific and easy to scan.
                            </span>
                            {errorFor('title') && (
                                <FieldError error={errorFor('title')} />
                            )}
                        </label>
                        <div
                            id="listing-category_id"
                            tabIndex={-1}
                            className="grid gap-3 outline-none"
                        >
                            <p className="text-sm text-stone-500">
                                Search or browse departments, then choose the
                                most specific category for your item.
                            </p>
                            <CategoryPicker
                                label="Choose a category"
                                selected={selectedCategory}
                                onSelect={(category) => {
                                    setSelectedCategory(category);
                                    form.setData(
                                        'category_id',
                                        category?.id ?? '',
                                    );
                                    clearError('category_id');
                                }}
                                error={errorFor('category_id')}
                            />
                        </div>
                        <fieldset id="listing-condition" className="grid gap-3">
                            <legend className="font-semibold">
                                Item condition
                            </legend>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    {
                                        value: 'new',
                                        title: 'New',
                                        copy: 'Unused and in original condition.',
                                    },
                                    {
                                        value: 'used',
                                        title: 'Used',
                                        copy: 'Previously owned and fully described.',
                                    },
                                    {
                                        value: 'refurbished',
                                        title: 'Refurbished',
                                        copy: 'Restored, tested, and ready to use.',
                                    },
                                ].map((condition) => {
                                    const selected =
                                        form.data.condition === condition.value;

                                    return (
                                        <button
                                            key={condition.value}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'condition',
                                                    condition.value,
                                                )
                                            }
                                            aria-pressed={selected}
                                            className={`rounded-2xl border p-4 text-left transition ${
                                                selected
                                                    ? 'border-stone-950 bg-stone-950 text-white shadow-sm dark:border-stone-50 dark:bg-stone-50 dark:text-stone-950'
                                                    : 'border-stone-200 hover:border-stone-400 dark:border-stone-800'
                                            }`}
                                        >
                                            <span className="flex items-center justify-between gap-3 font-black">
                                                {condition.title}
                                                <span
                                                    className={`size-3 rounded-full border ${selected ? 'border-amber-400 bg-amber-400' : 'border-stone-300'}`}
                                                />
                                            </span>
                                            <span
                                                className={`mt-1 block text-xs leading-5 ${selected ? 'text-stone-300 dark:text-stone-600' : 'text-stone-500'}`}
                                            >
                                                {condition.copy}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>
                        <div className="grid gap-2 font-semibold">
                            <label htmlFor="listing-brand_name">
                                Brand{' '}
                                <span className="font-normal text-stone-500">
                                    (optional)
                                </span>
                            </label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-stone-400" />
                                <input
                                    id="listing-brand_name"
                                    value={brandQuery}
                                    onFocus={() => setShowBrandOptions(true)}
                                    onBlur={() =>
                                        window.setTimeout(
                                            () => setShowBrandOptions(false),
                                            150,
                                        )
                                    }
                                    onChange={(event) =>
                                        typeBrand(event.target.value)
                                    }
                                    placeholder="Search a brand or type a new one"
                                    className={`w-full py-3 pr-10 pl-11 ${inputClass}`}
                                />
                                {brandQuery !== '' && (
                                    <button
                                        type="button"
                                        onClick={() => typeBrand('')}
                                        className="absolute top-1/2 right-3 -translate-y-1/2 rounded p-1 text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-800"
                                        aria-label="Clear brand"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                                {showBrandOptions &&
                                    brandOptions.length > 0 && (
                                        <div className="absolute z-10 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-stone-200 bg-white p-1 shadow-xl dark:border-stone-700 dark:bg-stone-900">
                                            {brandOptions.map((brand) => (
                                                <button
                                                    type="button"
                                                    key={brand.id}
                                                    onMouseDown={(event) =>
                                                        event.preventDefault()
                                                    }
                                                    onClick={() =>
                                                        selectBrand(brand)
                                                    }
                                                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-amber-50 dark:hover:bg-amber-950/30"
                                                >
                                                    <Tag className="size-4 text-amber-700" />
                                                    {brand.name}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                            </div>
                            <p className="text-sm font-normal text-stone-500">
                                {form.data.brand_id
                                    ? 'Selected from the catalog.'
                                    : form.data.brand_name.trim() !== ''
                                      ? 'New brands are added to the catalog when the listing is approved.'
                                      : 'You can select a known brand or leave this blank.'}
                            </p>
                            {errorFor('brand_name') && (
                                <FieldError error={errorFor('brand_name')} />
                            )}
                        </div>
                    </div>
                )}

                {activeStep === 1 && (
                    <div className="grid gap-6 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-7 dark:border-stone-800 dark:bg-stone-900">
                        <StepIntro step={2} title="Describe the item" />
                        <div className="grid gap-5 sm:grid-cols-2">
                            <label className="grid gap-2 font-semibold">
                                Location
                                <input
                                    id="listing-location"
                                    value={form.data.location}
                                    onChange={(event) => {
                                        form.setData(
                                            'location',
                                            event.target.value,
                                        );
                                        clearError('location');
                                    }}
                                    placeholder="Colombo"
                                    className={inputClass}
                                />
                                {errorFor('location') && (
                                    <FieldError error={errorFor('location')} />
                                )}
                            </label>
                            <label className="grid gap-2 font-semibold">
                                Warranty{' '}
                                <span className="font-normal text-stone-500">
                                    (optional)
                                </span>
                                <input
                                    value={form.data.warranty}
                                    onChange={(event) =>
                                        form.setData(
                                            'warranty',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. Six months manufacturer warranty"
                                    className={inputClass}
                                />
                            </label>
                            <div className="grid gap-2 font-semibold sm:col-span-2">
                                <label htmlFor="listing-description">
                                    Description
                                </label>
                                <RichTextEditor
                                    id="listing-description"
                                    value={form.data.description}
                                    onChange={(value) => {
                                        form.setData('description', value);
                                        clearError('description');
                                    }}
                                    placeholder="Describe the item, its condition, and anything a buyer should know."
                                    error={errorFor('description')}
                                />
                                <span className="text-xs font-normal text-stone-500">
                                    Use headings, emphasis, quotes, and lists to
                                    make important details easy to scan.
                                </span>
                                {errorFor('description') && (
                                    <FieldError
                                        error={errorFor('description')}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {activeStep === 2 && (
                    <div className="grid gap-6 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-7 dark:border-stone-800 dark:bg-stone-900">
                        <StepIntro step={3} title="Set your pricing" />
                        <div className="grid gap-3 sm:grid-cols-2">
                            {[
                                {
                                    value: 'buy_now' as const,
                                    title: 'Buy now',
                                    copy: 'Set a fixed price and available stock.',
                                    icon: ShoppingBag,
                                },
                                {
                                    value: 'auction' as const,
                                    title: 'Auction',
                                    copy: 'Let buyers compete within a schedule.',
                                    icon: Gavel,
                                },
                            ].map((saleMethod) => {
                                const SaleMethodIcon = saleMethod.icon;
                                const selected =
                                    form.data.listing_type === saleMethod.value;

                                return (
                                    <button
                                        key={saleMethod.value}
                                        type="button"
                                        onClick={() =>
                                            form.setData({
                                                ...form.data,
                                                listing_type: saleMethod.value,
                                                sale_price:
                                                    saleMethod.value ===
                                                    'auction'
                                                        ? ''
                                                        : form.data.sale_price,
                                            })
                                        }
                                        aria-pressed={selected}
                                        className={`flex items-start gap-4 rounded-2xl border p-5 text-left transition ${
                                            selected
                                                ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200 dark:bg-amber-950/30 dark:ring-amber-900/50'
                                                : 'border-stone-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-stone-800'
                                        }`}
                                    >
                                        <span
                                            className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${selected ? 'bg-primary text-primary-foreground' : 'bg-stone-100 text-stone-500 dark:bg-stone-800'}`}
                                        >
                                            <SaleMethodIcon className="size-5" />
                                        </span>
                                        <span>
                                            <span className="block font-black">
                                                {saleMethod.title}
                                            </span>
                                            <span className="mt-1 block text-sm leading-5 text-stone-500">
                                                {saleMethod.copy}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        {form.data.listing_type === 'buy_now' ? (
                            <div className="grid gap-5 rounded-2xl bg-stone-50 p-5 sm:grid-cols-2 dark:bg-stone-950">
                                <NumberField
                                    id="listing-price"
                                    label="Regular price (LKR)"
                                    value={form.data.price}
                                    onChange={(value) => {
                                        form.setData('price', value);
                                        clearError('price');
                                    }}
                                    error={errorFor('price')}
                                />
                                <NumberField
                                    id="listing-sale_price"
                                    label="Offer price (optional)"
                                    value={form.data.sale_price}
                                    onChange={(value) => {
                                        form.setData('sale_price', value);
                                        clearError('sale_price');
                                    }}
                                    error={errorFor('sale_price')}
                                />
                                <NumberField
                                    id="listing-stock_quantity"
                                    label="Stock quantity"
                                    value={form.data.stock_quantity}
                                    integer
                                    onChange={(value) => {
                                        form.setData(
                                            'stock_quantity',
                                            value === '' ? '' : Number(value),
                                        );
                                        clearError('stock_quantity');
                                    }}
                                    error={errorFor('stock_quantity')}
                                />
                            </div>
                        ) : (
                            <div className="grid gap-5 rounded-2xl bg-stone-50 p-5 sm:grid-cols-2 dark:bg-stone-950">
                                <NumberField
                                    id="listing-starting_price"
                                    label="Starting price (LKR)"
                                    value={form.data.starting_price}
                                    onChange={(value) => {
                                        form.setData('starting_price', value);
                                        clearError('starting_price');
                                    }}
                                    error={errorFor('starting_price')}
                                />
                                <NumberField
                                    id="listing-reserve_price"
                                    label="Reserve price (optional)"
                                    value={form.data.reserve_price}
                                    onChange={(value) =>
                                        form.setData('reserve_price', value)
                                    }
                                />
                                <NumberField
                                    id="listing-minimum_increment"
                                    label="Minimum increment (LKR)"
                                    value={form.data.minimum_increment}
                                    onChange={(value) => {
                                        form.setData(
                                            'minimum_increment',
                                            value,
                                        );
                                        clearError('minimum_increment');
                                    }}
                                    error={errorFor('minimum_increment')}
                                />
                                <DateField
                                    id="listing-starts_at"
                                    label="Starts at"
                                    value={form.data.starts_at}
                                    onChange={(value) => {
                                        form.setData('starts_at', value);
                                        clearError('starts_at');
                                    }}
                                    error={errorFor('starts_at')}
                                />
                                <DateField
                                    id="listing-ends_at"
                                    label="Ends at"
                                    value={form.data.ends_at}
                                    onChange={(value) => {
                                        form.setData('ends_at', value);
                                        clearError('ends_at');
                                    }}
                                    error={errorFor('ends_at')}
                                    className="sm:col-span-2"
                                />
                            </div>
                        )}
                    </div>
                )}

                {activeStep === 3 && (
                    <div className="grid gap-6 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-7 dark:border-stone-800 dark:bg-stone-900">
                        <StepIntro step={4} title="Add your best photos" />
                        <div className="-mt-3 flex flex-wrap gap-2">
                            <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold text-stone-600 dark:bg-stone-800 dark:text-stone-300">
                                {totalPhotoCount}/5 selected
                            </span>
                            <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold text-stone-600 dark:bg-stone-800 dark:text-stone-300">
                                JPG, PNG or WebP
                            </span>
                            <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold text-stone-600 dark:bg-stone-800 dark:text-stone-300">
                                5 MB max each
                            </span>
                            <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold text-stone-600 dark:bg-stone-800 dark:text-stone-300">
                                1200 × 900 minimum
                            </span>
                        </div>
                        <label
                            htmlFor="listing-images"
                            onDragEnter={() => setIsDraggingImages(true)}
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
                            className={`group flex min-h-64 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-5 py-10 text-center transition ${
                                isDraggingImages
                                    ? 'scale-[1.01] border-amber-500 bg-amber-100 shadow-lg dark:bg-amber-950/40'
                                    : 'border-amber-300 bg-amber-50/60 hover:border-amber-500 hover:bg-amber-100/60 dark:bg-amber-950/20'
                            }`}
                        >
                            <span className="flex size-16 items-center justify-center rounded-2xl bg-white text-amber-700 shadow-sm ring-1 ring-amber-200 transition group-hover:-translate-y-1 dark:bg-stone-900 dark:ring-amber-900">
                                <UploadCloud className="size-8" />
                            </span>
                            <span className="mt-3 font-black">
                                {isDraggingImages
                                    ? 'Drop your photos here'
                                    : 'Drag photos here or browse files'}
                            </span>
                            <span className="mt-1 max-w-sm text-sm leading-6 text-stone-500">
                                Add up to {Math.max(0, 5 - totalPhotoCount)}{' '}
                                more. Put your strongest photo first—it becomes
                                the listing cover.
                            </span>
                            <span className="mt-4 rounded-full bg-stone-950 px-5 py-2.5 text-sm font-bold text-white dark:bg-stone-50 dark:text-stone-950">
                                Choose photos
                            </span>
                            <input
                                id="listing-images"
                                className="sr-only"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) => {
                                    void addImages(event.target.files);
                                    event.target.value = '';
                                }}
                            />
                        </label>
                        {(existingMedia.length > 0 ||
                            imagePreviews.length > 0) && (
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                {existingMedia.map((media) => (
                                    <div
                                        key={media.id}
                                        className="group relative aspect-[4/3] overflow-hidden rounded-2xl bg-stone-100 shadow-sm ring-1 ring-stone-200 dark:bg-stone-800 dark:ring-stone-700"
                                    >
                                        <img
                                            className="h-full w-full object-cover transition group-hover:scale-105"
                                            src={media.url}
                                            alt="Existing listing photo"
                                        />
                                        {media === existingMedia[0] && (
                                            <span className="absolute top-2 left-2 rounded-full bg-amber-400 px-2 py-1 text-[10px] font-black text-stone-950">
                                                Cover
                                            </span>
                                        )}
                                        <span className="absolute right-2 bottom-2 rounded-full bg-stone-950/75 px-2 py-1 text-[10px] font-bold text-white backdrop-blur-sm">
                                            Saved
                                        </span>
                                    </div>
                                ))}
                                {imagePreviews.map(
                                    ({ file, url, size, crop }, index) => (
                                        <div
                                            key={url}
                                            className="group relative aspect-[4/3] overflow-hidden rounded-2xl bg-stone-100 shadow-sm ring-1 ring-stone-200 dark:bg-stone-800 dark:ring-stone-700"
                                        >
                                            {size && crop && (
                                                <CroppedImagePreview
                                                    src={url}
                                                    alt={file.name}
                                                    size={size}
                                                    crop={crop}
                                                />
                                            )}
                                            {existingMedia.length === 0 &&
                                                index === 0 && (
                                                    <span className="absolute top-2 left-2 rounded-full bg-amber-400 px-2 py-1 text-[10px] font-black text-stone-950">
                                                        Cover
                                                    </span>
                                                )}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    openCropEditor(index)
                                                }
                                                className="absolute bottom-2 left-2 rounded-full bg-white/95 px-3 py-1.5 text-[10px] font-black text-stone-950 shadow-sm backdrop-blur-sm transition hover:bg-amber-400"
                                            >
                                                Adjust crop
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeImage(index)
                                                }
                                                className="absolute top-2 right-2 rounded-full bg-stone-950/80 p-2 text-white backdrop-blur-sm transition hover:bg-red-600 sm:opacity-0 sm:group-hover:opacity-100"
                                                aria-label={`Remove ${file.name}`}
                                            >
                                                <X className="size-4" />
                                            </button>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                        {errorFor('images') && (
                            <FieldError error={errorFor('images')} />
                        )}
                    </div>
                )}

                {activeStep === 4 && (
                    <div className="grid gap-6 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-7 dark:border-stone-800 dark:bg-stone-900">
                        <StepIntro step={5} title="Review your listing" />
                        <p className="-mt-4 text-sm text-stone-500">
                            This is how buyers will first experience your item.
                            Check the preview and finish any missing details.
                        </p>
                        <div className="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.7fr)]">
                            <section className="overflow-hidden rounded-2xl border border-stone-200 bg-stone-50 shadow-sm dark:border-stone-800 dark:bg-stone-950">
                                <div className="relative aspect-[4/3] overflow-hidden bg-stone-200 dark:bg-stone-800">
                                    {primaryPhotoUrl ? (
                                        <img
                                            src={primaryPhotoUrl}
                                            alt="Listing cover preview"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : imagePreviews[0]?.size &&
                                      imagePreviews[0]?.crop ? (
                                        <CroppedImagePreview
                                            src={imagePreviews[0].url}
                                            alt="Listing cover preview"
                                            size={imagePreviews[0].size}
                                            crop={imagePreviews[0].crop}
                                        />
                                    ) : (
                                        <div className="flex h-full flex-col items-center justify-center gap-3 text-stone-400">
                                            <ImagePlus className="size-12" />
                                            <span className="text-sm font-bold">
                                                Your cover photo appears here
                                            </span>
                                        </div>
                                    )}
                                    <span className="absolute top-4 left-4 rounded-full bg-white/95 px-3 py-1.5 text-xs font-black text-stone-950 shadow-sm backdrop-blur-sm">
                                        {form.data.listing_type === 'auction'
                                            ? 'Auction'
                                            : 'Buy now'}
                                    </span>
                                    <span className="absolute right-4 bottom-4 rounded-full bg-stone-950/80 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-sm">
                                        {totalPhotoCount}{' '}
                                        {totalPhotoCount === 1
                                            ? 'photo'
                                            : 'photos'}
                                    </span>
                                </div>
                                <div className="p-5 sm:p-6">
                                    <div className="flex flex-wrap items-center gap-2 text-xs font-bold text-stone-500">
                                        <span>{selectedCategory?.name}</span>
                                        {selectedCategory && (
                                            <span aria-hidden="true">•</span>
                                        )}
                                        <span className="capitalize">
                                            {form.data.condition}
                                        </span>
                                    </div>
                                    <h3 className="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                                        {form.data.title ||
                                            'Your listing title'}
                                    </h3>
                                    {richTextPlainText(
                                        form.data.description,
                                    ) ? (
                                        <RichTextContent
                                            value={form.data.description}
                                            className="mt-3 line-clamp-3 text-sm leading-6 text-stone-600 dark:text-stone-300 [&_h2]:text-base [&_h3]:text-sm"
                                        />
                                    ) : (
                                        <p className="mt-3 text-sm leading-6 text-stone-500">
                                            Your product description will appear
                                            here.
                                        </p>
                                    )}
                                    <div className="mt-5 flex flex-col gap-3 border-t border-stone-200 pt-5 sm:flex-row sm:items-end sm:justify-between dark:border-stone-800">
                                        <div>
                                            <span className="block text-xs font-bold tracking-wider text-stone-500 uppercase">
                                                {form.data.listing_type ===
                                                'auction'
                                                    ? 'Starting bid'
                                                    : 'Price'}
                                            </span>
                                            <span className="mt-1 block text-2xl font-black text-amber-700 dark:text-amber-400">
                                                {formatPrice(
                                                    form.data.listing_type ===
                                                        'auction'
                                                        ? form.data
                                                              .starting_price
                                                        : form.data.price,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold text-stone-500">
                                            <span className="inline-flex items-center gap-1.5">
                                                <MapPin className="size-4" />
                                                {form.data.location ||
                                                    'Location'}
                                            </span>
                                            <span className="inline-flex items-center gap-1.5">
                                                <Tag className="size-4" />
                                                {selectedBrand?.name ??
                                                    (form.data.brand_name ||
                                                        'Unbranded')}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <aside className="grid content-start gap-4">
                                <section className="rounded-2xl border border-stone-200 p-5 dark:border-stone-800">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-xl bg-stone-950 text-white dark:bg-stone-50 dark:text-stone-950">
                                            <Eye className="size-5" />
                                        </span>
                                        <div>
                                            <h3 className="font-black">
                                                Listing health
                                            </h3>
                                            <p className="text-xs text-stone-500">
                                                {
                                                    listingHealthChecks.filter(
                                                        (check) =>
                                                            check.complete,
                                                    ).length
                                                }{' '}
                                                of {listingHealthChecks.length}{' '}
                                                essentials complete
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-5 grid gap-3">
                                        {listingHealthChecks.map((check) => (
                                            <div
                                                key={check.label}
                                                className="flex items-center gap-3 text-sm font-semibold"
                                            >
                                                {check.complete ? (
                                                    <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
                                                ) : (
                                                    <span className="mx-0.5 size-4 shrink-0 rounded-full border-2 border-stone-300 dark:border-stone-700" />
                                                )}
                                                <span
                                                    className={
                                                        check.complete
                                                            ? ''
                                                            : 'text-stone-500'
                                                    }
                                                >
                                                    {check.label}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                                {form.data.brand_name.trim() !== '' &&
                                    !form.data.brand_id && (
                                        <div className="rounded-2xl bg-amber-50 p-4 text-sm text-amber-950 ring-1 ring-amber-200 dark:bg-amber-950/30 dark:text-amber-100 dark:ring-amber-900/60">
                                            <Sparkles className="mr-2 inline size-4" />
                                            <strong>
                                                {form.data.brand_name}
                                            </strong>{' '}
                                            will be checked and added to the
                                            catalog when this listing is
                                            approved.
                                        </div>
                                    )}
                                {!canSubmit && (
                                    <div className="rounded-2xl border border-stone-200 p-4 text-sm leading-6 text-stone-600 dark:border-stone-800 dark:text-stone-300">
                                        You can save this listing as a draft.
                                        Submission unlocks when your seller
                                        account is approved.
                                    </div>
                                )}
                            </aside>
                        </div>
                        <div className="flex items-center gap-3 pt-2">
                            <span className="h-px flex-1 bg-stone-200 dark:bg-stone-800" />
                            <span className="text-xs font-black tracking-wider text-stone-400 uppercase">
                                Listing details
                            </span>
                            <span className="h-px flex-1 bg-stone-200 dark:bg-stone-800" />
                        </div>
                        <ReviewSection
                            title="Product"
                            onEdit={() => setActiveStep(0)}
                        >
                            <SummaryItem
                                label="Title"
                                value={form.data.title || 'Not set'}
                            />
                            <SummaryItem
                                label="Category"
                                value={selectedCategory?.name ?? 'Not set'}
                            />
                            <SummaryItem
                                label="Brand"
                                value={
                                    selectedBrand?.name ??
                                    (form.data.brand_name || 'Not set')
                                }
                            />
                            <SummaryItem
                                label="Condition"
                                value={form.data.condition}
                            />
                        </ReviewSection>
                        <ReviewSection
                            title="Details"
                            onEdit={() => setActiveStep(1)}
                        >
                            <SummaryItem
                                label="Location"
                                value={form.data.location || 'Not set'}
                            />
                            <SummaryItem
                                label="Warranty"
                                value={form.data.warranty || 'Not specified'}
                            />
                            <div className="sm:col-span-2">
                                <span className="block text-xs font-bold tracking-wider text-stone-500 uppercase">
                                    Description
                                </span>
                                {richTextPlainText(form.data.description) ? (
                                    <RichTextContent
                                        value={form.data.description}
                                        className="mt-1 text-sm"
                                    />
                                ) : (
                                    <span className="mt-1 block text-sm">
                                        Not set
                                    </span>
                                )}
                            </div>
                        </ReviewSection>
                        <ReviewSection
                            title="Pricing"
                            onEdit={() => setActiveStep(2)}
                        >
                            <SummaryItem
                                label="Sale method"
                                value={
                                    form.data.listing_type === 'auction'
                                        ? 'Auction'
                                        : 'Buy now'
                                }
                            />
                            {form.data.listing_type === 'auction' ? (
                                <>
                                    <SummaryItem
                                        label="Starting price"
                                        value={formatPrice(
                                            form.data.starting_price,
                                        )}
                                    />
                                    <SummaryItem
                                        label="Minimum increment"
                                        value={formatPrice(
                                            form.data.minimum_increment,
                                        )}
                                    />
                                    <SummaryItem
                                        label="Starts"
                                        value={formatDate(form.data.starts_at)}
                                    />
                                    <SummaryItem
                                        label="Ends"
                                        value={formatDate(form.data.ends_at)}
                                    />
                                </>
                            ) : (
                                <>
                                    <SummaryItem
                                        label="Price"
                                        value={formatPrice(form.data.price)}
                                    />
                                    <SummaryItem
                                        label="Offer price"
                                        value={
                                            form.data.sale_price
                                                ? formatPrice(
                                                      form.data.sale_price,
                                                  )
                                                : 'No discount'
                                        }
                                    />
                                    <SummaryItem
                                        label="Stock"
                                        value={
                                            form.data.stock_quantity === ''
                                                ? 'Not set'
                                                : `${form.data.stock_quantity} available`
                                        }
                                    />
                                </>
                            )}
                        </ReviewSection>
                        <ReviewSection
                            title="Photos"
                            onEdit={() => setActiveStep(3)}
                        >
                            <SummaryItem
                                label="Photos"
                                value={`${totalPhotoCount} selected`}
                            />
                        </ReviewSection>
                    </div>
                )}

                <div className="sticky bottom-4 z-20 overflow-hidden rounded-2xl border border-stone-200 bg-white/95 shadow-xl shadow-stone-950/10 backdrop-blur-xl dark:border-stone-700 dark:bg-stone-900/95">
                    {form.progress && (
                        <div className="h-1.5 bg-stone-100 dark:bg-stone-800">
                            <div
                                className="h-full bg-primary transition-[width]"
                                style={{
                                    width: `${form.progress.percentage}%`,
                                }}
                            />
                        </div>
                    )}
                    <div className="flex flex-col-reverse gap-3 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                        <div className="flex items-center gap-4">
                            {activeStep > 0 ? (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setActiveStep((step) => step - 1)
                                    }
                                    className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-stone-300 px-5 py-3 font-bold transition hover:bg-stone-50 sm:flex-none dark:border-stone-700 dark:hover:bg-stone-800"
                                >
                                    <ChevronLeft className="size-4" />
                                    Previous
                                </button>
                            ) : (
                                <span className="hidden sm:block" />
                            )}
                            <p className="hidden text-xs text-stone-500 md:block">
                                Step {activeStep + 1} of {steps.length}
                                <span className="mx-2">•</span>
                                {activeStep === steps.length - 1
                                    ? 'Review everything before finishing'
                                    : 'Your progress stays here while you move between steps'}
                            </p>
                        </div>
                        {activeStep < steps.length - 1 ? (
                            <button
                                type="button"
                                onClick={nextStep}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-black text-primary-foreground shadow-sm transition hover:bg-primary/90"
                            >
                                Continue
                                <ChevronRight className="size-4" />
                            </button>
                        ) : (
                            <div className="grid gap-2 sm:flex">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl border border-stone-300 px-5 py-3 font-bold transition hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-stone-700 dark:hover:bg-stone-800"
                                >
                                    {form.processing
                                        ? 'Saving…'
                                        : isNewListing
                                          ? 'Save draft'
                                          : 'Save changes'}
                                </button>
                                <button
                                    type="button"
                                    disabled={form.processing || !canSubmit}
                                    onClick={() => submit(true)}
                                    className="rounded-xl bg-primary px-5 py-3 font-black text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {form.processing
                                        ? 'Submitting…'
                                        : 'Submit for review'}
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </form>
            <Dialog
                open={cropImageIndex !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCropImageIndex(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Adjust photo crop</DialogTitle>
                        <DialogDescription>
                            Pan and zoom until the product is framed inside the
                            4:3 area. This matches the final listing image.
                        </DialogDescription>
                    </DialogHeader>
                    {cropImage?.crop && (
                        <>
                            <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-stone-950">
                                <Cropper
                                    key={cropEditorVersion}
                                    image={cropImage.url}
                                    crop={cropPosition}
                                    zoom={cropZoom}
                                    minZoom={1}
                                    maxZoom={maximumCropZoom}
                                    aspect={4 / 3}
                                    initialCroppedAreaPixels={
                                        draftCrop ?? cropImage.crop
                                    }
                                    onCropChange={setCropPosition}
                                    onZoomChange={setCropZoom}
                                    onCropComplete={(
                                        _croppedArea: Area,
                                        croppedAreaPixels: Area,
                                    ) =>
                                        setDraftCrop(
                                            normalizeCrop(croppedAreaPixels),
                                        )
                                    }
                                    showGrid
                                />
                            </div>
                            <label className="grid gap-2 text-sm font-bold">
                                Zoom
                                <input
                                    type="range"
                                    min={1}
                                    max={maximumCropZoom}
                                    step={0.01}
                                    value={cropZoom}
                                    onInput={(event) =>
                                        setCropZoom(
                                            Number(event.currentTarget.value),
                                        )
                                    }
                                    className="w-full accent-amber-500"
                                />
                            </label>
                        </>
                    )}
                    <DialogFooter>
                        <button
                            type="button"
                            onClick={() => setCropImageIndex(null)}
                            className="rounded-xl border border-stone-300 px-5 py-2.5 font-bold transition hover:bg-stone-50 dark:border-stone-700 dark:hover:bg-stone-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={resetCropEditor}
                            className="rounded-xl border border-stone-300 px-5 py-2.5 font-bold transition hover:bg-stone-50 dark:border-stone-700 dark:hover:bg-stone-800"
                        >
                            Reset
                        </button>
                        <button
                            type="button"
                            onClick={applyCrop}
                            className="rounded-xl bg-primary px-5 py-2.5 font-black text-primary-foreground transition hover:bg-primary/90"
                        >
                            Apply crop
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}

function StepIntro({
    step,
    title,
}: {
    step: number;
    title: string;
}): ReactNode {
    return (
        <div className="flex items-start gap-4">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary font-black text-primary-foreground shadow-sm">
                {step}
            </span>
            <div>
                <p className="text-xs font-bold tracking-wider text-primary uppercase">
                    Step {step} of {steps.length}
                </p>
                <h2 className="mt-1 text-2xl font-black tracking-tight sm:text-3xl">
                    {title}
                </h2>
                <p className="mt-1 text-sm text-stone-500">
                    {steps[step - 1]?.[1]}
                </p>
            </div>
        </div>
    );
}

function FieldError({ error }: { error?: string }): ReactNode {
    return error ? (
        <span className="text-sm font-medium text-red-600 dark:text-red-400">
            {error}
        </span>
    ) : null;
}

function NumberField({
    className = '',
    error,
    id,
    integer = false,
    label,
    onChange,
    value,
}: {
    className?: string;
    error?: string;
    id?: string;
    integer?: boolean;
    label: string;
    onChange: (value: string) => void;
    value: number | string;
}): ReactNode {
    return (
        <label className={`grid gap-2 font-semibold ${className}`}>
            {label}
            <input
                id={id}
                type="number"
                min="1"
                step={integer ? '1' : '0.01'}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="rounded-xl border border-stone-300 bg-transparent px-4 py-3 transition outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 dark:border-stone-700"
            />
            {error && <FieldError error={error} />}
        </label>
    );
}

function DateField({
    className = '',
    error,
    id,
    label,
    onChange,
    value,
}: {
    className?: string;
    error?: string;
    id: string;
    label: string;
    onChange: (value: string) => void;
    value: string;
}): ReactNode {
    return (
        <label className={`grid gap-2 font-semibold ${className}`}>
            {label}
            <input
                id={id}
                type="datetime-local"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="rounded-xl border border-stone-300 bg-transparent px-4 py-3 transition outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 dark:border-stone-700"
            />
            {error && <FieldError error={error} />}
        </label>
    );
}

function ReviewSection({
    children,
    onEdit,
    title,
}: {
    children: ReactNode;
    onEdit: () => void;
    title: string;
}): ReactNode {
    return (
        <section className="rounded-2xl border border-stone-200 bg-stone-50/60 p-5 dark:border-stone-800 dark:bg-stone-950/50">
            <div className="mb-4 flex items-center justify-between gap-4">
                <h3 className="font-black">{title}</h3>
                <button
                    type="button"
                    onClick={onEdit}
                    className="text-sm font-bold text-primary hover:text-primary/80"
                >
                    Edit
                </button>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

function SummaryItem({
    label,
    value,
}: {
    label: string;
    value: string;
}): ReactNode {
    return (
        <p>
            <span className="block text-xs font-bold tracking-wider text-stone-500 uppercase">
                {label}
            </span>
            <span className="mt-1 block text-sm font-semibold capitalize">
                {value}
            </span>
        </p>
    );
}

function formatPrice(value: string): string {
    return value === '' ? 'Not set' : `LKR ${Number(value).toLocaleString()}`;
}

function formatDate(value: string): string {
    return value === '' ? 'Not set' : new Date(value).toLocaleString();
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
}): ReactNode {
    return (
        <img
            src={src}
            alt={alt}
            className="absolute max-w-none"
            style={{
                height: `${(size.height / crop.height) * 100}%`,
                left: `${(-crop.x / crop.width) * 100}%`,
                top: `${(-crop.y / crop.height) * 100}%`,
                width: `${(size.width / crop.width) * 100}%`,
            }}
        />
    );
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
