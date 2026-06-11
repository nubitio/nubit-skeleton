import { SmartCrudPage, defineResource } from '@nubitio/react-admin';

/**
 * Full CRUD page generated from the API docs: fields, filters, sorting and
 * forms all come from the `x-crud` hints that nubitio/api-platform publishes
 * in /api/docs.jsonld. No field definitions needed.
 */
const products = defineResource('/api/products', { title: 'Products' });

export function ProductsPage() {
  return <SmartCrudPage resource={products} />;
}
