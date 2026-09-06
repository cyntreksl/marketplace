import { BuyerSettingsLayout } from '@/components/buyer-settings-layout';
import Profile from '@/pages/settings/profile';

type Props = { mustVerifyEmail: boolean; status?: string };

export default function BuyerProfileSettings(props: Props) {
    return (
        <BuyerSettingsLayout title="Profile settings">
            <Profile {...props} buyer />
        </BuyerSettingsLayout>
    );
}
