import {
    useCallback,
    useState,
    type FormEvent,
} from 'react';

import axios from 'axios';

import {
    customerApi,
    type CustomerRequestPayload,
} from '../../api';

import { isRequestExpiryValid } from '../../customerPresentation';

export type CustomerTaskClass =
    | 'PICKUP_DELIVERY'
    | 'NON_DELIVERY';

type UseCreateCustomerRequestOptions = {
    runnerSlug: string;
    hasInvalidRunnerSlug: boolean;
    onCreated?: () => void;
};

function getDefaultExpiry(): string {
    const safetyBuffer = 10 * 60 * 1000;

    const date = new Date(
        Date.now() + 24 * 60 * 60 * 1000 - safetyBuffer,
    );

    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset)
        .toISOString()
        .slice(0, 16);
}

export function useCreateCustomerRequest({
    runnerSlug,
    hasInvalidRunnerSlug,
    onCreated,
}: UseCreateCustomerRequestOptions) {
    const [taskClass, setTaskClass] =
        useState<CustomerTaskClass>('PICKUP_DELIVERY');

    const [description, setDescription] = useState('');

    const [pickupAddress, setPickupAddress] = useState('');
    const [pickupContact, setPickupContact] = useState('');
    const [pickupPhone, setPickupPhone] = useState('');

    const [deliveryAddress, setDeliveryAddress] = useState('');
    const [deliveryContact, setDeliveryContact] = useState('');
    const [deliveryPhone, setDeliveryPhone] = useState('');

    const [taskAddress, setTaskAddress] = useState('');
    const [taskContact, setTaskContact] = useState('');
    const [taskPhone, setTaskPhone] = useState('');

    const [expiresAt, setExpiresAt] = useState(getDefaultExpiry());

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const clearMessages = useCallback(() => {
        setError('');
        setSuccess('');
    }, []);

    const resetForm = useCallback(() => {
        setTaskClass('PICKUP_DELIVERY');
        setDescription('');

        setPickupAddress('');
        setPickupContact('');
        setPickupPhone('');

        setDeliveryAddress('');
        setDeliveryContact('');
        setDeliveryPhone('');

        setTaskAddress('');
        setTaskContact('');
        setTaskPhone('');

        setExpiresAt(getDefaultExpiry());
    }, []);

    const submit = useCallback(
        async (
            event: FormEvent<HTMLFormElement>,
        ): Promise<void> => {
            event.preventDefault();

            if (loading) {
                return;
            }

            setError('');
            setSuccess('');

            if (hasInvalidRunnerSlug) {
                setError(
                    'Pautan profil Runner tidak sah. Sila buka semula pautan yang diberikan oleh Runner.',
                );
                return;
            }

            if (!isRequestExpiryValid(expiresAt)) {
                setError(
                    'Tarikh tamat mesti selepas masa sekarang dan tidak melebihi 24 jam.',
                );
                return;
            }

            const payload: CustomerRequestPayload = {
                source_type: runnerSlug
                    ? 'runner_profile_link'
                    : 'marketplace',

                ...(runnerSlug
                    ? { target_runner_slug: runnerSlug }
                    : {}),

                task_class: taskClass,
                description: description.trim(),
                expires_at: new Date(expiresAt).toISOString(),

                ...(taskClass === 'PICKUP_DELIVERY'
                    ? {
                        pickup_location_snapshot: {
                            address: pickupAddress.trim(),
                            contact_name:
                                pickupContact.trim() || null,
                            phone: pickupPhone.trim() || null,
                        },

                        delivery_location_snapshot: {
                            address: deliveryAddress.trim(),
                            contact_name:
                                deliveryContact.trim() || null,
                            phone: deliveryPhone.trim() || null,
                        },
                    }
                    : {
                        task_location_snapshot: {
                            address: taskAddress.trim(),
                            contact_name:
                                taskContact.trim() || null,
                            phone: taskPhone.trim() || null,
                        },
                    }),
            };

            setLoading(true);

            try {
                await customerApi.createRequest(payload);

                setSuccess(
                    'Permintaan tugasan berjaya dihantar.',
                );

                resetForm();
                onCreated?.();
            } catch (requestError) {
                if (axios.isAxiosError(requestError)) {
                    const responseErrors =
                        requestError.response?.data?.errors;

                    const firstValidationError = responseErrors
                        ? Object.values(responseErrors).flat()[0]
                        : null;

                    setError(
                        typeof firstValidationError === 'string'
                            ? firstValidationError
                            : requestError.response?.data?.message ??
                                'Tidak berjaya menghantar permintaan.',
                    );
                } else {
                    setError('Ralat berlaku. Sila cuba semula.');
                }
            } finally {
                setLoading(false);
            }
        },
        [
            loading,
            hasInvalidRunnerSlug,
            expiresAt,
            runnerSlug,
            taskClass,
            description,
            pickupAddress,
            pickupContact,
            pickupPhone,
            deliveryAddress,
            deliveryContact,
            deliveryPhone,
            taskAddress,
            taskContact,
            taskPhone,
            resetForm,
            onCreated,
        ],
    );

    return {
        taskClass,
        setTaskClass,
        description,
        setDescription,

        pickupAddress,
        setPickupAddress,
        pickupContact,
        setPickupContact,
        pickupPhone,
        setPickupPhone,

        deliveryAddress,
        setDeliveryAddress,
        deliveryContact,
        setDeliveryContact,
        deliveryPhone,
        setDeliveryPhone,

        taskAddress,
        setTaskAddress,
        taskContact,
        setTaskContact,
        taskPhone,
        setTaskPhone,

        expiresAt,
        setExpiresAt,

        loading,
        error,
        success,

        submit,
        resetForm,
        clearMessages,
    };
}
