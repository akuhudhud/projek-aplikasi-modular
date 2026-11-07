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
import {
    LocationSnapshotPanel,
    isRequestExpiryValid,
} from './customerPresentation';
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
    const safetyBuffer = 10 * 60 * 1000;
    const date = new Date(
        Date.now() + 24 * 60 * 60 * 1000 - safetyBuffer,
    );
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
    const [ownRequestRefreshKey, setOwnRequestRefreshKey] = useState(0);

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
    }, [account, page, ownRequestPage, ownRequestRefreshKey]);

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
