
import type { CustomerPage } from '../app/navigation';
import { CUSTOMER_NAVIGATION } from '../app/navigation';

type BottomNavigationProps = {
    activePage: CustomerPage;
    onNavigate: (page: CustomerPage) => void;
};

export default function BottomNavigation({
    activePage,
    onNavigate,
}: BottomNavigationProps) {
    return (
        <nav
            className="sr-bottom-navigation"
            aria-label="Navigasi utama"
        >
            {CUSTOMER_NAVIGATION.map((item) => {
                const active = activePage === item.id;

                return (
                    <button
                        key={item.id}
                        type="button"
                        className={[
                            'sr-bottom-navigation__item',
                            active ? 'is-active' : '',
                            item.primary ? 'is-primary' : '',
                        ]
                            .filter(Boolean)
                            .join(' ')}
                        aria-current={active ? 'page' : undefined}
                        aria-label={item.label}
                        title={item.description}
                        onClick={() => onNavigate(item.id)}
                    >
                        <span className="sr-bottom-navigation__icon">
                            {item.id === 'dashboard' && '⌂'}
                            {item.id === 'requests' && '▤'}
                            {item.id === 'create' && '+'}
                            {item.id === 'tasks' && '▣'}
                            {item.id === 'profile' && '○'}
                        </span>

                        <span className="sr-bottom-navigation__label">
                            {item.label}
                        </span>
                    </button>
                );
            })}
        </nav>
    );
}
