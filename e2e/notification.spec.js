import { test, expect } from '@playwright/test';
import { Resources } from "./resources";

/**
 * Covers the "last admin page accessed" notification feature added on the
 * feature/last-admin-page-accessed-notification branch (Controller IsLoggedIn,
 * last-page-notification-manager.js, the login layout/template and the
 * notification messages config).
 *
 * Scenario: a tab is left on the admin login page (signed out) but still holds a
 * page in its session storage. While it polls, the admin signs in on another tab
 * of the same browser (so the admin session cookie is shared). The polling tab
 * must then surface a "Go to the page" toast link that points at the exact route
 * stored in THAT tab's session storage, carrying a valid admin secret key.
 *
 * Requires (set the same way the module's CI configures the env):
 *   bin/magento config:set admin/mbissonho_remember_admin_last_page/active 1
 *   bin/magento config:set admin/mbissonho_remember_admin_last_page/active_notification_message 1
 *   bin/magento module:disable Magento_TwoFactorAuth Magento_AdminAnalytics
 */

const baseURL = Resources.magentoAdmin.baseURL;
const adminUrl = baseURL + '/admin/admin/';
const credentials = Resources.magentoAdmin.user;

const STORAGE_KEY = 'mbissonho-last-admin-page-accessed';

async function signIn(page) {
    await page.goto(adminUrl);
    await expect(page.locator('#username')).toBeVisible();
    await page.fill('#username', credentials.username);
    await page.fill('#login', credentials.password);
    await page.click('button.action-login');
}

/**
 * Opens a signed-out login tab, seeds its session storage with `storedPage`, and
 * reloads so the notification widget initializes from it. Returns the page.
 */
async function openPollingTabWithStoredPage(context, storedPage) {
    const pollingTab = await context.newPage();
    await pollingTab.goto(adminUrl);
    await expect(pollingTab.locator('#username')).toBeVisible();

    await pollingTab.evaluate(([key, value]) => {
        sessionStorage.setItem(key, value);
    }, [STORAGE_KEY, JSON.stringify(storedPage)]);

    await pollingTab.reload();
    await expect(pollingTab.locator('#username')).toBeVisible();

    // The "has saved page" toast shows while still signed out, with no link yet.
    await expect(pollingTab.locator('#toast-container .toast')).toBeVisible();
    await expect(pollingTab.locator('#toast-container .toast a')).toHaveCount(0);

    return pollingTab;
}

test.describe('Last accessed page notification', () => {
    test('renders a "Go to the page" link to a grid route once the admin signs in on another tab', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        const storedPage = {
            route_path: 'customer/index/index',
            edit_details: { url_entity_param_name: 'entity_id', url_entity_param_value: 0 }
        };

        const pollingTab = await openPollingTabWithStoredPage(context, storedPage);

        // Sign in on a second tab of the SAME context (shares the session cookie).
        const loginTab = await context.newPage();
        await signIn(loginTab);
        // Confirm the second tab is authenticated (lands on the dashboard).
        await loginTab.waitForURL(/\/admin\/dashboard/, { timeout: 30000 });

        // Back on the polling tab: polling (every 5s) detects the session and
        // swaps the message toast for a link toast.
        const link = pollingTab.locator('#toast-container .toast a');
        await expect(link).toBeVisible({ timeout: 20000 });
        await expect(link).toHaveText('Go to the page');

        const href = await link.getAttribute('href');
        expect(href).toContain('/admin/customer/index/index/');
        // A server-minted per-route admin secret key must be present.
        expect(href).toMatch(/\/key\/[a-f0-9]+\/?$/);

        // The link works: clicking it lands on the authenticated Customers grid.
        await link.click();
        await expect(pollingTab.locator('h1.page-title')).toHaveText('Customers');

        await context.close();
    });

    test('link reflects the exact stored route, including its entity parameter', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        // An edit-style page with an entity id, different from the grid case.
        const storedPage = {
            route_path: 'sales/order/view',
            edit_details: { url_entity_param_name: 'order_id', url_entity_param_value: 1 }
        };

        const pollingTab = await openPollingTabWithStoredPage(context, storedPage);

        const loginTab = await context.newPage();
        await signIn(loginTab);
        // Confirm the second tab is authenticated (lands on the dashboard).
        await loginTab.waitForURL(/\/admin\/dashboard/, { timeout: 40000 });

        const link = pollingTab.locator('#toast-container .toast a');
        await expect(link).toBeVisible({ timeout: 40000 });

        const href = await link.getAttribute('href');
        // The href must mirror the stored route AND its entity parameter, proving
        // it is built from this tab's session storage rather than a fixed route.
        expect(href).toContain('/admin/sales/order/view/order_id/1/');
        expect(href).toMatch(/\/key\/[a-f0-9]+\/?$/);

        await context.close();
    });

    test('does not render the link while signed out (no false positives)', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        const storedPage = {
            route_path: 'customer/index/index',
            edit_details: { url_entity_param_name: 'entity_id', url_entity_param_value: 0 }
        };

        const pollingTab = await openPollingTabWithStoredPage(context, storedPage);

        // Without anyone signing in, the polling endpoint keeps reporting
        // logged_in:false, so the link must never appear.
        await pollingTab.waitForTimeout(7000);
        await expect(pollingTab.locator('#toast-container .toast a')).toHaveCount(0);

        await context.close();
    });
});
