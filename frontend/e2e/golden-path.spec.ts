import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const ADMIN_EMAIL = 'admin@example.com';
const ADMIN_PASSWORD = 'admin1234';
const SCREENSHOT_DIR = path.join(import.meta.dirname, 'screenshots');

/**
 * The template's one critical path: sign in, and get a working CRUD screen that
 * nobody wrote frontend field code for. Everything here is generated from the
 * `x-crud` hints on App\Entity\Product, so a break in this spec means the
 * schema pipeline — not a page — regressed.
 *
 * Features this template ships with off (master-detail, audit trails, inline
 * editing, export) are covered in nubit-react's own example app, not here.
 */

async function login(page: Page) {
  await page.goto('/');
  await page.getByPlaceholder('Email').fill(ADMIN_EMAIL);
  await page.getByPlaceholder('Password').fill(ADMIN_PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });
}

async function shot(page: Page, name: string) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
  await page.screenshot({ path: path.join(SCREENSHOT_DIR, `${name}.png`), fullPage: true });
}

test.describe.configure({ timeout: 60_000 });

test.describe('Golden path', () => {
  test('login lands on a grid generated from the API docs', async ({ page }) => {
    await login(page);

    // Columns come from x-crud hints, in their declared `order`.
    const headers = page.locator('table thead th');
    await expect(headers.filter({ hasText: 'Name' })).toBeVisible();
    await expect(headers.filter({ hasText: 'SKU' })).toBeVisible();
    await expect(headers.filter({ hasText: 'Price' })).toBeVisible();

    await expect(page.locator('tr.nb-datagrid__row')).not.toHaveCount(0);
    await shot(page, 'products-grid');
  });

  test('the filter row narrows the result set through the API', async ({ page }) => {
    await login(page);

    const filter = page.locator('table thead input').first();
    await filter.fill('Espresso');
    await expect(page.getByText('Coffee Grinder')).toBeHidden({ timeout: 10_000 });
    await expect(page.getByText('Espresso Machine')).toBeVisible();

    await filter.clear();
    await expect(page.getByText('Coffee Grinder')).toBeVisible({ timeout: 10_000 });
  });

  test('the create form is generated too, and round-trips a new row', async ({ page }) => {
    await login(page);
    const name = `E2E Product ${Date.now()}`;

    await page.getByRole('button', { name: /new/i }).click();
    // Exact match: the filter row exposes "Filter Name" etc. as aria-labels.
    await page.getByLabel('Name', { exact: true }).fill(name);
    await page.getByLabel('Sku', { exact: true }).fill('E2E-1');
    await page.getByLabel('Price', { exact: true }).fill('12.34');
    await shot(page, 'products-form');
    await page.getByRole('button', { name: /save/i }).click();

    await expect(page.getByText(name)).toBeVisible({ timeout: 15_000 });
  });

  test('a deep link while signed out shows the login screen', async ({ page }) => {
    await page.goto('/products');

    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 15_000 });
  });
});
