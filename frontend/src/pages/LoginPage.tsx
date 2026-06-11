import { useState, type FormEvent } from 'react';
import { Button, Card, TextField } from '@nubitio/react-admin';

export function LoginPage({ onLoggedIn }: { onLoggedIn: () => void }) {
  const [username, setUsername] = useState('admin@example.com');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ username, password }),
      });
      if (!response.ok) {
        const body = (await response.json().catch(() => null)) as { message?: string } | null;
        setError(body?.message ?? 'Login failed');
        return;
      }
      onLoggedIn();
    } catch {
      setError('Network error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div style={{ display: 'grid', placeItems: 'center', minHeight: '100vh' }}>
      <Card>
        <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 12, width: 320, padding: 8 }}>
          <h2 style={{ margin: 0 }}>Nubit Admin</h2>
          <p style={{ margin: 0, color: 'var(--text-secondary)' }}>
            Demo credentials: admin@example.com / admin1234
          </p>
          <TextField
            placeholder="Email"
            value={username}
            autoComplete="username"
            onChange={(e) => setUsername(e.target.value)}
          />
          <TextField
            placeholder="Password"
            type="password"
            value={password}
            autoComplete="current-password"
            onChange={(e) => setPassword(e.target.value)}
          />
          {error && <p style={{ margin: 0, color: 'var(--error-color, #dc2626)' }}>{error}</p>}
          <Button variant="primary" type="submit" disabled={busy}>
            {busy ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>
      </Card>
    </div>
  );
}
