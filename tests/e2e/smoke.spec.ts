import { expect, test } from "playwright/test";

test("home page responds", async ({ page }) => {
   const response = await page.goto(`${process.env.BASE_URL}`);
   expect(response?.ok()).toBeTruthy();
});
