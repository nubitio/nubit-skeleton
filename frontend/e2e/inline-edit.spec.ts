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

/** Locator for the row that is currently in inline-edit mode. */
function editingRow(page: Page) {
  return page.locator('tr.nb-datagrid__row--editing');
}

test.describe.configure({ timeout: 60_000 });

test.describe('Inline row editing (batch mode)', () => {
  test('double-click row enters edit mode with inline controls', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    // Double-click the first data row (Acme Retail)
    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();

    // Row should now have editing class and show inline input controls
    const row = editingRow(page);
    await expect(row.getByRole('textbox').first()).toBeVisible({ timeout: 5_000 });

    // Save (✓) and Cancel (✗) buttons should appear in the actions cell
    await expect(row.getByTitle('Save row')).toBeVisible();
    await expect(row.getByTitle('Cancel edit')).toBeVisible();
  });

  test('batch bar appears when a row is in edit mode', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();

    // Batch bar should appear above the table
    const batchBar = page.locator('.nb-datagrid__batch-bar');
    await expect(batchBar).toBeVisible({ timeout: 5_000 });
    await expect(batchBar).toContainText('1 row');
  });

  test('cancel edit restores read mode and hides batch bar', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();

    const row = editingRow(page);
    await expect(row.getByTitle('Cancel edit')).toBeVisible();
    await row.getByTitle('Cancel edit').click();

    // Row should no longer have editing class
    await expect(page.locator('tr.nb-datagrid__row--editing')).toHaveCount(0, { timeout: 3_000 });

    // Batch bar should disappear
    await expect(page.locator('.nb-datagrid__batch-bar')).toBeHidden();
  });

  test('multiple rows can be open simultaneously in batch mode', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();
    await page.getByRole('row').filter({ hasText: 'Global Wholesale' }).dblclick();

    // Both rows should be in editing mode
    const editingRows = page.locator('tr.nb-datagrid__row--editing');
    await expect(editingRows).toHaveCount(2, { timeout: 5_000 });

    // Batch bar shows 2 rows
    await expect(page.locator('.nb-datagrid__batch-bar')).toContainText('2 row');
  });

  test('edit via ⋮ menu → Edit row starts inline edit', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    // Open the ⋮ actions menu on the Acme Retail row
    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).locator('button').last().click();

    const editItem = page.getByRole('menuitem', { name: /edit row/i });
    await expect(editItem).toBeVisible({ timeout: 5_000 });
    await editItem.click();

    // Row should now be in edit mode
    await expect(editingRow(page).getByRole('textbox').first()).toBeVisible({ timeout: 5_000 });
  });

  test('discard all clears all editing rows', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();
    await page.getByRole('row').filter({ hasText: 'Global Wholesale' }).dblclick();

    await expect(page.locator('.nb-datagrid__batch-bar')).toBeVisible();

    await page.locator('.nb-datagrid__batch-bar-btn--discard').click();

    await expect(page.locator('.nb-datagrid__batch-bar')).toBeHidden({ timeout: 3_000 });
    await expect(page.locator('tr.nb-datagrid__row--editing')).toHaveCount(0);
  });

  test('inline save patches the row and exits edit mode', async ({ page }) => {
    await login(page);
    await goToCustomers(page);

    await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();

    const row = editingRow(page);

    // Change the name field (first textbox in the editing row)
    const nameInput = row.getByRole('textbox').first();
    await nameInput.clear();
    await nameInput.fill('Acme Retail Updated');

    await row.getByTitle('Save row').click();

    // Row should exit edit mode (no more editing class)
    await expect(page.locator('tr.nb-datagrid__row--editing')).toHaveCount(0, { timeout: 10_000 });

    // Updated name should be visible in the grid
    await expect(page.getByRole('row').filter({ hasText: 'Acme Retail Updated' })).toBeVisible();

    // Restore the original name
    await page.getByRole('row').filter({ hasText: 'Acme Retail Updated' }).dblclick();
    const nameInput2 = editingRow(page).getByRole('textbox').first();
    await nameInput2.clear();
    await nameInput2.fill('Acme Retail');
    await editingRow(page).getByTitle('Save row').click();
    await expect(page.getByRole('row').filter({ hasText: 'Acme Retail' })).toBeVisible({ timeout: 10_000 });
  });
});
