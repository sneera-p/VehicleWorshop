import { defineConfig } from "playwright/test";

export default defineConfig({
   testDir: ".",              // tests are IN this same folder — not "../tests/e2e", not "./tests/e2e"
   fullyParallel: true,
   retries: process.env.CI ? 2 : 0,
   reporter: "list",
   use: {
      baseURL: process.env.BASE_URL ?? "http://localhost",
      trace: "on-first-retry",
   },
});
