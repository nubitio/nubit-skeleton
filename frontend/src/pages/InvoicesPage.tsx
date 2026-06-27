import type { RefObject } from 'react';
import {
  SchemaCrudPage,
  defineResource,
  type FormHandle,
} from '@nubitio/react-admin';

/**
 * Reference ERP document page.
 *
 * Demonstrates all four ERP building blocks together:
 *
 *   1. Auto-numbered document (INV-0001…) — driven by #[Sequence] on Invoice.
 *   2. State machine toolbar actions (Confirm / Mark as paid / Cancel / Reopen)
 *      — SchemaCrudPage auto-reads x-workflow from /api/docs.jsonld and builds
 *        row actions; no manual wiring needed.
 *   3. Audit trail (History button) — every field-level change is recorded.
 *   4. Master-detail lines with live totals — line fields are inferred from
 *      InvoiceLine x-crud hints via x-embedded-lines; subtotal + tax recalculated
 *      server-side by InvoiceProcessor; running total shown in the drawer footer.
 *
 * Copy this file as the starting point for:
 *   PurchaseOrdersPage, ReceiptsPage, CreditNotesPage, PayrollRunsPage, etc.
 */
const invoices = defineResource('/api/invoices', {
  title: 'Invoices',
  viewMode: { mode: 'drawer', drawerSize: 'lg' },

  permissions: {
    canView: true,
    // Paid and cancelled invoices are read-only
    canEditRow: (row) => !['paid', 'cancelled'].includes(String(row.status)),
    canDeleteRow: (row) => row.status === 'draft',
  },

  auditTrail: {
    enabled: true,
    apiUrl: (id) => `/api/audit-trail/invoice/${id}`,
  },

  formDetail: {
    propertyName: 'lines',
    allowAdding: true,
    allowDeleting: true,
    allowUpdating: true,
    required: true,
    summary: {
      sticky: true,
      items: [
        { column: 'lineTotal', summaryType: 'sum', valueFormat: 'currency', label: 'Lines total' },
      ],
    },
  },

  // Recompute the header total field live as lines change in the drawer.
  onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
    const rows = formRef.current?.getDetailData() ?? [];
    const subtotal = rows.reduce(
      (sum, row) => sum + Number(row.quantity ?? 0) * Number(row.unitPrice ?? 0),
      0,
    );
    const tax = rows.reduce(
      (sum, row) =>
        sum + Number(row.quantity ?? 0) * Number(row.unitPrice ?? 0) * (Number(row.taxRate ?? 0) / 100),
      0,
    );
    formRef.current?.setFieldValue('total', (subtotal + tax).toFixed(2));
  },
});

export function InvoicesPage() {
  return <SchemaCrudPage resource={invoices} />;
}