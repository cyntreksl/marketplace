import { Head, usePage } from '@inertiajs/react';

export type SeoPayload = {
    title: string;
    description: string;
    canonicalUrl: string;
    robots: string;
    openGraph: {
        siteName: string;
        type: string;
        locale: string;
        image: string;
        imageWidth: number | null;
        imageHeight: number | null;
    };
    product: {
        price: string | null;
        currency: string;
        availability: string;
    } | null;
    jsonLd: Record<string, unknown>[];
};

function scriptSafeJson(value: Record<string, unknown>): string {
    return JSON.stringify(value)
        .replaceAll('<', '\\u003C')
        .replaceAll('>', '\\u003E')
        .replaceAll('&', '\\u0026')
        .replaceAll('\u2028', '\\u2028')
        .replaceAll('\u2029', '\\u2029');
}

export function SeoHead() {
    const { seo } = usePage().props;

    if (!seo) {
        return null;
    }

    return (
        <Head>
            <title head-key="title">{seo.title}</title>
            <meta
                head-key="description"
                name="description"
                content={seo.description}
            />
            <link
                head-key="canonical"
                rel="canonical"
                href={seo.canonicalUrl}
            />
            <meta head-key="robots" name="robots" content={seo.robots} />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={seo.openGraph.siteName}
            />
            <meta
                head-key="og:type"
                property="og:type"
                content={seo.openGraph.type}
            />
            <meta
                head-key="og:locale"
                property="og:locale"
                content={seo.openGraph.locale}
            />
            <meta
                head-key="og:url"
                property="og:url"
                content={seo.canonicalUrl}
            />
            <meta head-key="og:title" property="og:title" content={seo.title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={seo.description}
            />
            <meta
                head-key="og:image"
                property="og:image"
                content={seo.openGraph.image}
            />
            {seo.openGraph.imageWidth !== null && (
                <meta
                    head-key="og:image:width"
                    property="og:image:width"
                    content={String(seo.openGraph.imageWidth)}
                />
            )}
            {seo.openGraph.imageHeight !== null && (
                <meta
                    head-key="og:image:height"
                    property="og:image:height"
                    content={String(seo.openGraph.imageHeight)}
                />
            )}
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content="summary_large_image"
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={seo.title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={seo.description}
            />
            <meta
                head-key="twitter:image"
                name="twitter:image"
                content={seo.openGraph.image}
            />
            {seo.product && (
                <>
                    <meta
                        head-key="product:price:amount"
                        property="product:price:amount"
                        content={seo.product.price ?? ''}
                    />
                    <meta
                        head-key="product:price:currency"
                        property="product:price:currency"
                        content={seo.product.currency}
                    />
                    <meta
                        head-key="product:availability"
                        property="product:availability"
                        content={seo.product.availability}
                    />
                </>
            )}
            {seo.jsonLd.map((graph, index) => (
                <script
                    key={`json-ld-${index}`}
                    head-key={`json-ld-${index}`}
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: scriptSafeJson(graph) }}
                />
            ))}
        </Head>
    );
}
