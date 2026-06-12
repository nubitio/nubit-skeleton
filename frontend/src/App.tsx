import { useCallback, useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  AdminShell,
  CoreConfigProvider,
  CoreProvider,
  SchemaProvider,
  HydraResourceSchemaProvider,
  HydraResourceStoreProvider,
  ThemeProvider,
  ThemeSwitcher,
  type AdminMenuItem,
} from '@nubitio/react-admin';
import { LoginPage } from './pages/LoginPage';
import { ProductsPage } from './pages/ProductsPage';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
});

const menu: AdminMenuItem[] = [
  { text: 'Products', icon: 'ph ph-package', path: '/products' },
];

// Same-origin via the Vite proxy (dev) or the built SPA served by Symfony
// (prod): cookies flow automatically, no CORS involved.
const API_BASE_URL = '/api/';

function Shell({ onLogout }: { onLogout: () => void }) {
  return (
    <SchemaProvider>
      <HydraResourceSchemaProvider>
        <HydraResourceStoreProvider>
          <AdminShell
            title="Nubit Admin"
            menuItems={menu}
            renderThemeSwitcher={() => <ThemeSwitcher />}
            renderUserMenu={({ close }) => (
              <button
                type="button"
                onClick={() => {
                  close();
                  onLogout();
                }}
              >
                Sign out
              </button>
            )}
          >
            <Routes>
              <Route path="/" element={<Navigate to="/products" replace />} />
              <Route path="/products" element={<ProductsPage />} />
            </Routes>
          </AdminShell>
        </HydraResourceStoreProvider>
      </HydraResourceSchemaProvider>
    </SchemaProvider>
  );
}

export function App() {
  const [authenticated, setAuthenticated] = useState<boolean | null>(null);

  // Probe the session on boot: the HttpOnly cookie is invisible to JS, so we
  // ask the API instead.
  useEffect(() => {
    fetch(`${API_BASE_URL}products?itemsPerPage=1`, { credentials: 'include' })
      .then((r) => setAuthenticated(r.ok))
      .catch(() => setAuthenticated(false));
  }, []);

  const handleLogout = useCallback(() => {
    void fetch(`${API_BASE_URL}auth/logout`, { method: 'POST', credentials: 'include' }).then(() =>
      setAuthenticated(false),
    );
  }, []);

  if (authenticated === null) {
    return null;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <CoreProvider http={{ baseUrl: API_BASE_URL, refreshPath: 'auth/refresh', loginPath: 'auth/login' }}>
          <CoreConfigProvider apiBaseUrl={API_BASE_URL} locale="en" timezone="UTC" currency="USD">
            <BrowserRouter>
              {authenticated ? (
                <Shell onLogout={handleLogout} />
              ) : (
                <LoginPage onLoggedIn={() => setAuthenticated(true)} />
              )}
            </BrowserRouter>
          </CoreConfigProvider>
        </CoreProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
