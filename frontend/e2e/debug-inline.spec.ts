import { test } from '@playwright/test';

test('debug: check DOM after dblclick', async ({ page }) => {
  await page.goto('/');
  await page.getByPlaceholder('Email').fill('admin@example.com');
  await page.getByPlaceholder('Password').fill('admin1234');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.getByText('Espresso Machine').waitFor({ timeout: 20_000 });

  await page.goto('/sales/customers');
  await page.getByText('Acme Retail').waitFor({ timeout: 10_000 });

  // Dump row classes before dblclick
  const rowClass = await page.getByRole('row').filter({ hasText: 'Acme Retail' }).getAttribute('class');
  console.log('Row class before dblclick:', rowClass);

  // Check editMode on the grid element
  const editModeAttr = await page.locator('.nb-datagrid').getAttribute('data-edit-mode');
  console.log('Grid data-edit-mode attr:', editModeAttr);

  // Check if the batch-bar CSS class even exists in the page
  const batchBarExists = await page.locator('.nb-datagrid__batch-bar').count();
  console.log('nb-datagrid__batch-bar count (before dblclick):', batchBarExists);

  // Check for nb-inline-btn in the DOM (would confirm inline edit code is active)
  const inlineBtnCount = await page.locator('.nb-datagrid__inline-btn').count();
  console.log('nb-datagrid__inline-btn count:', inlineBtnCount);

  // Dblclick
  await page.getByRole('row').filter({ hasText: 'Acme Retail' }).dblclick();
  await page.waitForTimeout(1000);

  // Check row class after
  const rowClassAfter = await page.getByRole('row').filter({ hasText: 'Acme Retail' }).getAttribute('class');
  console.log('Row class after dblclick:', rowClassAfter);

  // Check for textboxes in the row
  const textboxCount = await page.getByRole('row').filter({ hasText: 'Acme Retail' }).getByRole('textbox').count();
  console.log('Textbox count after dblclick:', textboxCount);

  // Check for batch bar
  const batchBarAfter = await page.locator('.nb-datagrid__batch-bar').count();
  console.log('nb-datagrid__batch-bar count (after dblclick):', batchBarAfter);

  // Dump all console errors
  const jsErrors: string[] = [];
  page.on('console', msg => {
    if (msg.type() === 'error') jsErrors.push(msg.text());
  });

  await page.screenshot({ path: 'e2e/screenshots/debug-after-dblclick.png' });

  console.log('JS errors:', JSON.stringify(jsErrors));

  // Check the full outer HTML of the row
  const rowHtml = await page.getByRole('row').filter({ hasText: 'Acme Retail' }).innerHTML();
  console.log('Row innerHTML (first 500):', rowHtml.slice(0, 500));
});
