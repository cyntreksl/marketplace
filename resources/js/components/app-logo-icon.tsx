import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
            <path
                fill="currentColor"
                d="M15 54V10h19.5c11.08 0 18.5 6.76 18.5 17.14 0 10.3-7.42 17.13-18.5 17.13H26.34V54H15Zm11.34-32.86v12.01h7.14c4.96 0 8.18-2.21 8.18-6.01 0-3.82-3.22-6-8.18-6h-7.14Z"
            />
            <path
                fill="var(--prodeals-spark, #f6c65b)"
                d="M47.98 5.5 50.7 12.3l6.8 2.72-6.8 2.72-2.72 6.8-2.72-6.8-6.8-2.72 6.8-2.72 2.72-6.8Z"
            />
        </svg>
    );
}
