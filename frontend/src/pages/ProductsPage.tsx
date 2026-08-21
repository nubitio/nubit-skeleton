import { SchemaCrudPage, defineResource } from '@nubitio/react-admin';

/**
 * Full CRUD page generated from the API docs: fields, filters, sorting and
 * forms all come from the `x-crud` hints that nubitio/api-platform publishes
 * in /api/docs.jsonld. No field definitions needed.
 *
 * `defineResource` is where you opt into per-page behaviour — a drawer view
 * mode, master-detail lines, row-level permissions, an export button, or the
 * audit-trail History action once nubit_admin.audit is enabled:
 *
 *   auditTrail: { enabled: true, apiUrl: (id) => `/api/audit-trail/product/${id}` }
 */
const products = defineResource('/api/products', { title: 'Products' });

export function ProductsPage() {
  return <SchemaCrudPage resource={products} />;
}
