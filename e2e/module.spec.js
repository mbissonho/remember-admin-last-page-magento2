import { test, expect } from '@playwright/test';
import { Resources } from "./resources";

const adminUrl = Resources.magentoAdmin.baseURL + '/admin/admin/';
const credentials = Resources.magentoAdmin.user

async function login(page) {
    console.log('Logging in as admin...');
    await page.goto(adminUrl);
    await page.fill('#username', credentials.username);
    await page.fill('#login', credentials.password);
    await page.click("button.action-login");
}

test('Session expiration and re-login', async ({ page }) => {
    console.log('Starting admin session...');
    await login(page);
    await page.waitForURL(/.*\/admin\/dashboard\/(index\/key\/[a-zA-Z0-9]+\/)?$/);
    await page.waitForTimeout(3000);

    console.log('Navigating to the customer listing page...');
    await page.locator("//*[@id=\"menu-magento-customer-customer\"]").click();
    await page.waitForTimeout(1000);
    let customersSideMenu = page.locator(".submenu");
    await customersSideMenu.locator("//span[text()='All Customers']").click();

    await expect(page.locator('h1.page-title')).toHaveText('Customers');

    console.log('Waiting for session to expire 65 seconds...');
    await page.waitForTimeout(65000);

    console.log('Attempting to navigate back to the admin dashboard...');
    await page.click('a.logo');

    console.log('Verifying that the session has expired and redirected to the login page...');
    await expect(page).toHaveURL(adminUrl );
    await expect(page.locator('#username')).toBeVisible();

    console.log('Checking session storage for last admin page accessed...');
    const lastAccessed = await page.evaluate(() => {
        return sessionStorage.getItem('mbissonho-last-admin-page-accessed');
    });
    expect(lastAccessed).not.toBeNull();

    console.log('Re-logging in...');
    await login(page);

    console.log('Verifying that the user is redirected to the customer listing page...');
    await expect(page.locator('h1.page-title')).toHaveText('Customers');

    console.log('Test completed successfully.');
});
