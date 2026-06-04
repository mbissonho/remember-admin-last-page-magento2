import { test, expect } from '@playwright/test';
import { Resources } from "./resources";

/**
 * Covers the server-side "stale secret-key resume" fix.
 *
 * Problem it guards against: with two admin tabs open (same browser, shared
 * session cookie), if the session is re-authenticated in tab A, tab B still
 * holds URLs signed with the previous session's secret key. Reloading tab B
 * makes Magento\Backend\App\AbstractAction::_processUrlKeys() bounce it (302) to
 * the dashboard startup page instead of its own page. The first-page-after-login
 * resume only handles the tab that went through the login screen, so tab B was
 * stranded on the dashboard.
 *
 * The fix (Plugin\Backend\ResumeOnSecretKeyBounce, an around plugin on
 * AbstractAction::_processUrlKeys) detects that bounce entirely on the server:
 * the failing request still carries its own route, so the plugin re-mints the
 * secret key for that exact route and redirects there. No dashboard render, no
 * client-side JavaScript, resolved on the first reload.
 *
 * Requires (same scenario the module's CI configures):
 *   bin/magento config:set admin/mbissonho_remember_admin_last_page/active 1
 *   bin/magento module:disable Magento_TwoFactorAuth Magento_AdminAnalytics
 */

const baseURL = Resources.magentoAdmin.baseURL;
const adminUrl = baseURL + '/admin/admin/';
const credentials = Resources.magentoAdmin.user;

async function signIn(page) {
    await page.goto(adminUrl);
    // Generous timeout: the full suite runs files in parallel, so the admin can
    // be slow to render under concurrent load; the 5s default is too tight.
    await expect(page.locator('#username')).toBeVisible({ timeout: 30000 });
    await page.fill('#username', credentials.username);
    await page.fill('#login', credentials.password);
    await page.click('button.action-login');
    await page.waitForURL(/\/admin\/dashboard/, { timeout: 30000 });
}

/**
 * Navigates to the Customers grid through the admin menu so the page is reached
 * with a valid per-route secret key (a keyless direct GET would itself bounce to
 * the dashboard). Returns the resulting keyed grid URL.
 */
async function openCustomersViaMenu(page) {
    await page.locator('#menu-magento-customer-customer').click();
    await page.waitForTimeout(1000);
    await page.locator('.submenu').locator("//span[text()='All Customers']").click();
    await expect(page.locator('h1.page-title')).toHaveText('Customers', { timeout: 30000 });
    return page.url();
}

// Same admin account is reused across tests; serialize to avoid concurrent
// sign-ins of the same user racing each other.
test.describe.configure({ mode: 'serial' });

test.describe('Server-side resume after a stale secret-key bounce', () => {
    test('resumes a second tab bounced to the dashboard back to its own page, on the first reload', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        // Tab A: sign in (establishes session S1, secret keys K1) and grab a
        // properly keyed Customers grid URL.
        const tabA = await context.newPage();
        await signIn(tabA);
        const keyedCustomersUrl = await openCustomersViaMenu(tabA);

        // Tab B: open the SAME keyed URL (shares session S1, so K1 is valid).
        const tabB = await context.newPage();
        await tabB.goto(keyedCustomersUrl);
        await expect(tabB.locator('h1.page-title')).toHaveText('Customers', { timeout: 30000 });

        // Session expires for everyone (cookie dropped) ...
        await context.clearCookies();
        // ... and the admin re-authenticates in tab A. Clearing tab A's storage
        // first keeps its own login landing on the dashboard (its login-page
        // hidden input drives the first-page-after-login resume), isolating this
        // test to tab B. The new session S2 renews the secret keys, so tab B's
        // keyed URL (K1) is now stale.
        await tabA.evaluate(() => sessionStorage.clear());
        await signIn(tabA);

        // Reload tab B with its old (K1) URL. _processUrlKeys() would bounce it
        // to the dashboard; the plugin must instead re-key the request's own
        // route server-side and land it directly on its page. No intermediate
        // dashboard render is involved.
        await tabB.goto(keyedCustomersUrl);

        await expect(tabB.locator('h1.page-title')).toHaveText('Customers', { timeout: 30000 });
        expect(tabB.url()).toContain('/admin/customer/index/index/');
        // It carries a freshly minted (S2) secret key ...
        expect(tabB.url()).toMatch(/\/key\/[a-f0-9]+\/?$/);
        // ... and never settled on the dashboard it would have been bounced to.
        expect(tabB.url()).not.toMatch(/\/admin\/dashboard/);

        await context.close();
    });

    test('does not re-sign a keyless admin URL — keeps the dashboard bounce (CSRF safety)', async ({ browser }) => {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });

        const page = await context.newPage();
        await signIn(page);

        // A logged-in GET to a real admin route but WITHOUT any secret key is the
        // shape a forged cross-site request would take. The plugin only resumes
        // URLs that carried a (stale) key, so this must keep Magento's original
        // bounce to the dashboard rather than being transparently re-signed.
        await page.goto(baseURL + '/admin/customer/index/index/');

        await expect(page.locator('h1.page-title')).toHaveText('Dashboard', { timeout: 30000 });
        expect(page.url()).toMatch(/\/admin\/dashboard/);
        expect(page.url()).not.toContain('/admin/customer/index/index/');

        await context.close();
    });
});
