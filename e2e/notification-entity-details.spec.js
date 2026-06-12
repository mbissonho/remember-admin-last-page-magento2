import { test, expect } from '@playwright/test';
import { Resources } from "./resources";

/**
 * Covers the "entity details" hint of the last-accessed-page notification
 * (the opt-in `active_entity_details` feature). Once the polling login tab
 * detects that the admin signed in on another tab, it asks the EntityPreview
 * controller for the masked, display-ready details of the remembered record and
 * renders them in a dedicated `.toast-entity-details` toast.
 *
 * What is real here vs. stubbed:
 *   - REAL: the signed-out polling tab, its session storage, the 5s poll to
 *     IsLoggedIn, the second-tab sign-in that shares the session cookie, the
 *     widget wiring and the toast rendering in last-page-notification-manager.js.
 *   - STUBBED: only the EntityPreview JSON response (via page.route). The token
 *     is server-minted and the resolved fields depend on seed data; stubbing the
 *     response keeps the test deterministic and focused on the display contract:
 *     the server returns an already-localised label + masked fields, and the JS
 *     renders them verbatim (no client-side re-translation).
 *
 * Requires (same scenario the module's CI configures), on top of notification.spec:
 *   bin/magento config:set admin/mbissonho_remember_admin_last_page/active_entity_details 1
 */

const baseURL = Resources.magentoAdmin.baseURL;
const adminUrl = baseURL + '/admin/admin/';
const credentials = Resources.magentoAdmin.user;

const STORAGE_KEY = 'mbissonho-last-admin-page-accessed';

async function signIn(page) {
    await page.goto(adminUrl);
    await expect(page.locator('#username')).toBeVisible({ timeout: 30000 });
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

/**
 * Intercepts the EntityPreview AJAX on `page` and answers it with `body`. Set
 * before the poll detects the session, so the very first preview request is
 * served by the stub rather than the real controller.
 */
async function stubEntityPreview(page, body) {
    await page.route(/\/mbissonho_ralp\/index\/entitypreview\b/, async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(body),
        });
    });
}

// A remembered order page carrying a (here, stubbed) sealed entity reference.
const storedOrderPage = {
    route_path: 'sales/order/view',
    edit_details: { url_entity_param_name: 'order_id', url_entity_param_value: 5 },
    entity_token: 'stub-entity-token'
};

test.describe('Last accessed page notification — entity details', () => {
    test('renders the label and masked fields returned by EntityPreview once signed in elsewhere', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        const pollingTab = await openPollingTabWithStoredPage(context, storedOrderPage);
        await stubEntityPreview(pollingTab, {
            details: {
                label: 'Order',
                fields: [
                    { label: 'Order', value: '#000000005' },
                    { label: 'Customer', value: 'J•hn D•e' },
                    { label: 'Email', value: 'j•hn@e•••••.com' }
                ]
            }
        });

        // Sign in on a second tab of the SAME context (shares the session cookie).
        const loginTab = await context.newPage();
        await signIn(loginTab);
        await loginTab.waitForURL(/\/admin\/dashboard/, { timeout: 30000 });

        // The dedicated entity-details toast appears alongside the resume link.
        const details = pollingTab.locator('#toast-container .toast-entity-details');
        await expect(details).toBeVisible({ timeout: 20000 });

        // Label is rendered exactly as the server sent it (no client-side $t).
        await expect(details.locator('.toast-entity-label')).toHaveText('Order');

        // Each masked field is rendered as "Label: value".
        const fieldLabels = details.locator('.toast-entity-field-label');
        await expect(fieldLabels).toHaveCount(3);
        await expect(fieldLabels.nth(0)).toHaveText('Order: ');
        await expect(fieldLabels.nth(1)).toHaveText('Customer: ');
        await expect(fieldLabels.nth(2)).toHaveText('Email: ');

        const values = details.locator('.toast-entity-field-value');
        await expect(values.nth(0)).toHaveText('#000000005');
        await expect(values.nth(1)).toHaveText('J•hn D•e');
        await expect(values.nth(2)).toHaveText('j•hn@e•••••.com');

        await context.close();
    });

    test('renders a server-localised label verbatim (the JS no longer re-translates)', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        const pollingTab = await openPollingTabWithStoredPage(context, storedOrderPage);
        // EntityPreview runs as an authenticated adminhtml request, so it returns
        // the label already in the restoring user's interface locale (e.g. pt_BR).
        // The login page itself is in another locale, so the only correct behaviour
        // is to print the server label as-is — which is what this asserts.
        await stubEntityPreview(pollingTab, {
            details: {
                label: 'Pedido',
                fields: [
                    { label: 'Cliente', value: 'M•ria' }
                ]
            }
        });

        const loginTab = await context.newPage();
        await signIn(loginTab);
        await loginTab.waitForURL(/\/admin\/dashboard/, { timeout: 30000 });

        const details = pollingTab.locator('#toast-container .toast-entity-details');
        await expect(details).toBeVisible({ timeout: 20000 });
        await expect(details.locator('.toast-entity-label')).toHaveText('Pedido');
        await expect(details.locator('.toast-entity-field-label')).toHaveText('Cliente: ');
        await expect(details.locator('.toast-entity-field-value')).toHaveText('M•ria');

        await context.close();
    });

    test('shows no entity-details toast when the preview returns no details', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        const pollingTab = await openPollingTabWithStoredPage(context, storedOrderPage);
        // Any gate failing server-side (auth, ACL, token, disabled) yields this.
        await stubEntityPreview(pollingTab, { details: null });

        const loginTab = await context.newPage();
        await signIn(loginTab);
        await loginTab.waitForURL(/\/admin\/dashboard/, { timeout: 30000 });

        // The resume link still appears (login was detected) ...
        await expect(pollingTab.locator('#toast-container .toast a')).toBeVisible({ timeout: 20000 });
        // ... but no entity-details toast is rendered for a null payload.
        await expect(pollingTab.locator('#toast-container .toast-entity-details')).toHaveCount(0);

        await context.close();
    });
});
