import {
    useCallback,
    useEffect,
    useState,
    type FormEvent,
} from 'react';

import {
    runnerApi,
    type RunnerAccount,
    type RunnerPublicProfile,
    type RunnerProfileAnalytics,
} from '../../api';

interface UseRunnerProfileResult {
    profile: RunnerPublicProfile | null;
    analytics: RunnerProfileAnalytics;
    loading: boolean;
    profileLoadFailed: boolean;
    saving: boolean;
    error: string;
    success: string;
    publicSlug: string;
    bio: string;
    baseLocation: string;
    serviceArea: string;
    servicesText: string;
    isPublished: boolean;
    setPublicSlug: (value: string) => void;
    setBio: (value: string) => void;
    setBaseLocation: (value: string) => void;
    setServiceArea: (value: string) => void;
    setServicesText: (value: string) => void;
    setIsPublished: (value: boolean) => void;
    loadProfile: () => Promise<void>;
    saveProfile: (event: FormEvent<HTMLFormElement>) => Promise<void>;
}

const EMPTY_ANALYTICS: RunnerProfileAnalytics = {
    profile_views: 0,
    requests_from_profile: 0,
    quotations_for_profile_requests: 0,
    completed_tasks_from_profile_requests: 0,
};

function createEmptyProfile(
    account: RunnerAccount,
): RunnerPublicProfile {
    return {
        public_slug: '',
        profile_name: account.profile_name,
        bio: '',
        base_location: '',
        service_area: '',
        services: [],
        is_published: false,
        views_count: 0,
    };
}

export function useRunnerProfile(
    account: RunnerAccount,
): UseRunnerProfileResult {
    const [profile, setProfile] =
        useState<RunnerPublicProfile | null>(null);

    const [analytics, setAnalytics] =
        useState<RunnerProfileAnalytics>(EMPTY_ANALYTICS);

    const [loading, setLoading] = useState(true);
    const [profileLoadFailed, setProfileLoadFailed] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const [publicSlug, setPublicSlug] = useState('');
    const [bio, setBio] = useState('');
    const [baseLocation, setBaseLocation] = useState('');
    const [serviceArea, setServiceArea] = useState('');
    const [servicesText, setServicesText] = useState('');
    const [isPublished, setIsPublished] = useState(false);

    const loadProfile = useCallback(async () => {
        setLoading(true);
        setProfileLoadFailed(false);
        setError('');
        setSuccess('');

        try {
            const result = await runnerApi.getProfile();

            const currentProfile = result
                ?? createEmptyProfile(account);

            setProfile(result);
            setPublicSlug(currentProfile.public_slug);
            setBio(currentProfile.bio ?? '');
            setBaseLocation(currentProfile.base_location ?? '');
            setServiceArea(currentProfile.service_area ?? '');
            setServicesText(
                (currentProfile.services ?? []).join(', '),
            );
            setIsPublished(currentProfile.is_published);

            if (result) {
                try {
                    const statistics =
                        await runnerApi.getProfileAnalytics();

                    setAnalytics(statistics);
                } catch {
                    setAnalytics({
                        ...EMPTY_ANALYTICS,
                        profile_views: result.views_count,
                    });
                }
            } else {
                setAnalytics(EMPTY_ANALYTICS);
            }
        } catch {
            setProfileLoadFailed(true);
            setProfile(null);
            setAnalytics(EMPTY_ANALYTICS);
            setError(
                'Tidak berjaya mendapatkan profil Runner. Sila tekan SEGARKAN sebelum menyimpan profil.',
            );
        } finally {
            setLoading(false);
        }
    }, [account]);

    useEffect(() => {
        void loadProfile();
    }, [loadProfile]);

    const saveProfile = useCallback(async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (saving || profileLoadFailed) {
            return;
        }

        const normalizedSlug = publicSlug
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '-');

        if (!/^[a-z0-9_-]{3,50}$/.test(normalizedSlug)) {
            setError(
                'Pautan profil mesti 3 hingga 50 aksara dan hanya menggunakan huruf, nombor, tanda sempang atau garis bawah.',
            );
            return;
        }

        const services = servicesText
            .split(',')
            .map((service) => service.trim())
            .filter(Boolean)
            .slice(0, 20);

        setSaving(true);
        setError('');
        setSuccess('');

        try {
            const saved = await runnerApi.saveProfile({
                public_slug: normalizedSlug,
                bio: bio.trim() || null,
                base_location: baseLocation.trim() || null,
                service_area: serviceArea.trim() || null,
                services,
                is_published: isPublished,
            });

            setProfile(saved);
            setPublicSlug(saved.public_slug);
            setBio(saved.bio ?? '');
            setBaseLocation(saved.base_location ?? '');
            setServiceArea(saved.service_area ?? '');
            setServicesText((saved.services ?? []).join(', '));
            setIsPublished(saved.is_published);

            setSuccess('Profil Runner berjaya disimpan.');

            try {
                const statistics =
                    await runnerApi.getProfileAnalytics();

                setAnalytics(statistics);
            } catch {
                setAnalytics((current) => ({
                    ...current,
                    profile_views: saved.views_count,
                }));
            }
        } catch (saveError: unknown) {
            const message =
                saveError
                && typeof saveError === 'object'
                && 'response' in saveError
                && saveError.response
                && typeof saveError.response === 'object'
                && 'data' in saveError.response
                && saveError.response.data
                && typeof saveError.response.data === 'object'
                && 'message' in saveError.response.data
                && typeof saveError.response.data.message === 'string'
                    ? saveError.response.data.message
                    : 'Profil tidak berjaya disimpan. Semak maklumat dan cuba semula.';

            setError(message);
        } finally {
            setSaving(false);
        }
    }, [
        saving,
        profileLoadFailed,
        publicSlug,
        servicesText,
        bio,
        baseLocation,
        serviceArea,
        isPublished,
    ]);

    return {
        profile,
        analytics,
        loading,
        profileLoadFailed,
        saving,
        error,
        success,
        publicSlug,
        bio,
        baseLocation,
        serviceArea,
        servicesText,
        isPublished,
        setPublicSlug,
        setBio,
        setBaseLocation,
        setServiceArea,
        setServicesText,
        setIsPublished,
        loadProfile,
        saveProfile,
    };
}
