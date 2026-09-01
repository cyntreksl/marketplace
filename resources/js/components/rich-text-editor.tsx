import { Editor } from '@tinymce/tinymce-react';
import type { ReactNode } from 'react';
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
    const isDark =
        typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark');
    const apiKey = import.meta.env.VITE_TINYMCE_API_KEY ?? 'no-api-key';

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-white transition focus-within:border-primary focus-within:ring-4 focus-within:ring-primary/10 dark:bg-slate-950',
                error
                    ? 'border-red-500 dark:border-red-500'
                    : 'border-slate-300 dark:border-slate-700',
            )}
        >
            <Editor
                id={id}
                apiKey={apiKey}
                cloudChannel="8"
                value={value}
                rollback={false}
                onEditorChange={(content) => {
                    const sanitizedValue = sanitizeRichText(content);
                    onChange(
                        richTextPlainText(sanitizedValue) === ''
                            ? ''
                            : sanitizedValue,
                    );
                }}
                init={{
                    height: 280,
                    min_height: 240,
                    max_height: 520,
                    menubar: false,
                    branding: false,
                    promotion: false,
                    resize: true,
                    statusbar: true,
                    elementpath: false,
                    placeholder,
                    plugins: ['autoresize', 'code', 'lists', 'wordcount'],
                    toolbar:
                        'undo redo | blocks | bold italic underline | bullist numlist blockquote | removeformat code',
                    block_formats:
                        'Paragraph=p; Heading 2=h2; Heading 3=h3; Quote=blockquote',
                    valid_elements:
                        'p,br,strong/b,em/i,u,h2,h3,blockquote,ul,ol,li',
                    skin: isDark ? 'oxide-dark' : 'oxide',
                    content_css: isDark ? 'dark' : 'default',
                    content_style:
                        'body { font-family: Instrument Sans, ui-sans-serif, system-ui, sans-serif; font-size: 14px; line-height: 1.75; }',
                }}
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
