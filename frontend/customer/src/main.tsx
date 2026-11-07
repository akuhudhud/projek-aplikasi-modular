import { useEffect, useState, type FormEvent } from 'react';
import axios from 'axios';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import {
    customerApi,
    type CustomerAccount,
    type CustomerTaskItem,
    type CustomerRequestPayload,
    type CustomerOwnMarketplaceRequest,
    type RegistrationOtpRequestPayload,
} from './api';
import CustomerRequestQuotations from './CustomerRequestQuotations';
import '../styles/customer.css';

type Page = 'dashboard' | 'tasks' | 'profile' | 'request';
type TaskClass = 'PICKUP_DELIVERY' | 'NON_DELIVERY';

function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function taskClassLabel(taskClass: string): string {
    return taskClass === 'PICKUP_DELIVERY'
        ? 'Pickup & Delivery'
        : 'Non-Delivery';
}

function getDefaultExpiry(): string {
    const date = new Date(Date.now() + 24 * 60 * 60 * 1000);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset)
        .toISOString()
        .slice(0, 16);
}

function CustomerApp() {
    const [account, setAccount] = useState<CustomerAccount | null>(null);
    const [profileName, setProfileName] = useState('');
    const [profileSaving, setProfileSaving] = useState(false);
    const [profileMessage, setProfileMessage] = useState('');
    const [profileError, setProfileError] = useState('');

    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [checkingSession, setCheckingSession] = useState(true);

    const [authMode, setAuthMode] = useState<'login' | 'register'>('login');
    const [registrationStep, setRegistrationStep] = useState<1 | 2 | 3>(1);
    const [registrationChannel, setRegistrationChannel] = useState<'phone' | 'email'>('phone');
    const [registrationContact, setRegistrationContact] = useState('');
    const [registrationVerificationId, setRegistrationVerificationId] = useState('');
    const [registrationVerificationToken, setRegistrationVerificationToken] = useState('');
    const [registrationOtp, setRegistrationOtp] = useState('');
    const [registrationName, setRegistrationName] = useState('');
    const [registrationPassword, setRegistrationPassword] = useState('');
    const [registrationPasswordConfirmation, setRegistrationPasswordConfirmation] = useState('');

    const [page, setPage] = useState<Page>('dashboard');
    const [tasks, setTasks] = useState<CustomerTaskItem[]>([]);
    const [taskTotal, setTaskTotal] = useState(0);
    const [taskPage, setTaskPage] = useState(1);
    const [taskLastPage, setTaskLastPage] = useState(1);
    const [tasksLoading, setTasksLoading] = useState(false);
    const [taskRefreshKey, setTaskRefreshKey] = useState(0);

    const [selectedTask, setSelectedTask] = useState<CustomerTaskItem | null>(null);
    const [selectedTaskId, setSelectedTaskId] = useState<string | null>(null);
    const [taskDetailLoading, setTaskDetailLoading] = useState(false);
    const [taskDetailError, setTaskDetailError] = useState('');

    const [taskClass, setTaskClass] = useState<TaskClass>('PICKUP_DELIVERY');
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
    const [requestLoading, setRequestLoading] = useState(false);
    const [requestSuccess, setRequestSuccess] = useState('');

    const [ownRequests, setOwnRequests] = useState<CustomerOwnMarketplaceRequest[]>([]);
    const [ownRequestPage, setOwnRequestPage] = useState(1);
    const [ownRequestLastPage, setOwnRequestLastPage] = useState(1);
    const [ownRequestTotal, setOwnRequestTotal] = useState(0);
    const [ownRequestsLoading, setOwnRequestsLoading] = useState(false);
    const [ownRequestsError, setOwnRequestsError] = useState('');

    const [cancellingRequestId, setCancellingRequestId] = useState<string | null>(null);
    const [ownRequestActionMessage, setOwnRequestActionMessage] = useState('');
    const [ownRequestActionError, setOwnRequestActionError] = useState('');

    useEffect(() => {
        let mounted = true;

        customerApi.me()
            .then((currentAccount) => {
                if (mounted) setAccount(currentAccount);
            })
            .catch(() => {
                if (mounted) setAccount(null);
            })
            .finally(() => {
                if (mounted) setCheckingSession(false);
            });

        return () => {
            mounted = false;
        };
    }, []);

    useEffect(() => {
        if (account) {
            setProfileName(account.name);
        }
    }, [account]);

    useEffect(() => {
        if (!account) return;

        let mounted = true;
        setTasksLoading(true);

        customerApi.getTasks(taskPage)
            .then((result) => {
                if (!mounted) return;

                setTasks(result.tasks);
                setTaskTotal(result.pagination.total);
                setTaskLastPage(result.pagination.last_page);
            })
            .catch(() => {
                if (mounted) {
                    setError('Tidak berjaya memuatkan senarai tugasan.');
                }
            })
            .finally(() => {
                if (mounted) setTasksLoading(false);
            });

        return () => {
            mounted = false;
        };
    }, [account, taskPage, taskRefreshKey]);

    useEffect(() => {
        if (!selectedTaskId) return;

        let mounted = true;

        setSelectedTask(null);
        setTaskDetailError('');
        setTaskDetailLoading(true);

        customerApi.getTask(selectedTaskId)
            .then((result) => {
                if (!mounted) return;

                setSelectedTask({
                    task: result.task,
                    request: result.request,
                });
            })
            .catch(() => {
                if (mounted) {
                    setTaskDetailError(
                        'Tidak berjaya memuatkan butiran tugasan. Sila cuba semula.',
                    );
                }
            })
            .finally(() => {
                if (mounted) setTaskDetailLoading(false);
            });

        return () => {
            mounted = false;
        };
    }, [selectedTaskId]);

    useEffect(() => {
        if (!account || page !== 'request') return;

        let mounted = true;
        setOwnRequestsLoading(true);
        setOwnRequestsError('');

        customerApi.getRequests(ownRequestPage)
            .then((result) => {
                if (!mounted) return;

                setOwnRequests(result.requests);
                setOwnRequestTotal(result.pagination.total);
                setOwnRequestLastPage(result.pagination.last_page);
            })
            .catch(() => {
                if (mounted) {
                    setOwnRequestsError(
                        'Tidak berjaya memuatkan senarai Open Request.',
                    );
                }
            })
            .finally(() => {
                if (mounted) setOwnRequestsLoading(false);
            });

        return () => {
            mounted = false;
        };
    }, [account, page, ownRequestPage]);

    function closeTaskDetail() {
        setSelectedTaskId(null);
        setSelectedTask(null);
        setTaskDetailError('');
        setTaskDetailLoading(false);
    }

    async function handleLogin(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const value = identifier.trim();

            const currentAccount = await customerApi.login({
                ...(value.includes('@')
                    ? { email: value }
                    : { phone: value }),
                password,
            });

            setAccount(currentAccount);
            setPassword('');
            setPage('dashboard');
        } catch (err) {
            if (axios.isAxiosError(err)) {
                setError(
                    err.response?.data?.message ??
                    'Login gagal. Sila semak maklumat anda.',
                );
            } else {
                setError('Ralat berlaku. Sila cuba semula.');
            }
        } finally {
            setLoading(false);
        }
    }

    function resetRegistration() {
        setRegistrationStep(1);
        setRegistrationChannel('phone');
        setRegistrationContact('');
        setRegistrationVerificationId('');
        setRegistrationVerificationToken('');
        setRegistrationOtp('');
        setRegistrationName('');
        setRegistrationPassword('');
        setRegistrationPasswordConfirmation('');
        setError('');
    }

    async function handleRequestRegistrationOtp(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const payload: RegistrationOtpRequestPayload = {
                channel: registrationChannel,
                contact: registrationContact.trim(),
                purpose: 'REGISTRATION',
            };

            const result = await customerApi.requestRegistrationOtp(payload);

            setRegistrationVerificationId(result.verification_id);
            setRegistrationVerificationToken('');
            setRegistrationOtp('');
            setRegistrationStep(2);
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const responseErrors = err.response?.data?.errors;
                const firstValidationError = responseErrors
                    ? Object.values(responseErrors).flat()[0]
                    : null;

                setError(
                    typeof firstValidationError === 'string'
                        ? firstValidationError
                        : err.response?.data?.message ??
                            'Tidak berjaya menghantar OTP. Sila cuba semula.',
                );
            } else {
                setError('Ralat berlaku. Sila cuba semula.');
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleVerifyRegistrationOtp(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');

        if (!/^\d{6}$/.test(registrationOtp)) {
            setError('Masukkan kod OTP 6 digit.');
            return;
        }

        setLoading(true);

        try {
            const result = await customerApi.verifyRegistrationOtp({
                verification_id: registrationVerificationId,
                otp: registrationOtp,
            });

            setRegistrationVerificationToken(result.verification_token);
            setRegistrationStep(3);
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const responseErrors = err.response?.data?.errors;
                const firstValidationError = responseErrors
                    ? Object.values(responseErrors).flat()[0]
                    : null;

                setError(
                    typeof firstValidationError === 'string'
                        ? firstValidationError
                        : err.response?.data?.message ??
                            'OTP tidak sah atau telah tamat tempoh.',
                );
            } else {
                setError('Ralat berlaku. Sila cuba semula.');
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleCompleteRegistration(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setError('');

        if (!registrationVerificationToken) {
            setError('Pengesahan OTP diperlukan. Sila ulang proses pendaftaran.');
            setRegistrationStep(1);
            return;
        }

        if (
            registrationPassword.length < 8 ||
            registrationPassword.length > 12 ||
            !/[A-Z]/.test(registrationPassword) ||
            !/[a-z]/.test(registrationPassword) ||
            !/\d/.test(registrationPassword)
        ) {
            setError(
                'Kata laluan mesti 8–12 aksara dan mengandungi huruf besar, huruf kecil serta nombor.',
            );
            return;
        }

        if (registrationPassword !== registrationPasswordConfirmation) {
            setError('Pengesahan kata laluan tidak sepadan.');
            return;
        }

        setLoading(true);

        try {
            const currentAccount = await customerApi.completeRegistration({
                verification_token: registrationVerificationToken,
                name: registrationName.trim(),
                password: registrationPassword,
                password_confirmation: registrationPasswordConfirmation,
            });

            setAccount(currentAccount);
            setPassword('');
            setRegistrationVerificationToken('');
            setPage('dashboard');
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const responseErrors = err.response?.data?.errors;
                const firstValidationError = responseErrors
                    ? Object.values(responseErrors).flat()[0]
                    : null;

                setError(
                    typeof firstValidationError === 'string'
                        ? firstValidationError
                        : err.response?.data?.message ??
                            'Pendaftaran tidak berjaya. Sila cuba semula.',
                );
            } else {
                setError('Ralat berlaku. Sila cuba semula.');
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleUpdateProfile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!account || profileSaving) return;

        const name = profileName.trim();

        if (!name) {
            setProfileError('Nama tidak boleh kosong.');
            setProfileMessage('');
            return;
        }

        setProfileSaving(true);
        setProfileError('');
        setProfileMessage('');

        try {
            const updatedAccount = await customerApi.updateProfile({ name });

            setAccount(updatedAccount);
            setProfileName(updatedAccount.name);
            setProfileMessage('Profil berjaya dikemaskini.');
        } catch (requestError) {
            if (axios.isAxiosError(requestError)) {
                const validationMessage =
                    requestError.response?.data?.errors?.name?.[0];

                setProfileError(
                    validationMessage ??
                    requestError.response?.data?.message ??
                    'Tidak berjaya mengemaskini profil.',
                );
            } else {
                setProfileError('Tidak berjaya mengemaskini profil.');
            }
        } finally {
            setProfileSaving(false);
        }
    }

    async function handleLogout() {
        setError('');
        setLoading(true);

        try {
            await customerApi.logout();
            setAccount(null);
            setIdentifier('');
            setPassword('');
            setTasks([]);
            setOwnRequests([]);
            closeTaskDetail();
        } catch {
            setError('Tidak berjaya logout. Sila cuba semula.');
        } finally {
            setLoading(false);
        }
    }

    function openTask(item: CustomerTaskItem) {
        setSelectedTask(null);
        setTaskDetailError('');
        setSelectedTaskId(item.task.task_id);
    }

    function resetRequestForm() {
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
    }

    async function handleCreateRequest(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        setRequestSuccess('');
        setRequestLoading(true);

        try {
            const payload: CustomerRequestPayload = {
                task_class: taskClass,
                description: description.trim(),
                expires_at: new Date(expiresAt).toISOString(),
                ...(taskClass === 'PICKUP_DELIVERY'
                    ? {
                        pickup_location_snapshot: {
                            address: pickupAddress.trim(),
                            contact_name: pickupContact.trim() || null,
                            phone: pickupPhone.trim() || null,
                        },
                        delivery_location_snapshot: {
                            address: deliveryAddress.trim(),
                            contact_name: deliveryContact.trim() || null,
                            phone: deliveryPhone.trim() || null,
                        },
                    }
                    : {
                        task_location_snapshot: {
                            address: taskAddress.trim(),
                            contact_name: taskContact.trim() || null,
                            phone: taskPhone.trim() || null,
                        },
                    }),
            };

            await customerApi.createRequest(payload);

            setRequestSuccess('Permintaan tugasan berjaya dihantar.');
            setOwnRequestPage(1);
            resetRequestForm();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const responseErrors = err.response?.data?.errors;
                const firstValidationError = responseErrors
                    ? Object.values(responseErrors).flat()[0]
                    : null;

                setError(
                    typeof firstValidationError === 'string'
                        ? firstValidationError
                        : err.response?.data?.message ??
                            'Tidak berjaya menghantar permintaan.',
                );
            } else {
                setError('Ralat berlaku. Sila cuba semula.');
            }
        } finally {
            setRequestLoading(false);
        }
    }

    async function handleCancelRequest(requestId: string) {
        const confirmed = window.confirm(
            'Adakah anda pasti mahu membatalkan Open Request ini?',
        );

        if (!confirmed) return;

        setCancellingRequestId(requestId);
        setOwnRequestActionMessage('');
        setOwnRequestActionError('');

        try {
            const result = await customerApi.cancelRequest(requestId);

            setOwnRequests((current) =>
                current.map((request) =>
                    request.request_id === requestId
                        ? {
                            ...request,
                            status: result.status,
                            cancelled_at: result.cancelled_at,
                            updated_at: result.updated_at,
                        }
                        : request,
                ),
            );

            setOwnRequestActionMessage(
                'Open Request berjaya dibatalkan.',
            );
        } catch (err) {
            if (axios.isAxiosError(err)) {
                setOwnRequestActionError(
                    err.response?.data?.message ??
                        'Tidak berjaya membatalkan Open Request.',
                );
            } else {
                setOwnRequestActionError(
                    'Ralat berlaku. Sila cuba semula.',
                );
            }
        } finally {
            setCancellingRequestId(null);
        }
    }

    function handleQuotationAwarded(
        requestId: string,
        result: Awaited<ReturnType<typeof customerApi.awardQuotation>>,
    ) {
        setOwnRequests((current) =>
            current.map((request) =>
                request.request_id === requestId
                    ? {
                        ...request,
                        status: result.request.status,
                        awarded_at: result.request.awarded_at,
                        updated_at: result.request.updated_at,
                    }
                    : request,
            ),
        );

        setTaskRefreshKey((current) => current + 1);

        setOwnRequestActionError('');
        setOwnRequestActionMessage(
            `Runner berjaya dipilih. Core Task: ${result.task.task_id}`,
        );
    }

    function renderTaskList() {
        if (tasksLoading) {
            return <p>Memuatkan tugasan...</p>;
        }

        if (tasks.length === 0) {
            return (
                <section className="customer-empty">
                    <h3>Belum ada tugasan</h3>
                    <p>Tugasan yang anda buat akan dipaparkan di sini.</p>
                </section>
            );
        }

        return (
            <>
                <div className="customer-task-list">
                    {tasks.map((item) => (
                        <button
                            className="customer-task-card"
                            type="button"
                            key={item.task.task_id}
                            onClick={() => openTask(item)}
                        >
                            <span className="customer-task-card-top">
                                {taskClassLabel(item.request.task_class)}
                            </span>
                            <strong>{item.request.description}</strong>
                            <span>Status: {item.task.generic_status}</span>
                            <span>{formatDate(item.task.created_at)}</span>
                        </button>
                    ))}
                </div>

                <div className="customer-pagination">
                    <button
                        type="button"
                        disabled={taskPage <= 1 || tasksLoading}
                        onClick={() => setTaskPage((current) => current - 1)}
                    >
                        Sebelum
                    </button>

                    <span>
                        {taskPage} / {taskLastPage}
                    </span>

                    <button
                        type="button"
                        disabled={taskPage >= taskLastPage || tasksLoading}
                        onClick={() => setTaskPage((current) => current + 1)}
                    >
                        Seterusnya
                    </button>
                </div>
            </>
        );
    }

    function renderTaskDetail(item: CustomerTaskItem) {
        const { task, request } = item;

        return (
            <section className="customer-panel">
                <button
                    className="customer-back-button"
                    type="button"
                    onClick={closeTaskDetail}
                >
                    ← Kembali ke senarai
                </button>

                <h2>Butiran Tugasan</h2>
                <p className="customer-task-class">
                    {taskClassLabel(request.task_class)}
                </p>

                <h3>{request.description}</h3>

                <dl className="customer-detail-list">
                    <div>
                        <dt>ID Tugasan</dt>
                        <dd>{task.task_id}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{task.generic_status}</dd>
                    </div>
                    <div>
                        <dt>Status Permintaan</dt>
                        <dd>{request.status}</dd>
                    </div>
                    <div>
                        <dt>Dicipta</dt>
                        <dd>{formatDate(task.created_at)}</dd>
                    </div>
                    <div>
                        <dt>Runner ditugaskan</dt>
                        <dd>{formatDate(task.assigned_at)}</dd>
                    </div>
                    <div>
                        <dt>Mula</dt>
                        <dd>{formatDate(task.started_at)}</dd>
                    </div>
                    <div>
                        <dt>Selesai</dt>
                        <dd>{formatDate(task.completed_at)}</dd>
                    </div>
                    <div>
                        <dt>Tarikh luput permintaan</dt>
                        <dd>{formatDate(request.expires_at)}</dd>
                    </div>
                </dl>

                {request.task_class === 'PICKUP_DELIVERY' && (
                    <div className="customer-location-grid">
                        <div>
                            <h4>Lokasi Pickup</h4>
                            <pre>
                                {JSON.stringify(
                                    request.pickup_location_snapshot ?? {},
                                    null,
                                    2,
                                )}
                            </pre>
                        </div>
                        <div>
                            <h4>Lokasi Delivery</h4>
                            <pre>
                                {JSON.stringify(
                                    request.delivery_location_snapshot ?? {},
                                    null,
                                    2,
                                )}
                            </pre>
                        </div>
                    </div>
                )}

                {request.task_class === 'NON_DELIVERY' && (
                    <div>
                        <h4>Lokasi Tugasan</h4>
                        <pre>
                            {JSON.stringify(
                                request.task_location_snapshot ?? {},
                                null,
                                2,
                            )}
                        </pre>
                    </div>
                )}
            </section>
        );
    }

    function renderDashboard() {
        return (
            <>
                <section className="customer-welcome">
                    <p>Dashboard Pelanggan</p>
                    <h2>Hai, {account?.name}</h2>
                    <span>Selamat datang ke Super Runner.</span>
                </section>

                <div className="customer-summary-grid">
                    <article className="customer-summary-card">
                        <span>Jumlah Tugasan</span>
                        <strong>{taskTotal}</strong>
                    </article>
                    <article className="customer-summary-card">
                        <span>Status Akaun</span>
                        <strong>{account?.status}</strong>
                    </article>
                </div>

                <section className="customer-panel">
                    <div className="customer-section-heading">
                        <h2>Tugasan Terkini</h2>
                        <button
                            type="button"
                            onClick={() => {
                                closeTaskDetail();
                                setPage('tasks');
                            }}
                        >
                            Lihat semua
                        </button>
                    </div>

                    {renderTaskList()}
                </section>
            </>
        );
    }

    function renderProfile() {
        if (!account) return null;

        return (
            <section className="customer-panel">
                <h2>Profil Saya</h2>

                <div className="customer-profile-avatar">
                    {account.name.charAt(0).toUpperCase()}
                </div>

                <form onSubmit={handleUpdateProfile}>
                    <div>
                        <label htmlFor="profile-name">Nama</label>
                        <input
                            id="profile-name"
                            type="text"
                            autoComplete="name"
                            value={profileName}
                            onChange={(event) => {
                                setProfileName(event.target.value);
                                setProfileError('');
                                setProfileMessage('');
                            }}
                            required
                            maxLength={255}
                            disabled={profileSaving}
                        />
                    </div>

                    <dl className="customer-detail-list">
                        <div>
                            <dt>Nombor Telefon</dt>
                            <dd>{account.phone || 'Belum ditetapkan'}</dd>
                        </div>
                        <div>
                            <dt>E-mel</dt>
                            <dd>{account.email || 'Belum ditetapkan'}</dd>
                        </div>
                        <div>
                            <dt>Peranan</dt>
                            <dd>{account.role}</dd>
                        </div>
                        <div>
                            <dt>Status Akaun</dt>
                            <dd>{account.status}</dd>
                        </div>
                    </dl>

                    {profileError && <p role="alert">{profileError}</p>}
                    {profileMessage && <p role="status">{profileMessage}</p>}

                    <button type="submit" disabled={profileSaving}>
                        {profileSaving ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </form>
            </section>
        );
    }

    function renderLocationFields(
        title: string,
        address: string,
        setAddress: (value: string) => void,
        contact: string,
        setContact: (value: string) => void,
        phone: string,
        setPhone: (value: string) => void,
    ) {
        return (
            <fieldset className="customer-request-location">
                <legend>{title}</legend>

                <div>
                    <label>Alamat / Lokasi</label>
                    <textarea
                        value={address}
                        onChange={(event) => setAddress(event.target.value)}
                        required
                        rows={3}
                        maxLength={2000}
                        disabled={requestLoading}
                    />
                </div>

                <div>
                    <label>Nama Orang Dihubungi (Pilihan)</label>
                    <input
                        type="text"
                        value={contact}
                        onChange={(event) => setContact(event.target.value)}
                        maxLength={150}
                        disabled={requestLoading}
                    />
                </div>

                <div>
                    <label>Nombor Telefon (Pilihan)</label>
                    <input
                        type="tel"
                        value={phone}
                        onChange={(event) => setPhone(event.target.value)}
                        maxLength={30}
                        disabled={requestLoading}
                    />
                </div>
            </fieldset>
        );
    }

    function renderOwnRequests() {
        return (
            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Open Request Saya</h2>
                        <p>Jumlah permintaan: {ownRequestTotal}</p>
                    </div>
                </div>

                {ownRequestActionMessage && (
                    <p
                        className="customer-request-success"
                        role="status"
                    >
                        {ownRequestActionMessage}
                    </p>
                )}

                {ownRequestActionError && (
                    <p role="alert">
                        {ownRequestActionError}
                    </p>
                )}

                {ownRequestsLoading ? (
                    <p>Memuatkan senarai permintaan...</p>
                ) : ownRequestsError ? (
                    <p role="alert">{ownRequestsError}</p>
                ) : ownRequests.length === 0 ? (
                    <section className="customer-empty">
                        <h3>Belum ada Open Request</h3>
                        <p>
                            Permintaan yang anda cipta akan dipaparkan di sini.
                        </p>
                    </section>
                ) : (
                    <>
                        <div className="customer-task-list">
                            {ownRequests.map((request) => (
                                <article
                                    className="customer-task-card"
                                    key={request.request_id}
                                >
                                    <span className="customer-task-card-top">
                                        {taskClassLabel(request.task_class)}
                                    </span>

                                    <strong>{request.description}</strong>

                                    <span>Status: {request.status}</span>

                                    <span>
                                        Tarikh luput: {formatDate(request.expires_at)}
                                    </span>

                                    <span>
                                        Dicipta: {formatDate(request.created_at)}
                                    </span>

                                    {request.cancelled_at && (
                                        <span>
                                            Dibatalkan: {formatDate(request.cancelled_at)}
                                        </span>
                                    )}

                                    {request.status === 'OPEN' && (
                                        <button
                                            type="button"
                                            disabled={cancellingRequestId !== null}
                                            onClick={() =>
                                                handleCancelRequest(request.request_id)
                                            }
                                        >
                                            {cancellingRequestId === request.request_id
                                                ? 'Membatalkan...'
                                                : 'Batalkan Permintaan'}
                                        </button>
                                    )}

                                    {(request.status === 'OPEN' ||
                                        request.status === 'AWARDED') && (
                                        <CustomerRequestQuotations
                                            requestId={request.request_id}
                                            requestStatus={request.status}
                                            onAwarded={handleQuotationAwarded}
                                        />
                                    )}
                                </article>
                            ))}
                        </div>

                        <div className="customer-pagination">
                            <button
                                type="button"
                                disabled={
                                    ownRequestPage <= 1 || ownRequestsLoading
                                }
                                onClick={() =>
                                    setOwnRequestPage((current) => current - 1)
                                }
                            >
                                Sebelum
                            </button>

                            <span>
                                {ownRequestPage} / {ownRequestLastPage}
                            </span>

                            <button
                                type="button"
                                disabled={
                                    ownRequestPage >= ownRequestLastPage ||
                                    ownRequestsLoading
                                }
                                onClick={() =>
                                    setOwnRequestPage((current) => current + 1)
                                }
                            >
                                Seterusnya
                            </button>
                        </div>
                    </>
                )}
            </section>
        );
    }

    function renderRequestForm() {
        return (
            <>
                <section className="customer-panel">
                    <h2>Cipta Open Request</h2>
                    <p>
                        Nyatakan tugasan yang anda perlukan. Runner boleh melihat
                        permintaan ini dan menghantar sebut harga.
                    </p>

                    {requestSuccess && (
                        <p className="customer-request-success" role="status">
                            {requestSuccess}
                        </p>
                    )}

                    <form onSubmit={handleCreateRequest}>
                        <div>
                            <label htmlFor="request-task-class">
                                Jenis Tugasan
                            </label>
                            <select
                                id="request-task-class"
                                value={taskClass}
                                onChange={(event) => {
                                    setTaskClass(event.target.value as TaskClass);
                                }}
                                disabled={requestLoading}
                            >
                                <option value="PICKUP_DELIVERY">
                                    Pickup & Delivery
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
                                onChange={(event) => setDescription(event.target.value)}
                                required
                                maxLength={10000}
                                rows={5}
                                disabled={requestLoading}
                                placeholder="Terangkan tugasan yang anda perlukan..."
                            />
                        </div>

                        {taskClass === 'PICKUP_DELIVERY' && (
                            <>
                                {renderLocationFields(
                                    'Lokasi Pickup',
                                    pickupAddress,
                                    setPickupAddress,
                                    pickupContact,
                                    setPickupContact,
                                    pickupPhone,
                                    setPickupPhone,
                                )}

                                {renderLocationFields(
                                    'Lokasi Delivery',
                                    deliveryAddress,
                                    setDeliveryAddress,
                                    deliveryContact,
                                    setDeliveryContact,
                                    deliveryPhone,
                                    setDeliveryPhone,
                                )}
                            </>
                        )}

                        {taskClass === 'NON_DELIVERY' && (
                            renderLocationFields(
                                'Lokasi Tugasan',
                                taskAddress,
                                setTaskAddress,
                                taskContact,
                                setTaskContact,
                                taskPhone,
                                setTaskPhone,
                            )
                        )}

                        <div>
                            <label htmlFor="request-expiry">
                                Tarikh dan Masa Tamat Permintaan
                            </label>
                            <input
                                id="request-expiry"
                                type="datetime-local"
                                value={expiresAt}
                                onChange={(event) => setExpiresAt(event.target.value)}
                                min={new Date(
                                    Date.now() + 60000 -
                                    new Date().getTimezoneOffset() * 60000,
                                ).toISOString().slice(0, 16)}
                                required
                                disabled={requestLoading}
                            />
                            <small>
                                Permintaan mesti tamat dalam tempoh maksimum 24 jam.
                            </small>
                        </div>

                        {error && <p role="alert">{error}</p>}

                        <button type="submit" disabled={requestLoading}>
                            {requestLoading
                                ? 'Menghantar permintaan...'
                                : 'Hantar Open Request'}
                        </button>
                    </form>
                </section>

                {renderOwnRequests()}
            </>
        );
    }

    if (checkingSession) {
        return (
            <main className="customer-loading">
                <p>Memeriksa sesi...</p>
            </main>
        );
    }

    if (!account) {
        return (
            <main className="customer-login">
                <h1>Super Runner</h1>

                {authMode === 'login' ? (
                    <>
                        <h2>Log Masuk Pelanggan</h2>

                        <form onSubmit={handleLogin}>
                            <div>
                                <label htmlFor="identifier">
                                    Nombor Telefon atau E-mel
                                </label>
                                <input
                                    id="identifier"
                                    type="text"
                                    autoComplete="username"
                                    value={identifier}
                                    onChange={(event) => setIdentifier(event.target.value)}
                                    required
                                    disabled={loading}
                                />
                            </div>

                            <div>
                                <label htmlFor="password">Kata Laluan</label>
                                <input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={password}
                                    onChange={(event) => setPassword(event.target.value)}
                                    required
                                    disabled={loading}
                                />
                            </div>

                            {error && <p role="alert">{error}</p>}

                            <button type="submit" disabled={loading}>
                                {loading ? 'Sedang log masuk...' : 'Log Masuk'}
                            </button>
                        </form>

                        <p>
                            Belum ada akaun?{' '}
                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => {
                                    resetRegistration();
                                    setAuthMode('register');
                                }}
                            >
                                Daftar Akaun
                            </button>
                        </p>
                    </>
                ) : (
                    <>
                        <h2>Pendaftaran Pelanggan</h2>
                        <p>Daftar akaun menggunakan nombor telefon atau e-mel.</p>

                        <div
                            className="customer-registration-steps"
                            aria-label="Langkah pendaftaran"
                        >
                            <span className={registrationStep >= 1 ? 'active' : ''}>
                                1. Hubungan
                            </span>
                            <span className={registrationStep >= 2 ? 'active' : ''}>
                                2. OTP
                            </span>
                            <span className={registrationStep >= 3 ? 'active' : ''}>
                                3. Akaun
                            </span>
                        </div>

                        {registrationStep === 1 && (
                            <form onSubmit={handleRequestRegistrationOtp}>
                                <div>
                                    <label htmlFor="registration-channel">
                                        Daftar menggunakan
                                    </label>
                                    <select
                                        id="registration-channel"
                                        value={registrationChannel}
                                        onChange={(event) => {
                                            setRegistrationChannel(
                                                event.target.value as 'phone' | 'email',
                                            );
                                            setRegistrationContact('');
                                        }}
                                        disabled={loading}
                                    >
                                        <option value="phone">Nombor Telefon</option>
                                        <option value="email">E-mel</option>
                                    </select>
                                </div>

                                <div>
                                    <label htmlFor="registration-contact">
                                        {registrationChannel === 'phone'
                                            ? 'Nombor Telefon'
                                            : 'Alamat E-mel'}
                                    </label>
                                    <input
                                        id="registration-contact"
                                        type={
                                            registrationChannel === 'phone'
                                                ? 'tel'
                                                : 'email'
                                        }
                                        autoComplete={
                                            registrationChannel === 'phone'
                                                ? 'tel'
                                                : 'email'
                                        }
                                        value={registrationContact}
                                        onChange={(event) =>
                                            setRegistrationContact(event.target.value)
                                        }
                                        required
                                        maxLength={
                                            registrationChannel === 'phone' ? 30 : 254
                                        }
                                        disabled={loading}
                                    />
                                </div>

                                {error && <p role="alert">{error}</p>}

                                <button type="submit" disabled={loading}>
                                    {loading ? 'Menghantar OTP...' : 'Hantar Kod OTP'}
                                </button>
                            </form>
                        )}

                        {registrationStep === 2 && (
                            <form onSubmit={handleVerifyRegistrationOtp}>
                                <p>
                                    Masukkan kod OTP 6 digit yang dihantar ke{' '}
                                    <strong>{registrationContact}</strong>.
                                </p>

                                <div>
                                    <label htmlFor="registration-otp">Kod OTP</label>
                                    <input
                                        id="registration-otp"
                                        type="text"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        value={registrationOtp}
                                        onChange={(event) =>
                                            setRegistrationOtp(
                                                event.target.value
                                                    .replace(/\D/g, '')
                                                    .slice(0, 6),
                                            )
                                        }
                                        required
                                        minLength={6}
                                        maxLength={6}
                                        pattern="[0-9]{6}"
                                        disabled={loading}
                                    />
                                </div>

                                {error && <p role="alert">{error}</p>}

                                <button
                                    type="submit"
                                    disabled={loading || registrationOtp.length !== 6}
                                >
                                    {loading ? 'Mengesahkan...' : 'Sahkan OTP'}
                                </button>

                                <button
                                    type="button"
                                    disabled={loading}
                                    onClick={() => {
                                        setRegistrationStep(1);
                                        setRegistrationVerificationId('');
                                        setRegistrationOtp('');
                                        setError('');
                                    }}
                                >
                                    Tukar Nombor / E-mel
                                </button>
                            </form>
                        )}

                        {registrationStep === 3 && (
                            <form onSubmit={handleCompleteRegistration}>
                                <p>
                                    OTP berjaya disahkan untuk{' '}
                                    <strong>{registrationContact}</strong>.
                                </p>

                                <div>
                                    <label htmlFor="registration-name">Nama</label>
                                    <input
                                        id="registration-name"
                                        type="text"
                                        autoComplete="name"
                                        value={registrationName}
                                        onChange={(event) =>
                                            setRegistrationName(event.target.value)
                                        }
                                        required
                                        maxLength={150}
                                        disabled={loading}
                                    />
                                </div>

                                <div>
                                    <label htmlFor="registration-password">
                                        Kata Laluan
                                    </label>
                                    <input
                                        id="registration-password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={registrationPassword}
                                        onChange={(event) =>
                                            setRegistrationPassword(event.target.value)
                                        }
                                        required
                                        minLength={8}
                                        maxLength={12}
                                        disabled={loading}
                                    />
                                    <small>
                                        8–12 aksara, mesti ada huruf besar, huruf kecil dan nombor.
                                    </small>
                                </div>

                                <div>
                                    <label htmlFor="registration-password-confirmation">
                                        Sahkan Kata Laluan
                                    </label>
                                    <input
                                        id="registration-password-confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        value={registrationPasswordConfirmation}
                                        onChange={(event) =>
                                            setRegistrationPasswordConfirmation(
                                                event.target.value,
                                            )
                                        }
                                        required
                                        minLength={8}
                                        maxLength={12}
                                        disabled={loading}
                                    />
                                </div>

                                {error && <p role="alert">{error}</p>}

                                <button type="submit" disabled={loading}>
                                    {loading ? 'Mencipta akaun...' : 'Cipta Akaun'}
                                </button>

                                <button
                                    type="button"
                                    disabled={loading}
                                    onClick={() => {
                                        setRegistrationStep(2);
                                        setRegistrationVerificationToken('');
                                        setError('');
                                    }}
                                >
                                    Kembali
                                </button>
                            </form>
                        )}

                        <p>
                            Sudah ada akaun?{' '}
                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => {
                                    resetRegistration();
                                    setAuthMode('login');
                                }}
                            >
                                Log Masuk
                            </button>
                        </p>
                    </>
                )}
            </main>
        );
    }

    return (
        <div className="customer-layout">
            <header className="customer-header">
                <a
                    className="customer-brand"
                    href="#dashboard"
                    onClick={(event) => {
                        event.preventDefault();
                        closeTaskDetail();
                        setPage('dashboard');
                    }}
                >
                    Super Runner
                </a>

                <div className="customer-header-account">
                    <span>{account.name}</span>
                    <button
                        type="button"
                        onClick={handleLogout}
                        disabled={loading}
                    >
                        {loading ? '...' : 'Logout'}
                    </button>
                </div>
            </header>

            <div className="customer-body">
                <nav className="customer-sidebar" aria-label="Menu utama">
                    <button
                        type="button"
                        className={page === 'dashboard' ? 'active' : ''}
                        onClick={() => {
                            closeTaskDetail();
                            setPage('dashboard');
                        }}
                    >
                        Dashboard
                    </button>

                    <button
                        type="button"
                        className={page === 'request' ? 'active' : ''}
                        onClick={() => {
                            closeTaskDetail();
                            setError('');
                            setRequestSuccess('');
                            setPage('request');
                        }}
                    >
                        Open Request
                    </button>

                    <button
                        type="button"
                        className={page === 'tasks' ? 'active' : ''}
                        onClick={() => {
                            closeTaskDetail();
                            setPage('tasks');
                        }}
                    >
                        Tugasan Saya
                    </button>

                    <button
                        type="button"
                        className={page === 'profile' ? 'active' : ''}
                        onClick={() => {
                            closeTaskDetail();
                            setPage('profile');
                        }}
                    >
                        Profil
                    </button>
                </nav>

                <main className="customer-content">
                    {error && page !== 'request' && (
                        <p role="alert">{error}</p>
                    )}

                    {selectedTaskId && taskDetailLoading ? (
                        <section className="customer-panel">
                            <p>Memuatkan butiran tugasan...</p>
                        </section>
                    ) : selectedTaskId && taskDetailError ? (
                        <section className="customer-panel">
                            <button
                                className="customer-back-button"
                                type="button"
                                onClick={closeTaskDetail}
                            >
                                ← Kembali ke senarai
                            </button>
                            <p role="alert">{taskDetailError}</p>
                        </section>
                    ) : selectedTask ? (
                        renderTaskDetail(selectedTask)
                    ) : page === 'dashboard' ? (
                        renderDashboard()
                    ) : page === 'tasks' ? (
                        <section className="customer-panel">
                            <h2>Tugasan Saya</h2>
                            {renderTaskList()}
                        </section>
                    ) : page === 'request' ? (
                        renderRequestForm()
                    ) : (
                        renderProfile()
                    )}
                </main>
            </div>
        </div>
    );
}

const rootElement = document.getElementById('customer-app');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <CustomerApp />
        </StrictMode>,
    );
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .catch((error) => {
                console.error(
                    'Customer Service Worker registration failed:',
                    error,
                );
            });
    });
}
