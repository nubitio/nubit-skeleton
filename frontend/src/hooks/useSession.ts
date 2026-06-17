import { useCallback, useEffect, useState } from 'react';

export type SessionProfile = {
  username: string;
  roles: string[];
};

type SessionState =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'authenticated'; profile: SessionProfile };

const API_BASE_URL = '/api/';

export function useSession() {
  const [session, setSession] = useState<SessionState>({ status: 'loading' });

  const refresh = useCallback(async () => {
    try {
      const response = await fetch(`${API_BASE_URL}me`, { credentials: 'include' });
      if (!response.ok) {
        setSession({ status: 'anonymous' });
        return;
      }

      const profile = (await response.json()) as SessionProfile;
      setSession({ status: 'authenticated', profile });
    } catch {
      setSession({ status: 'anonymous' });
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const logout = useCallback(async () => {
    await fetch(`${API_BASE_URL}auth/logout`, { method: 'POST', credentials: 'include' });
    setSession({ status: 'anonymous' });
  }, []);

  return {
    session,
    refresh,
    logout,
    roles: session.status === 'authenticated' ? session.profile.roles : [],
    username: session.status === 'authenticated' ? session.profile.username : null,
  };
}