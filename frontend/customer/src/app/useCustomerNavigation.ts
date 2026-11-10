
import {
    useCallback,
    useState,
} from 'react';

import type { CustomerPage } from './navigation';

type CustomerNavigationState = {
    activePage: CustomerPage;
    runnerSlug: string;
    hasInvalidRunnerSlug: boolean;
};

type CustomerNavigationActions = {
    navigate: (page: CustomerPage) => void;
    openRunnerRequest: () => void;
    returnHome: () => void;
};

export type CustomerNavigation =
    CustomerNavigationState & CustomerNavigationActions;

function readRunnerSlug(): {
    slug: string;
    invalid: boolean;
} {
    const parameter = new URLSearchParams(
        window.location.search,
    ).get('runner');

    if (parameter === null) {
        return {
            slug: '',
            invalid: false,
        };
    }

    const value = parameter.trim();

    if (!/^[A-Za-z0-9_-]{3,50}$/.test(value)) {
        return {
            slug: '',
            invalid: true,
        };
    }

    return {
        slug: value.toLowerCase(),
        invalid: false,
    };
}

export function useCustomerNavigation(): CustomerNavigation {
    const [runner] = useState(readRunnerSlug);

    const [activePage, setActivePage] =
        useState<CustomerPage>(() => {
            return runner.slug || runner.invalid
                ? 'create'
                : 'dashboard';
        });

    const navigate = useCallback((page: CustomerPage) => {
        setActivePage(page);
    }, []);

    const openRunnerRequest = useCallback(() => {
        setActivePage('create');
    }, []);

    const returnHome = useCallback(() => {
        setActivePage('dashboard');
    }, []);

    return {
        activePage,
        runnerSlug: runner.slug,
        hasInvalidRunnerSlug: runner.invalid,
        navigate,
        openRunnerRequest,
        returnHome,
    };
}
