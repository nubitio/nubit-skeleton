import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  AdminShell,
  CoreConfigProvider,
  CoreProvider,
  HydraResourceSchemaProvider,
  HydraResourceStoreProvider,
  MercureProvider,
  SchemaProvider,
  SmartCrudRolesProvider,
  ThemeProvider,
  ThemeSwitcher,
  type AdminMenuItem,
} from '@nubitio/react-admin';
import { useSession } from './hooks/useSession';
import { LoginPage } from './pages/LoginPage';
import { ProductsPage } from './pages/ProductsPage';
import { SalesDocumentsPage } from './pages/SalesDocumentsPage';
import { ToastHost, useAppRuntime } from './runtime/ToastHost';
import './runtime/toast.css';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
});

const menu: AdminMenuItem[] = [
  { text: 'Products', icon: 'ph ph-package', path: '/products' },
  { text: 'Sales', icon: 'ph ph-receipt', path: '/sales' },
];

const API_BASE_URL = '/api/';
const MERCURE_HUB_URL = import.meta.env.VITE_MERCURE_HUB_URL ?? '/.well-known/mercure';
/** Must match API Platform IRIs (DEFAULT_URI / public API origin). */
const MERCURE_TOPIC_ORIGIN = import.meta.env.VITE_MERCURE_TOPIC_ORIGIN as string | undefined;

function Shell({
  username,
  onLogout,
}: {
  username: string;
  onLogout: () => void;
}) {
  return (
    <MercureProvider hubUrl={MERCURE_HUB_URL}>
      <SchemaProvider>
        <HydraResourceSchemaProvider>
          <HydraResourceStoreProvider>
            <AdminShell
              title="Nubit Admin"
              menuItems={menu}
              renderThemeSwitcher={() => <ThemeSwitcher />}
              renderUserMenu={({ close }) => (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 180 }}>
                  <span style={{ color: 'var(--text-secondary)', fontSize: '0.875rem' }}>{username}</span>
                  <button
                    type="button"
                    onClick={() => {
                      close();
                      onLogout();
                    }}
                  >
                    Sign out
                  </button>
                </div>
              )}
            >
              <Routes>
                <Route path="/" element={<Navigate to="/products" replace />} />
                <Route path="/products" element={<ProductsPage />} />
                <Route path="/sales" element={<SalesDocumentsPage />} />
              </Routes>
            </AdminShell>
          </HydraResourceStoreProvider>
        </HydraResourceSchemaProvider>
      </SchemaProvider>
    </MercureProvider>
  );
}

export function App() {
  const { session, refresh, logout, roles, username } = useSession();
  const { runtime, toasts, dismiss } = useAppRuntime();

  if (session.status === 'loading') {
    return null;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <CoreProvider
          http={{ baseUrl: API_BASE_URL, refreshPath: 'auth/refresh', loginPath: 'auth/login' }}
          runtime={runtime}
        >
          <CoreConfigProvider
            apiBaseUrl={API_BASE_URL}
            locale="en"
            timezone="UTC"
            currency="USD"
            mercureTopicOrigin={MERCURE_TOPIC_ORIGIN}
          >
            <SmartCrudRolesProvider roles={roles}>
              <BrowserRouter>
                {session.status === 'authenticated' ? (
                  <Shell username={username ?? 'User'} onLogout={() => void logout()} />
                ) : (
                  <LoginPage onLoggedIn={() => void refresh()} />
                )}
              </BrowserRouter>
              <ToastHost toasts={toasts} onDismiss={dismiss} />
            </SmartCrudRolesProvider>
          </CoreConfigProvider>
        </CoreProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}