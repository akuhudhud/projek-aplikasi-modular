import { useState } from 'react';

import type { RunnerAccount } from '../api';

import { useRunnerProfile } from '../features/profile/useRunnerProfile';

import RunnerProfileForm from '../components/profile/RunnerProfileForm';
import RunnerAccountSettings from '../components/profile/RunnerAccountSettings';

type ProfileTab = 'PROFILE' | 'SETTINGS';

interface RunnerProfileSettingsProps {
    account: RunnerAccount;
    onLogout: () => void;
    logoutLoading: boolean;
}

export default function RunnerProfileSettings({
    account,
    onLogout,
    logoutLoading,
}: RunnerProfileSettingsProps) {
    const [activeTab, setActiveTab] =
        useState<ProfileTab>('PROFILE');

    const profileModule = useRunnerProfile(account);

    if (profileModule.loading) {
        return (
            <section className="runner-dashboard__section">
                <p className="runner-dashboard__notice">
                    Memuatkan profil dan tetapan Runner...
                </p>
            </section>
        );
    }

    return (
        <section className="runner-dashboard__section">
            <div className="runner-dashboard__section-heading">
                <div>
                    <h2>Profil dan Tetapan</h2>
                    <p>
                        Urus maklumat profil awam dan maklumat akaun Runner.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => void profileModule.loadProfile()}
                    disabled={profileModule.saving || profileModule.loading}
                >
                    SEGARKAN
                </button>
            </div>

            <nav
                className="runner-dashboard__tabs runner-profile__tabs"
                aria-label="Menu profil Runner"
            >
                <button
                    type="button"
                    className={activeTab === 'PROFILE' ? 'is-active' : ''}
                    onClick={() => setActiveTab('PROFILE')}
                >
                    PROFIL SAYA
                </button>

                <button
                    type="button"
                    className={activeTab === 'SETTINGS' ? 'is-active' : ''}
                    onClick={() => setActiveTab('SETTINGS')}
                >
                    TETAPAN AKAUN
                </button>
            </nav>

            {profileModule.error && (
                <p className="runner-dashboard__alert" role="alert">
                    {profileModule.error}
                </p>
            )}

            {profileModule.success && (
                <p className="runner-dashboard__success" role="status">
                    {profileModule.success}
                </p>
            )}

            {activeTab === 'PROFILE' && (
                <RunnerProfileForm
                    account={account}
                    profile={profileModule.profile}
                    analytics={profileModule.analytics}
                    profileLoadFailed={profileModule.profileLoadFailed}
                    saving={profileModule.saving}
                    publicSlug={profileModule.publicSlug}
                    bio={profileModule.bio}
                    baseLocation={profileModule.baseLocation}
                    serviceArea={profileModule.serviceArea}
                    servicesText={profileModule.servicesText}
                    isPublished={profileModule.isPublished}
                    onPublicSlugChange={profileModule.setPublicSlug}
                    onBioChange={profileModule.setBio}
                    onBaseLocationChange={profileModule.setBaseLocation}
                    onServiceAreaChange={profileModule.setServiceArea}
                    onServicesTextChange={profileModule.setServicesText}
                    onPublishedChange={profileModule.setIsPublished}
                    onSubmit={profileModule.saveProfile}
                />
            )}

            {activeTab === 'SETTINGS' && (
                <RunnerAccountSettings
                    account={account}
                    onLogout={onLogout}
                    logoutLoading={logoutLoading}
                />
            )}
        </section>
    );
}
