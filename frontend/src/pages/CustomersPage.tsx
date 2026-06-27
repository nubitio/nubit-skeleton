import { SchemaCrudPage, defineResource } from '@nubitio/react-admin';

const customers = defineResource('/api/customers', {
  title: 'Customers',
  editMode: 'batch',
});

export const CustomersPage = () => <SchemaCrudPage resource={customers} />;