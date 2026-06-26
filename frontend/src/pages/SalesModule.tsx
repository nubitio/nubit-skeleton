import { Navigate, Route, Routes } from 'react-router-dom';
import { FeatureHubLayout } from '@nubitio/react-admin';
import { CustomersPage } from './CustomersPage';
import { InvoicesPage } from './InvoicesPage';
import { SalesDocumentsPage } from './SalesDocumentsPage';

/**
 * Module navigation pattern using FeatureHubLayout.
 *
 * FeatureHubLayout uses <Outlet /> from react-router-dom, so it MUST be
 * used as a layout route inside a nested <Routes>. The parent entry in
 * App.tsx must use a wildcard path: { path: '/sales/*', element: <SalesModule /> }
 *
 * Copy this pattern for every ERP module:
 *   PurchasingModule  → /purchasing/* (Purchase orders, Suppliers, Receipts)
 *   InventoryModule   → /inventory/*  (Items, Warehouses, Stock moves)
 *   AccountingModule  → /accounting/* (Chart of accounts, Journal entries)
 *   HrModule          → /hr/*         (Employees, Departments, Payroll)
 */
const BASE = '/sales';

export function SalesModule() {
  return (
    <Routes>
      {/*
       * Layout route: FeatureHubLayout renders the tab header + <Outlet />.
       * Child routes render inside the outlet — no extra wrapper needed.
       */}
      <Route
        element={
          <FeatureHubLayout
            title="Sales"
            subtitle="Invoices, orders and customer management"
            basePath={BASE}
            defaultPath={`${BASE}/invoices`}
            density="compact"
            tabs={[
              { key: 'invoices',  label: 'Invoices',  path: `${BASE}/invoices`,  icon: 'invoice' },
              { key: 'orders',    label: 'Orders',    path: `${BASE}/orders`,    icon: 'receipt' },
              { key: 'customers', label: 'Customers', path: `${BASE}/customers`, icon: 'users' },
            ]}
          />
        }
      >
        <Route index element={<Navigate to={`${BASE}/invoices`} replace />} />
        <Route path="invoices"  element={<InvoicesPage />} />
        <Route path="orders"    element={<SalesDocumentsPage />} />
        <Route path="customers" element={<CustomersPage />} />
      </Route>
    </Routes>
  );
}
