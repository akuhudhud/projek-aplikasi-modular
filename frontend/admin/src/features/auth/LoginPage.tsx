
import { useState, type FormEvent } from 'react';

interface LoginPageProps {
  onLogin: (identifier: string, password: string) => Promise<void>;
  error: string;
  loading: boolean;
}

export default function LoginPage({
  onLogin,
  error,
  loading
}: LoginPageProps) {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    await onLogin(identifier.trim(), password);
  }

  return (
    <main className="login-page">
      <form className="login-card" onSubmit={handleSubmit}>
        <div className="login-card__logo">SR</div>

        <span className="admin-header__eyebrow">
          SUPER RUNNER
        </span>

        <h1>Log Masuk Pentadbir</h1>

        <p>
          Gunakan nombor telefon atau e-mel akaun pentadbir.
        </p>

        <label htmlFor="admin-identifier">
          Nombor Telefon / E-mel
        </label>

        <input
          id="admin-identifier"
          autoComplete="username"
          value={identifier}
          onChange={(event) => setIdentifier(event.target.value)}
          required
        />

        <label htmlFor="admin-password">
          Kata Laluan
        </label>

        <input
          id="admin-password"
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          required
        />

        {error && (
          <div className="admin-alert admin-alert--error" role="alert">
            {error}
          </div>
        )}

        <button
          className="admin-button admin-button--primary"
          type="submit"
          disabled={loading}
        >
          {loading ? 'Sedang log masuk...' : 'Log Masuk'}
        </button>
      </form>
    </main>
  );
}
