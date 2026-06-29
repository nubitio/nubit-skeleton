import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const ADMIN_EMAIL = 'admin@example.com';
const ADMIN_PASSWORD = 'admin1234';
const SCREENSHOT_DIR = path.join(import.meta.dirname, 'screenshots');

type AuditFinding = {
  status: 'pass' | 'warn' | 'fail' | 'idea';
  area: string;
  note: string;
};

const audit: AuditFinding[] = [];

function record(status: AuditFinding['status'], area: string, note: string) {
  audit.push({ status, area, note });
}

async function login(page: Page) {
  await page.goto('/');
  await page.getByPlaceholder('Email').fill(ADMIN_EMAIL);
  await page.getByPlaceholder('Password').fill(ADMIN_PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText('Espresso Machine')).toBeVisible({ timeout: 20_000 });
  record('pass', 'auth', 'Login succeeds and redirects to Products grid with seeded data');
}

async function shot(page: Page, name: string) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
  await page.screenshot({ path: path.join(SCREENSHOT_DIR, `${name}.png`), fullPage: true });
}

async function openRowEdit(page: Page, rowText: string) {
  const row = page.getByRole('row').filter({ hasText: rowText });
  await row.locator('button').last().click();
  await page.getByRole('menuitem', { name: /edit/i }).click();
}

test.describe.configure({ timeout: 60_000 });

test.describe('Nubit admin visual audit', () => {
  test.beforeAll(() => {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
  });

  test.afterAll(() => {
    fs.writeFileSync(
      path.join(SCREENSHOT_DIR, 'audit-report.json'),
      JSON.stringify({ findings: audit, auditedAt: new Date().toISOString() }, null, 2),
    );
  });

  test('golden path: login → CRUD grids → sales master-detail', async ({ page }) => {
    await login(page);
    await shot(page, '01-products-grid');

    await page.goto('/sales/customers');
    await expect(page.getByText('Acme Retail')).toBeVisible();
    await shot(page, '02-customers-grid');

    await page.goto('/sales/orders');
    await expect(page.getByText('SD-0001')).toBeVisible();
    await openRowEdit(page, 'SD-0001');

    const drawer = page.getByRole('dialog', { name: /edit/i });
    await expect(drawer.getByRole('heading', { name: /edit/i })).toBeVisible({ timeout: 10_000 });
    const detailTable = drawer.locator('.nb-form__detail-table-scroll .nb-form__detail-table');
    await expect(detailTable.locator('tbody tr')).toHaveCount(2, { timeout: 15_000 });
    await expect(detailTable.getByRole('spinbutton').nth(0)).toHaveValue('2.00');

    const drawerText = (await drawer.textContent()) ?? '';
    expect(drawerText).not.toContain('[object Object]');
    record('pass', 'sales', 'Edit drawer hides raw collection field and loads line items');

    await expect(drawer.locator('.nb-form__detail-summary-value')).toContainText(/1[,.]029[,.]90/);
    record('pass', 'sales', 'Line items summary shows correct total');

    await shot(page, '05-sales-edit-drawer');

    await drawer.getByRole('button', { name: 'Close', exact: true }).click();
    await expect(drawer.getByRole('heading', { name: /edit/i })).toBeHidden();
  });

  test('auth gate: deep link to /customers shows login', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/customers');
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    record('pass', 'auth', 'Unauthenticated deep links fall back to login');
  });
});
