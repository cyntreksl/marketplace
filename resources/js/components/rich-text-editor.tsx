import { Editor } from '@tinymce/tinymce-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const allowedTags = new Set([
    'a',
    'blockquote',
    'br',
    'caption',
    'code',
    'col',
    'colgroup',
    'em',
    'h2',
    'h3',
    'hr',
    'li',
    'ol',
    'p',
    'pre',
    's',
    'span',
    'strong',
    'sub',
    'sup',
    'table',
    'tbody',
    'td',
    'tfoot',
    'th',
    'thead',
    'tr',
    'u',
    'ul',
]);

const tagAliases: Record<string, string> = {
    b: 'strong',
    div: 'p',
    i: 'em',
    strike: 's',
};

const voidTags = new Set(['br', 'col', 'hr']);

function escapeAttribute(value: string): string {
    return value
        .replace(/&(?!(?:amp|quot|lt|gt|#\d+|#x[\da-f]+);)/gi, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function attributeValue(attributes: string, name: string): string | null {
    const match = attributes.match(
        new RegExp(
            `(?:^|\\s)${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s"'=<>\u0060]+))`,
            'i',
        ),
    );

    return match ? (match[1] ?? match[2] ?? match[3] ?? '') : null;
}

function sanitizedStyle(tag: string, attributes: string): string {
    if (!['h2', 'h3', 'p', 'span', 'td', 'th'].includes(tag)) {
        return '';
    }

    const style = attributeValue(attributes, 'style');

    if (!style) {
        return '';
    }

    const declarations = style
        .split(';')
        .map((declaration) =>
            declaration.split(':', 2).map((part) => part.trim()),
        )
        .flatMap(([property, value]) => {
            const normalizedProperty = property?.toLocaleLowerCase();
            const normalizedValue = value?.toLocaleLowerCase();

            if (
                normalizedProperty === 'font-weight' &&
                normalizedValue &&
                ['bold', 'bolder', '600', '700', '800', '900'].includes(
                    normalizedValue,
                )
            ) {
                return ['font-weight: 700'];
            }

            if (
                normalizedProperty === 'font-style' &&
                normalizedValue === 'italic'
            ) {
                return ['font-style: italic'];
            }

            if (
                normalizedProperty === 'text-align' &&
                normalizedValue &&
                ['left', 'center', 'right', 'justify'].includes(normalizedValue)
            ) {
                return [`text-align: ${normalizedValue}`];
            }

            if (normalizedProperty === 'text-decoration' && normalizedValue) {
                const decorations = ['underline', 'line-through'].filter(
                    (decoration) => normalizedValue.includes(decoration),
                );

                return decorations.length > 0
                    ? [`text-decoration: ${decorations.join(' ')}`]
                    : [];
            }

            return [];
        });

    return declarations.length > 0 ? ` style="${declarations.join('; ')}"` : '';
}

function sanitizedAttributes(tag: string, attributes: string): string {
    const style = sanitizedStyle(tag, attributes);

    if (tag === 'a') {
        const href = attributeValue(attributes, 'href')?.trim();
        const safeHref =
            href && /^(?:https?:\/\/|mailto:|tel:|\/|#)/i.test(href)
                ? ` href="${escapeAttribute(href)}"`
                : '';
        const opensNewTab =
            attributeValue(attributes, 'target')?.toLocaleLowerCase() ===
            '_blank';

        return `${safeHref}${opensNewTab ? ' target="_blank" rel="noopener noreferrer"' : ''}`;
    }

    if (tag === 'td' || tag === 'th') {
        const spans = ['colspan', 'rowspan']
            .map((name) => {
                const value = attributeValue(attributes, name);
                const span = value === null ? 0 : Number(value);

                return Number.isInteger(span) && span > 0 && span <= 100
                    ? ` ${name}="${span}"`
                    : '';
            })
            .join('');
        const scope = attributeValue(attributes, 'scope')
            ?.trim()
            .toLocaleLowerCase();
        const safeScope =
            tag === 'th' &&
            scope &&
            ['col', 'colgroup', 'row', 'rowgroup'].includes(scope)
                ? ` scope="${scope}"`
                : '';

        return `${spans}${safeScope}${style}`;
    }

    if (tag === 'col') {
        const value = attributeValue(attributes, 'span');
        const span = value === null ? 0 : Number(value);

        return Number.isInteger(span) && span > 0 && span <= 100
            ? ` span="${span}"`
            : '';
    }

    return style;
}

export function sanitizeRichText(value: string): string {
    return value
        .replace(/<\s*(script|style)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(
            /<\s*(\/?)\s*([a-z0-9]+)((?:\s[^<>]*?)?)\s*\/?\s*>/gi,
            (
                _match,
                closing: string,
                originalTag: string,
                attributes: string,
            ) => {
                const tag =
                    tagAliases[originalTag.toLocaleLowerCase()] ??
                    originalTag.toLocaleLowerCase();

                if (!allowedTags.has(tag)) {
                    return '';
                }

                if (voidTags.has(tag)) {
                    return `<${tag}${sanitizedAttributes(tag, attributes)}>`;
                }

                return closing === '/'
                    ? `</${tag}>`
                    : `<${tag}${sanitizedAttributes(tag, attributes)}>`;
            },
        );
}

export function richTextPlainText(value: string): string {
    return sanitizeRichText(value)
        .replace(/<br>/gi, '\n')
        .replace(
            /<\/(?:blockquote|caption|h2|h3|li|ol|p|pre|table|tbody|td|tfoot|th|thead|tr|ul)>/gi,
            '\n',
        )
        .replace(/<[^>]+>/g, '')
        .replace(/&(?:nbsp|#160|#x0*a0);/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function RichTextEditor({
    error,
    id,
    onBlur,
    onChange,
    placeholder,
    value,
}: {
    error?: string;
    id: string;
    onBlur?: () => void;
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
                onBlur={onBlur}
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
                    plugins: [
                        'autoresize',
                        'code',
                        'link',
                        'lists',
                        'table',
                        'wordcount',
                    ],
                    toolbar:
                        'undo redo | blocks | bold italic underline strikethrough | bullist numlist blockquote | link table | removeformat code',
                    block_formats:
                        'Paragraph=p; Heading 2=h2; Heading 3=h3; Quote=blockquote',
                    valid_elements:
                        'p[style],br,strong/b,em/i,u,s/strike,span[style],h2[style],h3[style],blockquote,ul,ol,li,a[href|target|rel],code,pre,hr,table,caption,colgroup,col[span],thead,tbody,tfoot,tr,th[colspan|rowspan|scope|style],td[colspan|rowspan|style]',
                    valid_styles: {
                        h2: 'font-weight,font-style,text-decoration,text-align',
                        h3: 'font-weight,font-style,text-decoration,text-align',
                        p: 'font-weight,font-style,text-decoration,text-align',
                        span: 'font-weight,font-style,text-decoration',
                        td: 'font-weight,font-style,text-decoration,text-align',
                        th: 'font-weight,font-style,text-decoration,text-align',
                    },
                    paste_as_text: false,
                    paste_data_images: false,
                    paste_webkit_styles:
                        'font-weight font-style text-decoration text-align',
                    table_default_attributes: {},
                    table_default_styles: {},
                    skin: isDark ? 'oxide-dark' : 'oxide',
                    content_css: isDark ? 'dark' : 'default',
                    content_style:
                        'body { font-family: Instrument Sans, ui-sans-serif, system-ui, sans-serif; font-size: 14px; line-height: 1.75; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; vertical-align: top; } th { background: #f1f5f9; font-weight: 700; }',
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
                'max-w-full space-y-3 overflow-x-auto leading-7 whitespace-pre-wrap [&_a]:font-semibold [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-amber-400 [&_blockquote]:pl-4 [&_h2]:text-2xl [&_h2]:font-black [&_h3]:text-xl [&_h3]:font-bold [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-6 [&_table]:w-full [&_table]:min-w-[32rem] [&_table]:border-collapse [&_td]:border [&_td]:border-slate-200 [&_td]:p-3 [&_td]:align-top dark:[&_td]:border-slate-700 [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:p-3 [&_th]:text-left [&_th]:font-bold dark:[&_th]:border-slate-700 dark:[&_th]:bg-slate-800 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: sanitizeRichText(value) }}
        />
    );
}
