
export type CustomerPage =
    | 'dashboard'
    | 'requests'
    | 'create'
    | 'tasks'
    | 'profile';

export type CustomerNavigationItem = {
    id: CustomerPage;
    label: string;
    icon: string;
    description: string;
    primary?: boolean;
};

export const CUSTOMER_NAVIGATION: CustomerNavigationItem[] = [
    {
        id: 'dashboard',
        label: 'Utama',
        icon: 'home',
        description: 'Halaman utama pelanggan',
    },
    {
        id: 'requests',
        label: 'Permintaan',
        icon: 'clipboard-list',
        description: 'Senarai permintaan tugasan anda',
    },
    {
        id: 'create',
        label: 'Cipta Tugasan',
        icon: 'plus-circle',
        description: 'Cipta tugasan baharu',
        primary: true,
    },
    {
        id: 'tasks',
        label: 'Tugasan',
        icon: 'package',
        description: 'Senarai tugasan anda',
    },
    {
        id: 'profile',
        label: 'Profil',
        icon: 'user-round',
        description: 'Profil dan tetapan akaun',
    },
];

export const CUSTOMER_PAGE_LABELS: Record<CustomerPage, string> = {
    dashboard: 'Utama',
    requests: 'Senarai Permintaan',
    create: 'Cipta Tugasan',
    tasks: 'Tugasan Saya',
    profile: 'Profil Saya',
};

export function isCustomerPage(
    value: string,
): value is CustomerPage {
    return CUSTOMER_NAVIGATION.some(
        (item) => item.id === value,
    );
}
