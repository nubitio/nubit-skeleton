import { Suspense, lazy, type ReactNode } from 'react';
import { createNubitApp } from '@nubitio/react-admin';

const ProductsPage = lazy(() =>
  import('./pages/ProductsPage').then((module) => ({ default: module.ProductsPage })),
);

/** Routes are lazy chunks so a page is only fetched when it is first visited. */
const deferred = (element: ReactNode) => (
  <Suspense fallback={<div className="nb-route-loading">Loading…</div>}>{element}</Suspense>
);

const { App } = createNubitApp({
  title: 'Nubit Admin',
  homePath: '/products',
  // One entry per screen. A grouped module uses FeatureHubLayout and a
  // wildcard route (`/sales/*`) whose nested Routes render each tab.
  menu: [{ text: 'Products', icon: 'ph ph-package', path: '/products' }],
  routes: [{ path: '/products', element: deferred(<ProductsPage />) }],
  login: {
    defaultUsername: 'admin@example.com',
    hint: 'Demo credentials: admin@example.com / admin1234',
  },
});

export { App };
