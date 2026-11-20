
import type {
    ChangeEvent,
    FormEvent,
} from 'react';

type RunnerLoginFormProps = {
    identifier: string;
    password: string;
    showPassword: boolean;
    loading: boolean;
    error: string;
    onIdentifierChange: (value: string) => void;
    onPasswordChange: (value: string) => void;
    onTogglePassword: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export default function RunnerLoginForm({
    identifier,
    password,
    showPassword,
    loading,
    error,
    onIdentifierChange,
    onPasswordChange,
    onTogglePassword,
    onSubmit,
}: RunnerLoginFormProps) {
    function handleIdentifierChange(
        event: ChangeEvent<HTMLInputElement>,
    ) {
        onIdentifierChange(event.target.value);
    }

    function handlePasswordChange(
        event: ChangeEvent<HTMLInputElement>,
    ) {
        onPasswordChange(event.target.value);
    }

    return (
        <main className="runner-auth">
            <section
                className="runner-auth__panel"
                aria-labelledby="runner-auth-heading"
            >
                <div className="runner-auth__brand">
                    <div
                        className="runner-auth__brand-mark"
                        aria-hidden="true"
                    >
                        SR
                    </div>

                    <div>
                        <h1>SUPER RUNNER</h1>
                        <p>Runner Platform</p>
                    </div>
                </div>

                <div className="runner-auth__heading">
                    <h2 id="runner-auth-heading">
                        Log Masuk Runner
                    </h2>

                    <p>
                        Log masuk menggunakan akaun Super Runner
                        anda untuk meneruskan.
                    </p>
                </div>

                <form
                    className="runner-auth__form"
                    onSubmit={onSubmit}
                >
                    <div className="runner-auth__field">
                        <label htmlFor="runner-identifier">
                            Nombor Telefon atau E-mel
                        </label>

                        <input
                            id="runner-identifier"
                            type="text"
                            autoComplete="username"
                            value={identifier}
                            onChange={handleIdentifierChange}
                            placeholder="Masukkan nombor telefon atau e-mel"
                            required
                            disabled={loading}
                        />
                    </div>

                    <div className="runner-auth__field">
                        <label htmlFor="runner-password">
                            Kata Laluan
                        </label>

                        <div className="runner-auth__password">
                            <input
                                id="runner-password"
                                type={
                                    showPassword
                                        ? 'text'
                                        : 'password'
                                }
                                autoComplete="current-password"
                                value={password}
                                onChange={handlePasswordChange}
                                placeholder="Masukkan kata laluan"
                                required
                                disabled={loading}
                            />

                            <button
                                type="button"
                                onClick={onTogglePassword}
                                disabled={loading}
                                aria-label={
                                    showPassword
                                        ? 'Sembunyikan kata laluan'
                                        : 'Paparkan kata laluan'
                                }
                            >
                                {showPassword ? 'Sembunyi' : 'Papar'}
                            </button>
                        </div>
                    </div>

                    {error && (
                        <p
                            className="runner-auth__error"
                            role="alert"
                        >
                            {error}
                        </p>
                    )}

                    <button
                        className="runner-auth__submit"
                        type="submit"
                        disabled={loading}
                    >
                        {loading
                            ? 'Sedang log masuk...'
                            : 'LOG MASUK'}
                    </button>
                </form>

                <div className="runner-auth__footer">
                    <p>
                        Belum mempunyai akaun?
                    </p>

                    <p>
                        Daftar akaun melalui platform
                        Super Runner Customer terlebih dahulu.
                    </p>
                </div>
            </section>
        </main>
    );
}
