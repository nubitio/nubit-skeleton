import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  AdminShell,
  CoreConfigProvider,
  CoreProvider,
  HydraResourceSchemaProvider,
  HydraResourceStoreProvider,
  LoginPage,
  MercureProvider,
  SchemaProvider,
  SessionProvider,
  SmartCrudRolesProvider,
  ThemeProvider,
  ThemeSwitcher,
  ToastHost,
  useAppRuntime,
  useSession,
  type AdminMenuItem,
} from '@nubitio/react-admin';
import { CustomersPage } from './pages/CustomersPage';
import { ProductsPage } from './pages/ProductsPage';
import { SalesDocumentsPage } from './pages/SalesDocumentsPage';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
});

const menu: AdminMenuItem[] = [
  { text: 'Products', icon: 'ph ph-package', path: '/products' },
  { text: 'Customers', icon: 'ph ph-users', path: '/customers' },
  { text: 'Sales', icon: 'ph ph-receipt', path: '/sales' },
];

const API_BASE_URL = '/api/';

function Shell({
  username,
  onLogout,
}: {
  username: string;
  onLogout: () => void;
}) {
  return (
    <MercureProvider>
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
                <Route path="/customers" element={<CustomersPage />} />
                <Route path="/sales" element={<SalesDocumentsPage />} />
              </Routes>
            </AdminShell>
          </HydraResourceStoreProvider>
        </HydraResourceSchemaProvider>
      </SchemaProvider>
    </MercureProvider>
  );
}

function AuthenticatedApp() {
  const { session, refresh, logout, roles, username } = useSession();
  const { runtime, toasts, dismiss } = useAppRuntime();

  if (session.status === 'loading') {
    return null;
  }

  return (
    <CoreProvider
      http={{ baseUrl: API_BASE_URL, refreshPath: 'auth/refresh', loginPath: 'auth/login' }}
      runtime={runtime}
    >
      <CoreConfigProvider apiBaseUrl={API_BASE_URL} locale="en" timezone="UTC" currency="USD">
        <SmartCrudRolesProvider roles={roles}>
          <BrowserRouter>
            {session.status === 'authenticated' ? (
              <Shell username={username ?? 'User'} onLogout={() => void logout()} />
            ) : (
              <LoginPage
                apiBaseUrl={API_BASE_URL}
                defaultUsername="admin@example.com"
                hint="Demo credentials: admin@example.com / admin1234"
                onLoggedIn={() => void refresh()}
              />
            )}
          </BrowserRouter>
          <ToastHost toasts={toasts} onDismiss={dismiss} />
        </SmartCrudRolesProvider>
      </CoreConfigProvider>
    </CoreProvider>
  );
}

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <SessionProvider apiBaseUrl={API_BASE_URL}>
          <AuthenticatedApp />
        </SessionProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}