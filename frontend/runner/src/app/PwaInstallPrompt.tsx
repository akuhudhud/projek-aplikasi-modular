
import { useEffect, useState } from 'react';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
}

type InstallPlatform = 'ANDROID' | 'IOS' | 'HARMONY' | 'OTHER';

function detectPlatform(): InstallPlatform {
    const userAgent = navigator.userAgent;

    if (/HarmonyOS|HuaweiBrowser|HUAWEI/i.test(userAgent)) {
        return 'HARMONY';
    }

    if (/iPhone|iPad|iPod/i.test(userAgent)) {
        return 'IOS';
    }

    if (/Android/i.test(userAgent)) {
        return 'ANDROID';
    }

    if (/Macintosh/i.test(userAgent) && navigator.maxTouchPoints > 1) {
        return 'IOS';
    }

    return 'OTHER';
}

function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

const panelStyle = {
    position: 'fixed' as const,
    right: '16px',
    bottom: '16px',
    zIndex: 1000,
    width: 'min(360px, calc(100vw - 32px))',
    padding: '16px',
    border: '1px solid #e5e5e5',
    borderRadius: '14px',
    background: '#ffffff',
    color: '#151515',
    boxShadow: '0 8px 32px rgba(0,0,0,0.16)',
    fontFamily: 'inherit',
};

const primaryButtonStyle = {
    width: '100%',
    minHeight: '44px',
    padding: '10px 14px',
    border: 0,
    borderRadius: '9px',
    background: '#ffd400',
    color: '#151515',
    fontSize: '12px',
    fontWeight: 900,
    cursor: 'pointer',
};

const secondaryButtonStyle = {
    width: '100%',
    minHeight: '40px',
    marginTop: '8px',
    padding: '9px 12px',
    border: '1px solid #dddddd',
    borderRadius: '9px',
    background: '#ffffff',
    color: '#151515',
    fontSize: '12px',
    fontWeight: 750,
    cursor: 'pointer',
};

export default function PwaInstallPrompt() {
    const [platform, setPlatform] = useState<InstallPlatform>('OTHER');
    const [installed, setInstalled] = useState(false);
    const [guideOpen, setGuideOpen] = useState(false);
    const [installPrompt, setInstallPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);

    useEffect(() => {
        setPlatform(detectPlatform());
        setInstalled(isStandalone());

        function handleBeforeInstallPrompt(event: Event) {
            event.preventDefault();

            setInstallPrompt(
                event as BeforeInstallPromptEvent,
            );
        }

        function handleAppInstalled() {
            setInstalled(true);
            setInstallPrompt(null);
            setGuideOpen(false);
        }

        window.addEventListener(
            'beforeinstallprompt',
            handleBeforeInstallPrompt,
        );

        window.addEventListener(
            'appinstalled',
            handleAppInstalled,
        );

        const mediaQuery = window.matchMedia(
            '(display-mode: standalone)',
        );

        function handleDisplayModeChange(event: MediaQueryListEvent) {
            if (event.matches) {
                setInstalled(true);
            }
        }

        mediaQuery.addEventListener(
            'change',
            handleDisplayModeChange,
        );

        return () => {
            window.removeEventListener(
                'beforeinstallprompt',
                handleBeforeInstallPrompt,
            );

            window.removeEventListener(
                'appinstalled',
                handleAppInstalled,
            );

            mediaQuery.removeEventListener(
                'change',
                handleDisplayModeChange,
            );
        };
    }, []);

    async function handleInstall() {
        if (!installPrompt) {
            setGuideOpen(true);
            return;
        }

        try {
            await installPrompt.prompt();

            const choice = await installPrompt.userChoice;

            if (choice.outcome === 'accepted') {
                setInstallPrompt(null);
            }
        } catch (error) {
            console.error(
                'PWA installation failed:',
                error,
            );
        }
    }

    if (installed) {
        return null;
    }

    const platformName = {
        ANDROID: 'Android',
        IOS: 'iPhone / iOS',
        HARMONY: 'Huawei HarmonyOS',
        OTHER: 'peranti anda',
    }[platform];

    return (
        <aside
            aria-label="Pemasangan Super Runner"
            style={panelStyle}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    marginBottom: '12px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        width: '42px',
                        height: '42px',
                        flexShrink: 0,
                        borderRadius: '10px',
                        background: '#ffd400',
                        color: '#151515',
                        fontWeight: 950,
                        fontSize: '17px',
                    }}
                >
                    SR
                </div>

                <div>
                    <strong
                        style={{
                            display: 'block',
                            fontSize: '14px',
                            fontWeight: 950,
                        }}
                    >
                        SUPER RUNNER
                    </strong>

                    <span
                        style={{
                            display: 'block',
                            marginTop: '3px',
                            color: '#737373',
                            fontSize: '11px',
                        }}
                    >
                        Pasang aplikasi pada {platformName}
                    </span>
                </div>
            </div>

            <button
                type="button"
                style={primaryButtonStyle}
                onClick={handleInstall}
            >
                {installPrompt
                    ? 'PASANG APLIKASI'
                    : 'CARA PEMASANGAN'}
            </button>

            {guideOpen && (
                <div
                    style={{
                        marginTop: '14px',
                        paddingTop: '12px',
                        borderTop: '1px solid #eeeeee',
                    }}
                >
                    <strong
                        style={{
                            display: 'block',
                            marginBottom: '8px',
                            fontSize: '13px',
                        }}
                    >
                        Cara memasang SUPER RUNNER
                    </strong>

                    {platform === 'ANDROID' && (
                        <ol style={guideListStyle}>
                            <li>
                                Buka menu tiga titik pada Chrome.
                            </li>
                            <li>
                                Pilih Install app atau Pasang aplikasi.
                            </li>
                            <li>
                                Sahkan pemasangan.
                            </li>
                        </ol>
                    )}

                    {platform === 'IOS' && (
                        <ol style={guideListStyle}>
                            <li>
                                Buka SUPER RUNNER menggunakan Safari.
                            </li>
                            <li>
                                Tekan butang Share.
                            </li>
                            <li>
                                Pilih Add to Home Screen.
                            </li>
                            <li>
                                Aktifkan Open as Web App jika pilihan tersedia.
                            </li>
                            <li>
                                Tekan Add.
                            </li>
                        </ol>
                    )}

                    {platform === 'HARMONY' && (
                        <ol style={guideListStyle}>
                            <li>
                                Buka SUPER RUNNER menggunakan pelayar Huawei.
                            </li>
                            <li>
                                Buka menu pelayar.
                            </li>
                            <li>
                                Pilih Add to Home Screen atau pilihan pemasangan
                                aplikasi jika tersedia.
                            </li>
                            <li>
                                Sahkan pemasangan.
                            </li>
                        </ol>
                    )}

                    {platform === 'OTHER' && (
                        <p style={guideTextStyle}>
                            Buka menu pelayar dan cari pilihan
                            Install App atau Add to Home Screen.
                        </p>
                    )}
                </div>
            )}

            <button
                type="button"
                style={secondaryButtonStyle}
                onClick={() => setGuideOpen(!guideOpen)}
            >
                {guideOpen ? 'TUTUP PANDUAN' : 'PANDUAN PEMASANGAN'}
            </button>
        </aside>
    );
}

const guideListStyle = {
    margin: '0',
    paddingLeft: '20px',
    color: '#404040',
    fontSize: '12px',
    lineHeight: 1.8,
};

const guideTextStyle = {
    color: '#404040',
    fontSize: '12px',
    lineHeight: 1.7,
};
