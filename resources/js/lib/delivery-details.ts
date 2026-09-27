export const deliveryDetailsStorageKey = 'prodeals.delivery-details.seen.v1';

type DeliveryDetailsStorage = Pick<Storage, 'getItem' | 'setItem'>;

export function claimDeliveryDetailsModal(
    storage: DeliveryDetailsStorage,
): boolean {
    try {
        if (storage.getItem(deliveryDetailsStorageKey) === '1') {
            return false;
        }

        storage.setItem(deliveryDetailsStorageKey, '1');

        return true;
    } catch {
        return true;
    }
}
