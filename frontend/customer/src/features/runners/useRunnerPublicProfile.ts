
import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

import {
    runnerPublicApi,
    type RunnerPublicProfileData,
} from './runnerPublicApi';

interface UseRunnerPublicProfileResult {
    profile: RunnerPublicProfileData | null;
    loading: boolean;
    notFound: boolean;
    error: string;
    reload: () => void;
}

export function useRunnerPublicProfile(
    slug: string,
): UseRunnerPublicProfileResult {
    const [profile, setProfile] =
        useState<RunnerPublicProfileData | null>(null);
    const [loading, setLoading] = useState(true);
    const [notFound, setNotFound] = useState(false);
    const [error, setError] = useState('');
    const [reloadKey, setReloadKey] = useState(0);

    const reload = useCallback(() => {
        setReloadKey((current) => current + 1);
    }, []);

    useEffect(() => {
        let active = true;

        setProfile(null);
        setLoading(true);
        setNotFound(false);
        setError('');

        runnerPublicApi
            .getProfile(slug)
            .then((data) => {
                if (!active) return;

                setProfile(data);
            })
            .catch((requestError: unknown) => {
                if (!active) return;

                if (
                    axios.isAxiosError(requestError) &&
                    requestError.response?.status === 404
                ) {
                    setNotFound(true);
                    return;
                }

                setError(
                    'Tidak berjaya memuatkan profil Runner. Sila cuba lagi.',
                );
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [slug, reloadKey]);

    return {
        profile,
        loading,
        notFound,
        error,
        reload,
    };
}
