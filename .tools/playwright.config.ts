import { defineConfig } from "playwright/test";

export default defineConfig({
   testDir: "../tests/e2e",
   fullyParallel: true,
   retries: process.env.CI ? 2 : 0,
   reporter: "list",
   use: {
      baseURL: process.env.BASE_URL ?? "http://localhost",
      trace: "on-first-retry",
   },
});
