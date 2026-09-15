import { expect, test, type APIResponse, type Locator, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';

const ADMIN_EMAIL = 'ci@example.com';
const ADMIN_PASSWORD = 'ci-only-password-16';
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

async function expectAccessible(page: Page) {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();

  expect(results.violations).toEqual([]);
}

async function deleteCreatedProduct(page: Page, response: APIResponse) {
  const product = (await response.json()) as { '@id'?: string; id?: number };
  const productPath = product['@id'] ?? `/api/products/${product.id}`;
  expect(productPath).not.toContain('undefined');
  const deleteResponse = await page.request.delete(productPath);
  expect(deleteResponse.status()).toBe(204);
}

async function tabTo(
  page: Page,
  target: Locator,
  control: string,
  limit = 30,
  key: 'Tab' | 'Shift+Tab' = 'Tab',
) {
  for (let index = 0; index < limit; index += 1) {
    await page.keyboard.press(key);
    if (await target.evaluate((element) => element === document.activeElement)) {
      await expect(target).toBeFocused();
      return;
    }
  }

  throw new Error(`Keyboard focus did not reach "${control}" on ${new URL(page.url()).pathname}`);
}

async function expectVisibleFocus(
  page: Page,
  target: Locator,
  control: string,
  key: 'Tab' | 'Shift+Tab' = 'Tab',
) {
  const unfocusedStyle = await target.evaluate((element) => {
    const style = getComputedStyle(element);
    return `${style.outlineStyle}|${style.outlineWidth}|${style.outlineColor}|${style.boxShadow}`;
  });

  await tabTo(page, target, control, 30, key);
  await expect(target).toBeFocused();
  const focusedStyle = await target.evaluate((element) => {
    const style = getComputedStyle(element);
    return {
      serialized: `${style.outlineStyle}|${style.outlineWidth}|${style.outlineColor}|${style.boxShadow}`,
      visible:
        (style.outlineStyle !== 'none' &&
          Number.parseFloat(style.outlineWidth) > 0 &&
          style.outlineColor !== 'transparent' &&
          style.outlineColor !== 'rgba(0, 0, 0, 0)') ||
        style.boxShadow !== 'none',
    };
  });
  expect(focusedStyle.serialized, `${control} focus style must change`).not.toBe(unfocusedStyle);
  expect(focusedStyle.visible, `${control} must have a visible focus indicator`).toBe(true);
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

  test('login, navigation, grid, and form have no automated accessibility violations', async ({
    page,
  }) => {
    await page.goto('/');
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    await expectAccessible(page);

    await login(page);
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
    await expectAccessible(page);

    await page.getByRole('button', { name: /new/i }).click();
    await expect(page.getByLabel('Name', { exact: true })).toBeVisible();
    await expectAccessible(page);

    await page.getByLabel('Name', { exact: true }).fill('Invalid Product');
    await page.getByLabel('Price', { exact: true }).fill('-1');
    await page.getByRole('button', { name: /save/i }).click();
    await expect(page.locator('.nb-form__error').first()).toBeVisible();
    await expectAccessible(page);

    await page.getByLabel('Name', { exact: true }).fill(`Accessible Product ${Date.now()}`);
    await page.getByLabel('Sku', { exact: true }).fill('ACCESSIBLE-1');
    await page.getByLabel('Price', { exact: true }).fill('12.34');
    await expectAccessible(page);
    const createResponse = page.waitForResponse(
      (response) => response.request().method() === 'POST' && response.url().includes('/api/products'),
    );
    await page.getByRole('button', { name: /save/i }).click();
    const response = await createResponse;
    await expect(page.getByLabel('Name', { exact: true })).not.toBeVisible();
    await expectAccessible(page);
    await deleteCreatedProduct(page, response);
  });

  test('the primary CRUD flow is keyboard operable with visible focus', async ({ page }) => {
    await page.goto('/');

    const email = page.getByPlaceholder('Email');
    await expectVisibleFocus(page, email, 'Email');
    await page.keyboard.type(ADMIN_EMAIL);

    const password = page.getByPlaceholder('Password');
    await tabTo(page, password, 'Password');
    await page.keyboard.type(ADMIN_PASSWORD);

    const signIn = page.getByRole('button', { name: 'Sign in' });
    await expectVisibleFocus(page, signIn, 'Sign in');
    await page.keyboard.press('Enter');
    await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });

    const filter = page.locator('table thead input').first();
    await expectVisibleFocus(page, filter, 'Filter Name');
    await page.keyboard.type('Espresso');
    await expect(page.getByText('Coffee Grinder')).toBeHidden({ timeout: 10_000 });
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.press('Backspace');
    await expect(page.getByText('Coffee Grinder')).toBeVisible({ timeout: 10_000 });

    const create = page.getByRole('button', { name: /new/i });
    await expectVisibleFocus(page, create, 'New', 'Shift+Tab');
    await page.keyboard.press('Enter');

    const name = `Keyboard Product ${Date.now()}`;
    const nameInput = page.getByLabel('Name', { exact: true });
    await tabTo(page, nameInput, 'Name');
    await page.keyboard.type(name);
    await tabTo(page, page.getByLabel('Sku', { exact: true }), 'Sku');
    await page.keyboard.type('KEYBOARD-1');
    await tabTo(page, page.getByLabel('Price', { exact: true }), 'Price');
    await page.keyboard.type('12.34');

    const save = page.getByRole('button', { name: /save/i });
    await expectVisibleFocus(page, save, 'Save');
    const createResponse = page.waitForResponse(
      (response) => response.request().method() === 'POST' && response.url().includes('/api/products'),
    );
    await page.keyboard.press('Enter');
    const response = await createResponse;
    expect(response.ok()).toBe(true);
    await deleteCreatedProduct(page, response);
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
    const createResponse = page.waitForResponse(
      (response) => response.request().method() === 'POST' && response.url().includes('/api/products'),
    );
    await page.getByRole('button', { name: /save/i }).click();
    const response = await createResponse;
    expect(response.ok()).toBe(true);
    await deleteCreatedProduct(page, response);
  });

  test('a deep link while signed out shows the login screen', async ({ page }) => {
    await page.goto('/products');

    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 15_000 });
  });

  /**
   * The one authentication failure path nothing else here exercises: every
   * other test in this file logs in with a credential CI actually seeded.
   * Wrong credentials must fail closed — no grid, no session — not merely
   * "eventually" reject on some later request.
   */
  test('signing in with the wrong password does not reach the grid', async ({ page }) => {
    await page.goto('/');
    await page.getByPlaceholder('Email').fill(ADMIN_EMAIL);
    await page.getByPlaceholder('Password').fill(`not-${ADMIN_PASSWORD}`);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('Espresso Machine')).not.toBeVisible();
    // Still on the login screen, not silently redirected anywhere else.
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 10_000 });
  });

  test('logout, session expiry, and re-authentication fail closed', async ({ page, request }) => {
    await login(page);
    const authenticatedCookies = await page.context().cookies();
    const refreshCookie = authenticatedCookies.find(({ name }) => name === 'REFRESH_TOKEN');
    expect(refreshCookie).toBeDefined();

    await page.getByRole('button', { name: 'User menu' }).click();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 10_000 });
    await expect(page.getByText('Espresso Machine')).not.toBeVisible();
    const refreshAfterLogout = await request.post('/api/auth/refresh', {
      headers: { cookie: `REFRESH_TOKEN=${refreshCookie?.value}` },
    });
    expect(refreshAfterLogout.status()).toBe(401);

    await login(page);
    await page.waitForTimeout(15_500);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 10_000 });
    await expect(page.getByText('Espresso Machine')).not.toBeVisible();

    await page.getByPlaceholder('Email').fill(ADMIN_EMAIL);
    await page.getByPlaceholder('Password').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });
  });
});
