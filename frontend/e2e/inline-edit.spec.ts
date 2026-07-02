import { expect, test, type Page } from '@playwright/test';

const ADMIN_EMAIL = 'admin@example.com';
const ADMIN_PASSWORD = 'admin1234';

async function login(page: Page) {
  await page.goto('/');
  await page.getByPlaceholder('Email').fill(ADMIN_EMAIL);
  await page.getByPlaceholder('Password').fill(ADMIN_PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });
}

async function goToCustomers(page: Page) {
  await page.goto('/sales/customers');
  await expect(page.getByText('Acme Retail')).toBeVisible({ timeout: 10_000 });
}

function customerRow(page: Page, name: string) {
  return page.getByRole('row').filter({ hasText: name });
}

async function editFirstTextCell(page: Page, rowText: string) {
  const row = customerRow(page, rowText);
  await row.locator('td.nb-datagrid__cell--editable').first().click();

  const activeCell = page.locator('td.nb-datagrid__edit-cell.nb-datagrid__cell--active');
  await expect(activeCell).toBeVisible({ timeout: 5_000 });

  const input = activeCell.getByRole('textbox').first();
  await expect(input).toBeVisible();
  return input;
}

async function updateCustomerName(page: Page, from: string, to: string) {
  const input = await editFirstTextCell(page, from);
  await input.clear();
  await input.fill(to);
}

const saveAllButton = (page: Page) => page.locator('.nb-datagrid__toolbar-icon-action--save');
const discardAllButton = (page: Page) => page.locator('.nb-datagrid__toolbar-icon-action--revert');

test.describe.configure({ timeout: 60_000 });

test.describe('Inline editing (batch mode)', () => {
  test('clicking an editable cell opens the active cell editor', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    const input = await editFirstTextCell(page, 'Acme Retail');

    await expect(input).toHaveValue('Acme Retail');
    await expect(page.locator('td.nb-datagrid__cell--active')).toHaveCount(1);
    await expect(page.locator('tr.nb-datagrid__row--editing')).toHaveCount(0);
  });

  test('toolbar save and discard actions appear after a cell changes', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await updateCustomerName(page, 'Acme Retail', 'Acme Retail Draft');

    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(1, { timeout: 5_000 });
    await expect(page.locator('td.nb-datagrid__cell--dirty')).toHaveCount(1);
    await expect(saveAllButton(page)).toBeVisible();
    await expect(discardAllButton(page)).toBeVisible();
  });

  test('discard all clears dirty rows and restores the original value', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await updateCustomerName(page, 'Acme Retail', 'Acme Retail Draft');
    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(1);

    await discardAllButton(page).click();

    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(0, { timeout: 3_000 });
    await expect(saveAllButton(page)).toBeHidden();
    await expect(discardAllButton(page)).toBeHidden();
    await expect(customerRow(page, 'Acme Retail')).toBeVisible();
  });

  test('multiple rows can hold unsaved changes simultaneously', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await updateCustomerName(page, 'Acme Retail', 'Acme Retail Draft');
    await updateCustomerName(page, 'Global Wholesale', 'Global Wholesale Draft');

    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(2, { timeout: 5_000 });
    await expect(page.locator('td.nb-datagrid__cell--dirty')).toHaveCount(2);
  });

  test('discard all clears multiple dirty rows', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await updateCustomerName(page, 'Acme Retail', 'Acme Retail Draft');
    await updateCustomerName(page, 'Global Wholesale', 'Global Wholesale Draft');
    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(2);

    await discardAllButton(page).click();

    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(0, { timeout: 3_000 });
    await expect(customerRow(page, 'Acme Retail')).toBeVisible();
    await expect(customerRow(page, 'Global Wholesale')).toBeVisible();
  });

  test('toolbar save patches the row and clears dirty state', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await updateCustomerName(page, 'Acme Retail', 'Acme Retail Updated');
    await saveAllButton(page).click();

    await expect(page.locator('tr.nb-datagrid__row--dirty')).toHaveCount(0, { timeout: 10_000 });
    await expect(customerRow(page, 'Acme Retail Updated')).toBeVisible();

    await updateCustomerName(page, 'Acme Retail Updated', 'Acme Retail');
    await saveAllButton(page).click();
    await expect(customerRow(page, 'Acme Retail')).toBeVisible({ timeout: 10_000 });
  });
});
