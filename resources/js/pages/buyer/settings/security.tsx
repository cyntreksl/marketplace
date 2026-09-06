import { BuyerSettingsLayout } from '@/components/buyer-settings-layout';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import Security from '@/pages/settings/security';

type Props = { passwordRules: string } & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function BuyerSecuritySettings(props: Props) {
    return (
        <BuyerSettingsLayout title="Password & security">
            <Security {...props} buyer />
        </BuyerSettingsLayout>
    );
}
