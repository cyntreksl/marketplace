import {
    Bold,
    Heading2,
    Italic,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Underline,
    Undo2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ClipboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

const allowedTags = new Set([
    'blockquote',
    'br',
    'em',
    'h2',
    'h3',
    'li',
    'ol',
    'p',
    'strong',
    'u',
    'ul',
]);

const tagAliases: Record<string, string> = {
    b: 'strong',
    div: 'p',
    i: 'em',
};

const toolbarTools: {
    label: string;
    icon: LucideIcon;
    command: string;
    value?: string;
}[] = [
    { label: 'Bold', icon: Bold, command: 'bold' },
    { label: 'Italic', icon: Italic, command: 'italic' },
    { label: 'Underline', icon: Underline, command: 'underline' },
    {
        label: 'Heading',
        icon: Heading2,
        command: 'formatBlock',
        value: 'h2',
    },
    {
        label: 'Quote',
        icon: Quote,
        command: 'formatBlock',
        value: 'blockquote',
    },
    { label: 'Bulleted list', icon: List, command: 'insertUnorderedList' },
    {
        label: 'Numbered list',
        icon: ListOrdered,
        command: 'insertOrderedList',
    },
    { label: 'Undo', icon: Undo2, command: 'undo' },
    { label: 'Redo', icon: Redo2, command: 'redo' },
];

export function sanitizeRichText(value: string): string {
    return value
        .replace(/<\s*(script|style)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(
            /<\s*(\/?)\s*([a-z0-9]+)(?:\s[^<>]*?)?\s*\/?\s*>/gi,
            (_match, closing: string, originalTag: string) => {
                const tag =
                    tagAliases[originalTag.toLocaleLowerCase()] ??
                    originalTag.toLocaleLowerCase();

                if (!allowedTags.has(tag)) {
                    return '';
                }

                if (tag === 'br') {
                    return '<br>';
                }

                return closing === '/' ? `</${tag}>` : `<${tag}>`;
            },
        );
}

export function richTextPlainText(value: string): string {
    return sanitizeRichText(value)
        .replace(/<br>/gi, '\n')
        .replace(/<\/(?:blockquote|h2|h3|li|ol|p|ul)>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&(?:nbsp|#160|#x0*a0);/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function RichTextEditor({
    error,
    id,
    onChange,
    placeholder,
    value,
}: {
    error?: string;
    id: string;
    onChange: (value: string) => void;
    placeholder: string;
    value: string;
}): ReactNode {
    const editorRef = useRef<HTMLDivElement>(null);
    const selectionRef = useRef<Range | null>(null);

    useEffect(() => {
        const editor = editorRef.current;
        const sanitizedValue = sanitizeRichText(value);

        if (editor && sanitizeRichText(editor.innerHTML) !== sanitizedValue) {
            editor.innerHTML = sanitizedValue;
        }
    }, [value]);

    function syncValue(): void {
        const editor = editorRef.current;

        if (editor) {
            const sanitizedValue = sanitizeRichText(editor.innerHTML);
            const nextValue =
                richTextPlainText(sanitizedValue) === '' ? '' : sanitizedValue;

            if (nextValue === '' && editor.innerHTML !== '') {
                editor.innerHTML = '';
            }

            onChange(nextValue);
        }
    }

    function rememberSelection(): void {
        const editor = editorRef.current;
        const selection = document.getSelection();

        if (
            editor &&
            selection?.rangeCount &&
            editor.contains(selection.getRangeAt(0).commonAncestorContainer)
        ) {
            selectionRef.current = selection.getRangeAt(0).cloneRange();
        }
    }

    function runCommand(command: string, commandValue?: string): void {
        const editor = editorRef.current;
        const selection = document.getSelection();

        editor?.focus();

        if (selection && selectionRef.current) {
            selection.removeAllRanges();
            selection.addRange(selectionRef.current);
        }

        document.execCommand(command, false, commandValue);
        syncValue();
        rememberSelection();
    }

    function pastePlainText(event: ClipboardEvent<HTMLDivElement>): void {
        event.preventDefault();
        document.execCommand(
            'insertText',
            false,
            event.clipboardData.getData('text/plain'),
        );
        syncValue();
    }

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-transparent transition focus-within:border-amber-500 focus-within:ring-4 focus-within:ring-amber-100 dark:focus-within:ring-amber-900/30',
                error
                    ? 'border-red-500 dark:border-red-500'
                    : 'border-stone-300 dark:border-stone-700',
            )}
        >
            <div
                role="toolbar"
                aria-label="Description formatting"
                className="flex flex-wrap gap-1 border-b border-stone-200 bg-stone-50 p-2 dark:border-stone-700 dark:bg-stone-950"
            >
                {toolbarTools.map((tool) => {
                    const Icon = tool.icon;

                    return (
                        <button
                            key={tool.label}
                            type="button"
                            title={tool.label}
                            aria-label={tool.label}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => runCommand(tool.command, tool.value)}
                            className="inline-flex size-9 items-center justify-center rounded-lg text-stone-600 transition hover:bg-white hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-amber-500 dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-white"
                        >
                            <Icon className="size-4" />
                        </button>
                    );
                })}
            </div>
            <div
                ref={editorRef}
                id={id}
                role="textbox"
                aria-label="Description"
                aria-multiline="true"
                aria-invalid={error ? true : undefined}
                data-placeholder={placeholder}
                contentEditable
                suppressContentEditableWarning
                onFocus={() =>
                    document.execCommand(
                        'defaultParagraphSeparator',
                        false,
                        'p',
                    )
                }
                onInput={syncValue}
                onKeyUp={rememberSelection}
                onMouseUp={rememberSelection}
                onBlur={rememberSelection}
                onPaste={pastePlainText}
                className="min-h-48 px-4 py-3 text-sm leading-7 whitespace-pre-wrap outline-none empty:before:pointer-events-none empty:before:text-stone-400 empty:before:content-[attr(data-placeholder)] [&_blockquote]:border-l-4 [&_blockquote]:border-amber-400 [&_blockquote]:pl-4 [&_h2]:text-xl [&_h2]:font-black [&_h3]:text-lg [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-6"
            />
        </div>
    );
}

export function RichTextContent({
    className,
    value,
}: {
    className?: string;
    value: string;
}): ReactNode {
    return (
        <div
            className={cn(
                'space-y-3 leading-7 whitespace-pre-wrap [&_blockquote]:border-l-4 [&_blockquote]:border-amber-400 [&_blockquote]:pl-4 [&_h2]:text-2xl [&_h2]:font-black [&_h3]:text-xl [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: sanitizeRichText(value) }}
        />
    );
}
