import type { ReactNode } from 'react';
import { SeoHead } from '@/components/seo-head';

export default function SeoLayout({ children }: { children: ReactNode }) {
    return (
        <>
            <SeoHead />
            {children}
        </>
    );
}
