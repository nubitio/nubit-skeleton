import { expect, test, type Page } from '@playwright/test';
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

async function tabTo(page: Page, target: ReturnType<Page['locator']>, limit = 30) {
  for (let index = 0; index < limit; index += 1) {
    await page.keyboard.press('Tab');
    if (await target.evaluate((element) => element === document.activeElement)) {
      await expect(target).toBeFocused();
      return;
    }
  }

  throw new Error(`Keyboard focus did not reach ${await target.getAttribute('aria-label') ?? 'target'}`);
}

async function expectVisibleFocus(target: ReturnType<Page['locator']>) {
  await expect(target).toBeFocused();
  const hasVisibleFocus = await target.evaluate((element) => {
    const style = getComputedStyle(element);
    return style.outlineStyle !== 'none' || style.boxShadow !== 'none';
  });
  expect(hasVisibleFocus).toBe(true);
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
  });

  test('the primary CRUD flow is keyboard operable with visible focus', async ({ page }) => {
    await page.goto('/');

    const email = page.getByPlaceholder('Email');
    await tabTo(page, email);
    await expectVisibleFocus(email);
    await page.keyboard.type(ADMIN_EMAIL);

    const password = page.getByPlaceholder('Password');
    await tabTo(page, password);
    await page.keyboard.type(ADMIN_PASSWORD);

    const signIn = page.getByRole('button', { name: 'Sign in' });
    await tabTo(page, signIn);
    await expectVisibleFocus(signIn);
    await page.keyboard.press('Enter');
    await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });

    const create = page.getByRole('button', { name: /new/i });
    await tabTo(page, create);
    await expectVisibleFocus(create);
    await page.keyboard.press('Enter');

    const name = `Keyboard Product ${Date.now()}`;
    const nameInput = page.getByLabel('Name', { exact: true });
    await tabTo(page, nameInput);
    await page.keyboard.type(name);
    await tabTo(page, page.getByLabel('Sku', { exact: true }));
    await page.keyboard.type('KEYBOARD-1');
    await tabTo(page, page.getByLabel('Price', { exact: true }));
    await page.keyboard.type('12.34');

    const save = page.getByRole('button', { name: /save/i });
    await tabTo(page, save);
    await expectVisibleFocus(save);
    await page.keyboard.press('Enter');
    await expect(page.getByText(name)).toBeVisible({ timeout: 15_000 });
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

  test('logout, session expiry, and re-authentication fail closed', async ({ page, context }) => {
    await login(page);

    await page.getByRole('button', { name: 'User menu' }).click();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 10_000 });
    await expect(page.getByText('Espresso Machine')).not.toBeVisible();

    await login(page);
    await context.clearCookies();
    await page.reload();
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 10_000 });
    await expect(page.getByText('Espresso Machine')).not.toBeVisible();

    await page.getByPlaceholder('Email').fill(ADMIN_EMAIL);
    await page.getByPlaceholder('Password').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });
  });
});
