import { Link, router } from '@inertiajs/react';
import {
    Armchair,
    Baby,
    BookOpen,
    BriefcaseBusiness,
    Camera,
    Car,
    ChevronDown,
    ChevronRight,
    Church,
    Dumbbell,
    FileText,
    Gamepad2,
    HeartPulse,
    House,
    Luggage,
    Menu,
    Monitor,
    PackageSearch,
    Palette,
    PawPrint,
    Puzzle,
    Shirt,
    Utensils,
    Wrench,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { FocusEvent, KeyboardEvent } from 'react';
import { index as listingsIndex } from '@/routes/listings';

export type StorefrontCategory = {
    id: number;
    name: string;
    slug: string;
    children: StorefrontCategoryChild[];
};

export type StorefrontCategoryChild = {
    id: number;
    name: string;
    slug: string;
};

const categoryIcons: Record<string, LucideIcon> = {
    'animals-pet-supplies': PawPrint,
    'apparel-accessories': Shirt,
    'arts-entertainment': Palette,
    'baby-toddler': Baby,
    'business-industrial': BriefcaseBusiness,
    'cameras-optics': Camera,
    electronics: Monitor,
    'food-beverages-tobacco': Utensils,
    furniture: Armchair,
    hardware: Wrench,
    'health-beauty': HeartPulse,
    'home-garden': House,
    'luggage-bags': Luggage,
    media: BookOpen,
    'office-supplies': FileText,
    'religious-ceremonial': Church,
    software: Gamepad2,
    'sporting-goods': Dumbbell,
    'toys-games': Puzzle,
    'vehicles-parts': Car,
};

function categoryHref(slug: string) {
    return listingsIndex({ query: { category: slug } });
}

export function categoryContainsSlug(
    category: StorefrontCategory,
    selectedCategorySlug: string | null,
) {
    return (
        category.slug === selectedCategorySlug ||
        category.children.some((child) => child.slug === selectedCategorySlug)
    );
}

function selectedParent(
    categories: StorefrontCategory[],
    selectedCategorySlug: string | null,
) {
    return categories.find((category) =>
        categoryContainsSlug(category, selectedCategorySlug),
    );
}

export function DesktopStorefrontCategoryMenu({
    categories,
    selectedCategorySlug,
    isAllProductsSelected,
}: {
    categories: StorefrontCategory[];
    selectedCategorySlug: string | null;
    isAllProductsSelected: boolean;
}) {
    const menuId = useId();
    const closeTimer = useRef<number | null>(null);
    const menuRegion = useRef<HTMLDivElement>(null);
    const currentParent = selectedParent(categories, selectedCategorySlug);
    const [isOpen, setIsOpen] = useState(false);
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(
        currentParent?.id ?? categories[0]?.id ?? null,
    );
    const activeCategory =
        categories.find((category) => category.id === activeCategoryId) ??
        categories[0];

    const cancelClose = () => {
        if (closeTimer.current !== null) {
            window.clearTimeout(closeTimer.current);
            closeTimer.current = null;
        }
    };

    const openMenu = () => {
        cancelClose();
        setActiveCategoryId(currentParent?.id ?? categories[0]?.id ?? null);
        setIsOpen(true);
    };

    const focusMenu = () => {
        cancelClose();

        if (!isOpen) {
            setActiveCategoryId(currentParent?.id ?? categories[0]?.id ?? null);
            setIsOpen(true);
        }
    };

    const closeMenu = () => {
        cancelClose();
        setIsOpen(false);
    };

    const scheduleClose = () => {
        cancelClose();
        closeTimer.current = window.setTimeout(() => setIsOpen(false), 180);
    };

    const handleBlur = (event: FocusEvent<HTMLDivElement>) => {
        if (!menuRegion.current?.contains(event.relatedTarget as Node | null)) {
            scheduleClose();
        }
    };

    useEffect(() => router.on('navigate', () => setIsOpen(false)), []);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleEscape = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        };

        document.addEventListener('keydown', handleEscape);

        return () => document.removeEventListener('keydown', handleEscape);
    });

    useEffect(
        () => () => {
            if (closeTimer.current !== null) {
                window.clearTimeout(closeTimer.current);
            }
        },
        [],
    );

    return (
        <div
            ref={menuRegion}
            className="relative z-30"
            onPointerEnter={openMenu}
            onPointerLeave={scheduleClose}
            onFocus={focusMenu}
            onBlur={handleBlur}
        >
            <button
                type="button"
                aria-controls={menuId}
                aria-expanded={isOpen}
                onClick={openMenu}
                className="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary px-4 text-sm font-black text-primary-foreground shadow-sm shadow-primary/25 transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-offset-slate-950"
            >
                <Menu className="size-4" />
                All categories
                <ChevronDown
                    className={`size-4 transition ${isOpen ? 'rotate-180' : ''}`}
                />
            </button>

            {isOpen && (
                <>
                    <button
                        type="button"
                        aria-label="Close category menu"
                        onClick={closeMenu}
                        className="fixed inset-x-0 top-[7.75rem] bottom-0 z-10 cursor-default bg-slate-950/45 backdrop-blur-[1px]"
                    />
                    <div
                        id={menuId}
                        className="absolute top-[calc(100%+0.5rem)] left-0 z-20 flex max-h-[calc(100vh-9rem)] rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/20 dark:border-slate-700 dark:bg-slate-950"
                    >
                        <nav
                            aria-label="Marketplace categories"
                            className="w-80 shrink-0 overflow-y-auto p-3"
                        >
                            <p className="px-3 pt-2 pb-3 text-xs font-black tracking-[0.16em] text-primary uppercase">
                                Shop by category
                            </p>
                            <Link
                                href={listingsIndex()}
                                onClick={closeMenu}
                                aria-current={
                                    isAllProductsSelected ? 'page' : undefined
                                }
                                className={`mb-1 flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-bold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                    isAllProductsSelected
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-slate-700 hover:bg-primary/10 hover:text-primary dark:text-slate-200'
                                }`}
                            >
                                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <Menu className="size-4" />
                                </span>
                                All products
                            </Link>
                            {categories.map((category) => {
                                const CategoryIcon =
                                    categoryIcons[category.slug] ??
                                    PackageSearch;
                                const isActive =
                                    category.id === activeCategory?.id;
                                const isSelected = categoryContainsSlug(
                                    category,
                                    selectedCategorySlug,
                                );

                                return (
                                    <Link
                                        key={category.id}
                                        href={categoryHref(category.slug)}
                                        onClick={closeMenu}
                                        onPointerEnter={() =>
                                            setActiveCategoryId(category.id)
                                        }
                                        onFocus={() =>
                                            setActiveCategoryId(category.id)
                                        }
                                        aria-current={
                                            category.slug ===
                                            selectedCategorySlug
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                            isActive || isSelected
                                                ? 'bg-primary/10 text-primary dark:bg-primary/15'
                                                : 'text-slate-700 hover:bg-slate-100 hover:text-primary dark:text-slate-200 dark:hover:bg-slate-900'
                                        }`}
                                    >
                                        <span
                                            className={`grid size-8 shrink-0 place-items-center rounded-lg transition ${
                                                isActive || isSelected
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-slate-100 text-slate-500 group-hover:text-primary dark:bg-slate-900 dark:text-slate-300'
                                            }`}
                                        >
                                            <CategoryIcon className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            {category.name}
                                        </span>
                                        {category.children.length > 0 && (
                                            <ChevronRight className="size-4 shrink-0" />
                                        )}
                                    </Link>
                                );
                            })}
                        </nav>

                        {activeCategory &&
                            activeCategory.children.length > 0 && (
                                <section
                                    aria-label={`${activeCategory.name} subcategories`}
                                    className="w-80 overflow-y-auto border-l border-slate-200 p-5 dark:border-slate-800"
                                >
                                    <p className="text-xs font-black tracking-[0.16em] text-slate-400 uppercase">
                                        Explore
                                    </p>
                                    <Link
                                        href={categoryHref(activeCategory.slug)}
                                        onClick={closeMenu}
                                        className="mt-1 flex items-center justify-between rounded-lg py-2 text-lg font-black text-slate-950 transition hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:text-white"
                                    >
                                        {activeCategory.name}
                                        <ChevronRight className="size-5" />
                                    </Link>
                                    <div className="mt-3 flex flex-col gap-1">
                                        {activeCategory.children.map(
                                            (child) => (
                                                <Link
                                                    key={child.id}
                                                    href={categoryHref(
                                                        child.slug,
                                                    )}
                                                    onClick={closeMenu}
                                                    aria-current={
                                                        child.slug ===
                                                        selectedCategorySlug
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={`rounded-lg px-3 py-2.5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                                        child.slug ===
                                                        selectedCategorySlug
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'text-slate-600 hover:bg-primary/10 hover:text-primary dark:text-slate-300'
                                                    }`}
                                                >
                                                    {child.name}
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                </section>
                            )}
                    </div>
                </>
            )}
        </div>
    );
}

export function MobileStorefrontCategoryMenu({
    categories,
    selectedCategorySlug,
    isAllProductsSelected,
}: {
    categories: StorefrontCategory[];
    selectedCategorySlug: string | null;
    isAllProductsSelected: boolean;
}) {
    const drawerId = useId();
    const trigger = useRef<HTMLButtonElement>(null);
    const closeButton = useRef<HTMLButtonElement>(null);
    const drawer = useRef<HTMLElement>(null);
    const currentParent = selectedParent(categories, selectedCategorySlug);
    const [isOpen, setIsOpen] = useState(false);
    const [expandedCategoryId, setExpandedCategoryId] = useState<number | null>(
        currentParent?.id ?? null,
    );

    const openMenu = () => {
        setExpandedCategoryId(currentParent?.id ?? null);
        setIsOpen(true);
    };

    const closeMenu = (restoreFocus = false) => {
        setIsOpen(false);

        if (restoreFocus) {
            window.setTimeout(() => trigger.current?.focus(), 0);
        }
    };

    const handleDrawerKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu(true);

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements = drawer.current?.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled])',
        );

        if (!focusableElements || focusableElements.length === 0) {
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    };

    useEffect(() => router.on('navigate', () => setIsOpen(false)), []);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        closeButton.current?.focus();

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [isOpen]);

    return (
        <div className="lg:hidden">
            <button
                ref={trigger}
                type="button"
                aria-controls={drawerId}
                aria-expanded={isOpen}
                aria-label="Open category menu"
                onClick={openMenu}
                className="grid size-10 place-items-center rounded-full text-slate-700 transition hover:bg-primary/10 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:text-slate-200"
            >
                <Menu className="size-5" />
            </button>

            {isOpen && (
                <>
                    <button
                        type="button"
                        aria-label="Close category menu"
                        onClick={() => closeMenu(true)}
                        className="fixed inset-0 z-50 cursor-default bg-slate-950/55 backdrop-blur-[1px]"
                    />
                    <aside
                        ref={drawer}
                        id={drawerId}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Marketplace categories"
                        onKeyDown={handleDrawerKeyDown}
                        className="fixed inset-y-0 left-0 z-60 flex w-[min(22rem,88vw)] flex-col bg-white shadow-2xl dark:bg-slate-950"
                    >
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                            <div>
                                <p className="text-xs font-black tracking-[0.16em] text-primary uppercase">
                                    Shop your way
                                </p>
                                <p className="mt-1 text-lg font-black">
                                    All categories
                                </p>
                            </div>
                            <button
                                ref={closeButton}
                                type="button"
                                aria-label="Close category menu"
                                onClick={() => closeMenu(true)}
                                className="grid size-10 place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:hover:bg-slate-900"
                            >
                                <X className="size-5" />
                            </button>
                        </div>
                        <nav className="flex-1 overflow-y-auto p-3">
                            <Link
                                href={listingsIndex()}
                                onClick={() => closeMenu()}
                                aria-current={
                                    isAllProductsSelected ? 'page' : undefined
                                }
                                className={`mb-2 flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-bold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                    isAllProductsSelected
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-primary/10 text-primary'
                                }`}
                            >
                                <span className="grid size-8 place-items-center rounded-lg bg-white/15">
                                    <Menu className="size-4" />
                                </span>
                                All products
                            </Link>
                            {categories.map((category) => {
                                const CategoryIcon =
                                    categoryIcons[category.slug] ??
                                    PackageSearch;
                                const isExpanded =
                                    category.id === expandedCategoryId;
                                const isSelected = categoryContainsSlug(
                                    category,
                                    selectedCategorySlug,
                                );

                                if (category.children.length === 0) {
                                    return (
                                        <Link
                                            key={category.id}
                                            href={categoryHref(category.slug)}
                                            onClick={() => closeMenu()}
                                            aria-current={
                                                category.slug ===
                                                selectedCategorySlug
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className={`flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                                isSelected
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900'
                                            }`}
                                        >
                                            <span className="grid size-8 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-300">
                                                <CategoryIcon className="size-4" />
                                            </span>
                                            {category.name}
                                        </Link>
                                    );
                                }

                                return (
                                    <div key={category.id}>
                                        <button
                                            type="button"
                                            aria-expanded={isExpanded}
                                            onClick={() =>
                                                setExpandedCategoryId(
                                                    isExpanded
                                                        ? null
                                                        : category.id,
                                                )
                                            }
                                            className={`flex min-h-12 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                                isExpanded || isSelected
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900'
                                            }`}
                                        >
                                            <span className="grid size-8 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-300">
                                                <CategoryIcon className="size-4" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                {category.name}
                                            </span>
                                            <ChevronDown
                                                className={`size-4 transition ${isExpanded ? 'rotate-180' : ''}`}
                                            />
                                        </button>
                                        {isExpanded && (
                                            <div className="ml-7 border-l border-primary/20 py-1 pl-4">
                                                <Link
                                                    href={categoryHref(
                                                        category.slug,
                                                    )}
                                                    onClick={() => closeMenu()}
                                                    aria-current={
                                                        category.slug ===
                                                        selectedCategorySlug
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className="block rounded-lg px-3 py-2.5 text-sm font-black text-primary transition hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                                >
                                                    View all {category.name}
                                                </Link>
                                                {category.children.map(
                                                    (child) => (
                                                        <Link
                                                            key={child.id}
                                                            href={categoryHref(
                                                                child.slug,
                                                            )}
                                                            onClick={() =>
                                                                closeMenu()
                                                            }
                                                            aria-current={
                                                                child.slug ===
                                                                selectedCategorySlug
                                                                    ? 'page'
                                                                    : undefined
                                                            }
                                                            className={`block rounded-lg px-3 py-2.5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                                                child.slug ===
                                                                selectedCategorySlug
                                                                    ? 'bg-primary text-primary-foreground'
                                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-900'
                                                            }`}
                                                        >
                                                            {child.name}
                                                        </Link>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </nav>
                    </aside>
                </>
            )}
        </div>
    );
}
