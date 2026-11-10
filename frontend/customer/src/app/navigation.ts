
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
        label: 'Home',
        icon: 'home',
        description: 'Halaman utama pelanggan',
    },
    {
        id: 'requests',
        label: 'Open Request',
        icon: 'clipboard-list',
        description: 'Senarai Open Request pelanggan',
    },
    {
        id: 'create',
        label: 'Create',
        icon: 'plus-circle',
        description: 'Cipta Open Request baharu',
        primary: true,
    },
    {
        id: 'tasks',
        label: 'Tasks',
        icon: 'package',
        description: 'Senarai tugasan pelanggan',
    },
    {
        id: 'profile',
        label: 'Profile',
        icon: 'user-round',
        description: 'Profil dan tetapan akaun',
    },
];

export const CUSTOMER_PAGE_LABELS: Record<CustomerPage, string> = {
    dashboard: 'Home',
    requests: 'Requests',
    create: 'Create Request',
    tasks: 'My Tasks',
    profile: 'My Profile',
};

export function isCustomerPage(
    value: string,
): value is CustomerPage {
    return CUSTOMER_NAVIGATION.some(
        (item) => item.id === value,
    );
}
