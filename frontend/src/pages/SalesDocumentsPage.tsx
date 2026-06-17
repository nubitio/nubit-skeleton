import type { RefObject } from 'react';
import {
  SmartCrudPage,
  currencyField,
  defineResource,
  entityField,
  numberField,
  type FormHandle,
} from '@nubitio/react-admin';

/**
 * Master-detail example: header fields come from the API docs; line items are
 * edited inline through formDetail and submitted embedded under `lines`.
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
    url: '/api/sales_document_lines?document={id}',
    allowAdding: true,
    allowDeleting: true,
    allowUpdating: true,
    required: true,
    summary: {
      sticky: true,
      items: [{ column: 'lineTotal', summaryType: 'sum', valueFormat: 'currency', label: 'Lines total' }],
    },
    fields: [
      entityField('/api/products', '_iri', 'name').name('product').label('Product').required(true).build(),
      numberField().name('quantity').label('Quantity').required(true).precision(2).build(),
      currencyField().name('unitPrice').label('Unit price').required(true).build(),
      currencyField().name('lineTotal').label('Line total').readonly(true).build(),
    ],
  },
  onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
    const rows = formRef.current?.getDetailData() ?? [];
    const total = rows.reduce((sum, row) => sum + Number(row.lineTotal ?? 0), 0);
    formRef.current?.setFieldValue('total', total.toFixed(2));
  },
});

export function SalesDocumentsPage() {
  return <SmartCrudPage resource={salesDocuments} />;
}