import type { RefObject } from 'react';
import {
  SchemaCrudPage,
  defineResource,
  type FormHandle,
} from '@nubitio/react-admin';

/**
 * Master-detail example: header fields come from the API docs; line items are
 * inferred from SalesDocumentLine x-crud hints via x-embedded-lines.
 */
const salesDocuments = defineResource('/api/sales_documents', {
  title: 'Sales',
  viewMode: { mode: 'drawer', drawerSize: 'lg' },
  permissions: {
    canView: true,
    canEditRow: (row) => row.status !== 'cancelled',
    canDeleteRow: (row) => row.status === 'draft',
  },
  formDetail: {
    propertyName: 'lines',
    allowAdding: true,
    allowDeleting: true,
    allowUpdating: true,
    required: true,
    summary: {
      sticky: true,
      items: [{ column: 'lineTotal', summaryType: 'sum', valueFormat: 'currency', label: 'Lines total' }],
    },
  },
  onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
    const rows = formRef.current?.getDetailData() ?? [];
    const total = rows.reduce((sum, row) => sum + Number(row.lineTotal ?? 0), 0);
    formRef.current?.setFieldValue('total', total.toFixed(2));
  },
});

export function SalesDocumentsPage() {
  return <SchemaCrudPage resource={salesDocuments} />;
}