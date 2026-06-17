import { SmartCrudPage, defineResource } from '@nubitio/react-admin';

const customers = defineResource('/api/customers', { title: 'Customers' });

export const CustomersPage = () => <SmartCrudPage resource={customers} />;