import { SmartCrudPage, defineResource } from '@nubitio/react-admin';

/**
 * Full CRUD page generated from the API docs: fields, filters, sorting and
 * forms all come from the `x-crud` hints that nubitio/api-platform publishes
 * in /api/docs.jsonld. No field definitions needed.
 *
 * auditTrail pairs with the bundle audit feature (#[Auditable] on the
 * entity): the row menu gains a History action listing field-level diffs.
 */
const products = defineResource('/api/products', {
  title: 'Products',
  auditTrail: {
    enabled: true,
    apiUrl: (id) => `/api/audit-trail/product/${id}`,
  },
});

export function ProductsPage() {
  return <SmartCrudPage resource={products} />;
}
