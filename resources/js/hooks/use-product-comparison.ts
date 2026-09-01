import { useEffect, useState } from 'react';

const comparisonKey = 'prodeals.comparisonListingIds';
const comparisonEvent = 'prodeals:comparison-change';

function readIds(): number[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const value = JSON.parse(
            window.localStorage.getItem(comparisonKey) ?? '[]',
        );

        return Array.isArray(value)
            ? value
                  .filter((id): id is number => Number.isInteger(id) && id > 0)
                  .slice(0, 4)
            : [];
    } catch {
        return [];
    }
}

export function useProductComparison() {
    const [ids, setIds] = useState<number[]>(readIds);

    useEffect(() => {
        const refresh = () => setIds(readIds());
        window.addEventListener(comparisonEvent, refresh);
        window.addEventListener('storage', refresh);

        return () => {
            window.removeEventListener(comparisonEvent, refresh);
            window.removeEventListener('storage', refresh);
        };
    }, []);

    const write = (next: number[]) => {
        window.localStorage.setItem(comparisonKey, JSON.stringify(next));
        setIds(next);
        window.dispatchEvent(new Event(comparisonEvent));
    };

    return {
        ids,
        contains: (id: number) => ids.includes(id),
        toggle(id: number): 'added' | 'removed' | 'limit' {
            if (ids.includes(id)) {
                write(ids.filter((item) => item !== id));

                return 'removed';
            }

            if (ids.length >= 4) {
                return 'limit';
            }

            write([...ids, id]);

            return 'added';
        },
        remove(id: number) {
            write(ids.filter((item) => item !== id));
        },
        clear() {
            write([]);
        },
    };
}
