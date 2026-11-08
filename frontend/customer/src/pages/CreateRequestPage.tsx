import React from 'react';

export type CustomerTaskClass =
    | 'PICKUP_DELIVERY'
    | 'NON_DELIVERY';

type CreateRequestPageProps = {
    taskClass: CustomerTaskClass;
    description: string;

    pickupAddress: string;
    pickupContact: string;
    pickupPhone: string;

    deliveryAddress: string;
    deliveryContact: string;
    deliveryPhone: string;

    taskAddress: string;
    taskContact: string;
    taskPhone: string;

    expiresAt: string;

    requestLoading: boolean;
    requestSuccess: string;
    error: string | null;

    runnerSlug?: string | null;
    hasInvalidRunnerSlug: boolean;

    onTaskClassChange: (value: CustomerTaskClass) => void;
    onDescriptionChange: (value: string) => void;

    onPickupAddressChange: (value: string) => void;
    onPickupContactChange: (value: string) => void;
    onPickupPhoneChange: (value: string) => void;

    onDeliveryAddressChange: (value: string) => void;
    onDeliveryContactChange: (value: string) => void;
    onDeliveryPhoneChange: (value: string) => void;

    onTaskAddressChange: (value: string) => void;
    onTaskContactChange: (value: string) => void;
    onTaskPhoneChange: (value: string) => void;

    onExpiresAtChange: (value: string) => void;
    onSubmit: (event: React.FormEvent<HTMLFormElement>) => void;
};

type LocationFieldsProps = {
    title: string;
    address: string;
    contact: string;
    phone: string;
    disabled: boolean;
    onAddressChange: (value: string) => void;
    onContactChange: (value: string) => void;
    onPhoneChange: (value: string) => void;
};

function LocationFields({
    title,
    address,
    contact,
    phone,
    disabled,
    onAddressChange,
    onContactChange,
    onPhoneChange,
}: LocationFieldsProps) {
    return (
        <fieldset className="customer-request-location">
            <legend>{title}</legend>

            <div>
                <label>
                    Alamat / Lokasi
                    <textarea
                        value={address}
                        onChange={(event) =>
                            onAddressChange(event.target.value)
                        }
                        required
                        rows={3}
                        maxLength={2000}
                        disabled={disabled}
                    />
                </label>
            </div>

            <div>
                <label>
                    Nama Orang Dihubungi (Pilihan)
                    <input
                        type="text"
                        value={contact}
                        onChange={(event) =>
                            onContactChange(event.target.value)
                        }
                        maxLength={150}
                        disabled={disabled}
                    />
                </label>
            </div>

            <div>
                <label>
                    Nombor Telefon (Pilihan)
                    <input
                        type="tel"
                        value={phone}
                        onChange={(event) =>
                            onPhoneChange(event.target.value)
                        }
                        maxLength={30}
                        disabled={disabled}
                    />
                </label>
            </div>
        </fieldset>
    );
}

export default function CreateRequestPage({
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

    expiresAt,

    requestLoading,
    requestSuccess,
    error,

    runnerSlug,
    hasInvalidRunnerSlug,

    onTaskClassChange,
    onDescriptionChange,

    onPickupAddressChange,
    onPickupContactChange,
    onPickupPhoneChange,

    onDeliveryAddressChange,
    onDeliveryContactChange,
    onDeliveryPhoneChange,

    onTaskAddressChange,
    onTaskContactChange,
    onTaskPhoneChange,

    onExpiresAtChange,
    onSubmit,
}: CreateRequestPageProps) {
    const minimumExpiry = new Date(
        Date.now() + 60000 -
        new Date().getTimezoneOffset() * 60000,
    )
        .toISOString()
        .slice(0, 16);

    const maximumExpiry = new Date(
        Date.now() +
        24 * 60 * 60 * 1000 -
        10 * 60 * 1000 -
        new Date().getTimezoneOffset() * 60000,
    )
        .toISOString()
        .slice(0, 16);

    return (
        <div className="sr-create-request-page">
            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Cipta Open Request</h2>
                        <p>
                            Nyatakan tugasan yang kau perlukan.
                            Runner yang layak boleh menghantar sebut harga.
                        </p>
                    </div>
                </div>

                {runnerSlug && !hasInvalidRunnerSlug && (
                    <p>
                        Permintaan ini akan dihantar khusus kepada Runner
                        daripada profil yang kau pilih.
                    </p>
                )}

                {requestSuccess && (
                    <p
                        className="customer-request-success"
                        role="status"
                    >
                        {requestSuccess}
                    </p>
                )}

                <form onSubmit={onSubmit}>
                    <div>
                        <label htmlFor="request-task-class">
                            Jenis Tugasan
                        </label>

                        <select
                            id="request-task-class"
                            value={taskClass}
                            onChange={(event) =>
                                onTaskClassChange(
                                    event.target.value as CustomerTaskClass,
                                )
                            }
                            disabled={requestLoading}
                        >
                            <option value="PICKUP_DELIVERY">
                                Pickup &amp; Delivery
                            </option>

                            <option value="NON_DELIVERY">
                                Non-Delivery
                            </option>
                        </select>
                    </div>

                    <div>
                        <label htmlFor="request-description">
                            Penerangan Tugasan
                        </label>

                        <textarea
                            id="request-description"
                            value={description}
                            onChange={(event) =>
                                onDescriptionChange(event.target.value)
                            }
                            required
                            maxLength={10000}
                            rows={5}
                            disabled={requestLoading}
                            placeholder="Terangkan tugasan yang kau perlukan..."
                        />
                    </div>

                    {taskClass === 'PICKUP_DELIVERY' && (
                        <>
                            <LocationFields
                                title="Lokasi Pickup"
                                address={pickupAddress}
                                contact={pickupContact}
                                phone={pickupPhone}
                                disabled={requestLoading}
                                onAddressChange={onPickupAddressChange}
                                onContactChange={onPickupContactChange}
                                onPhoneChange={onPickupPhoneChange}
                            />

                            <LocationFields
                                title="Lokasi Delivery"
                                address={deliveryAddress}
                                contact={deliveryContact}
                                phone={deliveryPhone}
                                disabled={requestLoading}
                                onAddressChange={onDeliveryAddressChange}
                                onContactChange={onDeliveryContactChange}
                                onPhoneChange={onDeliveryPhoneChange}
                            />
                        </>
                    )}

                    {taskClass === 'NON_DELIVERY' && (
                        <LocationFields
                            title="Lokasi Tugasan"
                            address={taskAddress}
                            contact={taskContact}
                            phone={taskPhone}
                            disabled={requestLoading}
                            onAddressChange={onTaskAddressChange}
                            onContactChange={onTaskContactChange}
                            onPhoneChange={onTaskPhoneChange}
                        />
                    )}

                    <div>
                        <label htmlFor="request-expiry">
                            Tarikh dan Masa Tamat Permintaan
                        </label>

                        <input
                            id="request-expiry"
                            type="datetime-local"
                            value={expiresAt}
                            onChange={(event) =>
                                onExpiresAtChange(event.target.value)
                            }
                            min={minimumExpiry}
                            max={maximumExpiry}
                            required
                            disabled={requestLoading}
                        />

                        <small>
                            Permintaan mesti tamat dalam tempoh maksimum
                            24 jam.
                        </small>
                    </div>

                    {error && (
                        <p role="alert">
                            {error}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={requestLoading}
                    >
                        {requestLoading
                            ? 'Menghantar permintaan...'
                            : 'Hantar Open Request'}
                    </button>
                </form>
            </section>
        </div>
    );
}
