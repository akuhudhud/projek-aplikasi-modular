
import { useEffect, useState } from 'react';
import './ConnectionStatus.css';

export default function ConnectionStatus() {
    const [isOnline, setIsOnline] = useState(
        () => navigator.onLine,
    );

    useEffect(() => {
        const handleOnline = () => setIsOnline(true);
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        setIsOnline(navigator.onLine);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    if (isOnline) {
        return null;
    }

    return (
        <div
            className="connection-status connection-status--offline"
            role="status"
            aria-live="polite"
        >
            <span className="connection-status__indicator" />
            <span>
                Tiada sambungan internet. Sesetengah fungsi
                aplikasi mungkin tidak tersedia.
            </span>
        </div>
    );
}
