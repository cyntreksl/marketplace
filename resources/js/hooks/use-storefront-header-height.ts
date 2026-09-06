import { useEffect, useState } from 'react';

export function useStorefrontHeaderHeight() {
    const [height, setHeight] = useState(80);
    useEffect(() => {
        const header = document.querySelector('[data-storefront-header]');

        if (!header) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setHeight(entry.target.getBoundingClientRect().height),
        );
        observer.observe(header);

        return () => observer.disconnect();
    }, []);

    return height;
}
