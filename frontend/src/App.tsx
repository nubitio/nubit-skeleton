import { createNubitApp } from '@nubitio/react-admin';
import { ProductsPage } from './pages/ProductsPage';
import { SalesModule } from './pages/SalesModule';

const { App } = createNubitApp({
  title: 'Nubit Admin',
  homePath: '/products',
  menu: [
    { text: 'Products', icon: 'ph ph-package', path: '/products' },
    // Module entry: points to the base path; FeatureHubLayout redirects to the
    // default tab (/sales/invoices) automatically.
    { text: 'Sales', icon: 'ph ph-receipt', path: '/sales' },
  ],
  routes: [
    { path: '/products', element: <ProductsPage /> },
    // Wildcard path is required — FeatureHubLayout's nested Routes handle the rest.
    { path: '/sales/*', element: <SalesModule /> },
  ],
  login: {
    defaultUsername: 'admin@example.com',
    hint: 'Demo credentials: admin@example.com / admin1234',
  },
});

export { App };
