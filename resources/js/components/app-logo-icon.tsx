import type { ImgHTMLAttributes } from 'react';

type AppLogoIconProps = Omit<
    ImgHTMLAttributes<HTMLImageElement>,
    'alt' | 'src'
>;

export default function AppLogoIcon(props: AppLogoIconProps) {
    return <img {...props} alt="" src="/prodeals-icon-inverse.svg" />;
}
