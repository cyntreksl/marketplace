import { ImagePlus, RotateCcw, UploadCloud, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ArtworkCrop = {
    x: number;
    y: number;
    width: number;
    height: number;
};

type ImageSize = { width: number; height: number };

export function CategoryArtworkUploader({
    id,
    name,
    cropName,
    label,
    description,
    aspect,
    minimumWidth,
    minimumHeight,
    existingUrl = null,
    imageError,
    cropError,
    required = false,
}: {
    id: string;
    name: string;
    cropName: string;
    label: string;
    description: string;
    aspect: number;
    minimumWidth: number;
    minimumHeight: number;
    existingUrl?: string | null;
    imageError?: string;
    cropError?: string;
    required?: boolean;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [imageSize, setImageSize] = useState<ImageSize | null>(null);
    const [crop, setCrop] = useState<ArtworkCrop | null>(null);
    const [draftCrop, setDraftCrop] = useState<ArtworkCrop | null>(null);
    const [cropPosition, setCropPosition] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [editorOpen, setEditorOpen] = useState(false);
    const [editorVersion, setEditorVersion] = useState(0);
    const [clientError, setClientError] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const sourceUrl = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(
        () => () => {
            if (sourceUrl) {
                URL.revokeObjectURL(sourceUrl);
            }
        },
        [sourceUrl],
    );

    const maximumZoom =
        imageSize === null
            ? 1
            : Math.max(
                  1,
                  Math.min(
                      centeredCrop(imageSize, aspect).width / minimumWidth,
                      centeredCrop(imageSize, aspect).height / minimumHeight,
                  ),
              );

    async function selectFile(selectedFile: File | undefined): Promise<void> {
        if (!selectedFile) {
            return;
        }

        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(
                selectedFile.type,
            ) ||
            selectedFile.size > 5 * 1024 * 1024
        ) {
            rejectSelection(
                'Use a JPG, PNG, or WebP image no larger than 5 MB.',
            );

            return;
        }

        const size = await readImageSize(selectedFile).catch(() => null);

        if (
            size === null ||
            size.width < minimumWidth ||
            size.height < minimumHeight ||
            size.width > 6000 ||
            size.height > 6000
        ) {
            rejectSelection(
                `Use an image from ${minimumWidth} × ${minimumHeight} to 6000 × 6000 pixels.`,
            );

            return;
        }

        const initialCrop = centeredCrop(size, aspect);
        setFile(selectedFile);
        setImageSize(size);
        setCrop(initialCrop);
        setDraftCrop(initialCrop);
        setCropPosition({ x: 0, y: 0 });
        setZoom(1);
        setClientError(null);
        setEditorVersion((version) => version + 1);
        setEditorOpen(true);
    }

    function rejectSelection(message: string): void {
        setClientError(message);
        setFile(null);
        setImageSize(null);
        setCrop(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    function onInputChange(event: ChangeEvent<HTMLInputElement>): void {
        void selectFile(event.target.files?.[0]);
    }

    function onDrop(event: DragEvent<HTMLLabelElement>): void {
        event.preventDefault();
        setIsDragging(false);
        const droppedFile = event.dataTransfer.files[0];

        if (!droppedFile || !inputRef.current) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(droppedFile);
        inputRef.current.files = transfer.files;
        void selectFile(droppedFile);
    }

    function resetEditor(): void {
        if (!imageSize) {
            return;
        }

        setDraftCrop(centeredCrop(imageSize, aspect));
        setCropPosition({ x: 0, y: 0 });
        setZoom(1);
        setEditorVersion((version) => version + 1);
    }

    function applyCrop(): void {
        if (!draftCrop) {
            return;
        }

        if (
            draftCrop.width < minimumWidth ||
            draftCrop.height < minimumHeight
        ) {
            setClientError(
                `Keep at least ${minimumWidth} × ${minimumHeight} source pixels inside the crop.`,
            );

            return;
        }

        setCrop(draftCrop);
        setClientError(null);
        setEditorOpen(false);
    }

    function clearSelection(): void {
        setFile(null);
        setImageSize(null);
        setCrop(null);
        setDraftCrop(null);
        setClientError(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    const previewUrl = sourceUrl ?? existingUrl;
    const error = clientError ?? imageError ?? cropError;

    return (
        <div className="grid gap-3">
            <div>
                <p className="text-sm font-black">{label}</p>
                <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                    {description}
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-[9rem_1fr] sm:items-stretch">
                <div
                    className={`relative overflow-hidden rounded-2xl bg-primary/10 ring-1 ring-primary/10 ${aspect === 1 ? 'aspect-square' : 'aspect-3/4'}`}
                >
                    {previewUrl ? (
                        sourceUrl && imageSize && crop ? (
                            <CroppedPreview
                                src={sourceUrl}
                                alt={`${label} preview`}
                                size={imageSize}
                                crop={crop}
                            />
                        ) : (
                            <img
                                src={previewUrl}
                                alt={`${label} preview`}
                                className="size-full object-cover"
                            />
                        )
                    ) : (
                        <span className="grid size-full place-items-center text-primary">
                            <ImagePlus className="size-8" />
                        </span>
                    )}
                </div>

                <label
                    htmlFor={id}
                    onDragEnter={(event) => {
                        event.preventDefault();
                        setIsDragging(true);
                    }}
                    onDragOver={(event) => event.preventDefault()}
                    onDragLeave={() => setIsDragging(false)}
                    onDrop={onDrop}
                    className={`flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed p-5 text-center transition ${isDragging ? 'border-primary bg-primary/10' : 'border-slate-300 bg-white hover:border-primary/60 hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900'}`}
                >
                    <UploadCloud className="size-6 text-primary" />
                    <span className="mt-2 text-sm font-black">
                        Choose or drop an image
                    </span>
                    <span className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        JPG, PNG, or WebP · max 5 MB
                    </span>
                    <input
                        ref={inputRef}
                        id={id}
                        required={required}
                        type="file"
                        name={name}
                        accept="image/jpeg,image/png,image/webp"
                        onChange={onInputChange}
                        className="sr-only"
                    />
                </label>
            </div>

            {crop && (
                <>
                    <input
                        type="hidden"
                        name={`${cropName}[x]`}
                        value={crop.x}
                    />
                    <input
                        type="hidden"
                        name={`${cropName}[y]`}
                        value={crop.y}
                    />
                    <input
                        type="hidden"
                        name={`${cropName}[width]`}
                        value={crop.width}
                    />
                    <input
                        type="hidden"
                        name={`${cropName}[height]`}
                        value={crop.height}
                    />
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => setEditorOpen(true)}
                            className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black hover:border-primary/40 hover:text-primary dark:border-slate-700"
                        >
                            Adjust crop
                        </button>
                        <button
                            type="button"
                            onClick={clearSelection}
                            className="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-xs font-black text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <X className="size-3.5" /> Clear selection
                        </button>
                    </div>
                </>
            )}

            {error && <p className="text-sm text-red-600">{error}</p>}

            <Dialog open={editorOpen} onOpenChange={setEditorOpen}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Adjust {label.toLowerCase()}</DialogTitle>
                        <DialogDescription>
                            Position the image inside the fixed crop. The saved
                            image is processed on the server.
                        </DialogDescription>
                    </DialogHeader>
                    {sourceUrl && (
                        <div className="grid gap-4">
                            <div className="relative h-[min(58vh,34rem)] overflow-hidden rounded-2xl bg-slate-950">
                                <Cropper
                                    key={editorVersion}
                                    image={sourceUrl}
                                    crop={cropPosition}
                                    zoom={zoom}
                                    minZoom={1}
                                    maxZoom={maximumZoom}
                                    aspect={aspect}
                                    onCropChange={setCropPosition}
                                    onZoomChange={setZoom}
                                    onCropComplete={(
                                        _croppedArea: Area,
                                        croppedAreaPixels: Area,
                                    ) =>
                                        setDraftCrop(
                                            normalizeCrop(croppedAreaPixels),
                                        )
                                    }
                                />
                            </div>
                            <label className="grid gap-2 text-sm font-bold">
                                Zoom
                                <input
                                    type="range"
                                    min={1}
                                    max={maximumZoom}
                                    step={0.01}
                                    value={zoom}
                                    onChange={(event) =>
                                        setZoom(Number(event.target.value))
                                    }
                                    className="w-full accent-primary"
                                />
                            </label>
                        </div>
                    )}
                    <DialogFooter>
                        <button
                            type="button"
                            onClick={resetEditor}
                            className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black dark:border-slate-700"
                        >
                            <RotateCcw className="size-4" /> Reset crop
                        </button>
                        <button
                            type="button"
                            onClick={applyCrop}
                            className="rounded-xl bg-primary px-4 py-2.5 text-sm font-black text-primary-foreground"
                        >
                            Apply crop
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function CroppedPreview({
    alt,
    crop,
    size,
    src,
}: {
    alt: string;
    crop: ArtworkCrop;
    size: ImageSize;
    src: string;
}) {
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

function centeredCrop(size: ImageSize, aspect: number): ArtworkCrop {
    if (size.width / size.height > aspect) {
        const width = Math.round(size.height * aspect);

        return {
            x: Math.round((size.width - width) / 2),
            y: 0,
            width,
            height: size.height,
        };
    }

    const height = Math.round(size.width / aspect);

    return {
        x: 0,
        y: Math.round((size.height - height) / 2),
        width: size.width,
        height,
    };
}

function normalizeCrop(crop: Area): ArtworkCrop {
    return {
        x: Math.round(crop.x),
        y: Math.round(crop.y),
        width: Math.round(crop.width),
        height: Math.round(crop.height),
    };
}

function readImageSize(file: File): Promise<ImageSize> {
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
