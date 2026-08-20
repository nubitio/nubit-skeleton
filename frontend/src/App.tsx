import { Suspense, lazy, type ReactNode } from 'react';
import { createNubitApp } from '@nubitio/react-admin';

const ProductsPage = lazy(() =>
  import('./pages/ProductsPage').then((module) => ({ default: module.ProductsPage })),
);
const SalesModule = lazy(() =>
  import('./pages/SalesModule').then((module) => ({ default: module.SalesModule })),
);

const deferred = (element: ReactNode) => (
  <Suspense fallback={<div className="nb-route-loading">Loading…</div>}>{element}</Suspense>
);

const profile = import.meta.env.VITE_NUBIT_PROFILE === 'minimal' ? 'minimal' : 'showcase';
const showcaseMenu =
  profile === 'showcase'
    ? [{ text: 'Sales', icon: 'ph ph-receipt', path: '/sales' }]
    : [];
const showcaseRoutes =
  profile === 'showcase'
    ? [{ path: '/sales/*', element: deferred(<SalesModule />) }]
    : [];

const { App } = createNubitApp({
  title: 'Nubit Admin',
  homePath: '/products',
  menu: [
    { text: 'Products', icon: 'ph ph-package', path: '/products' },
    // Module entry: points to the base path; FeatureHubLayout redirects to the
    // default tab (/sales/invoices) automatically.
    ...showcaseMenu,
  ],
  routes: [
    { path: '/products', element: deferred(<ProductsPage />) },
    // Wildcard path is required — FeatureHubLayout's nested Routes handle the rest.
    ...showcaseRoutes,
  ],
  login: {
    defaultUsername: 'admin@example.com',
    hint: 'Demo credentials: admin@example.com / admin1234',
  },
});

export { App };
