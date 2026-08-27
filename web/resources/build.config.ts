// web/resources/build.config.ts
import { mkdir } from "node:fs/promises";
import { watch } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import * as sass from "sass";

const scriptDir = dirname(fileURLToPath(import.meta.url));
const tsDir = join(scriptDir, "ts");
const scssEntry = join(scriptDir, "scss", "index.scss");
const outDir = join(scriptDir, "..", "public", "assets");

const isProd = !process.argv.includes("--dev");
const isWatch = process.argv.includes("--watch");

await mkdir(outDir, { recursive: true });

async function buildTs(): Promise<void> {
   const result = await Bun.build({
      entrypoints: [join(tsDir, "index.ts")],
      outdir: outDir,
      naming: "index.js",
      target: "browser",
      minify: isProd,
      sourcemap: isProd ? "external" : "inline",
   });

   if (!result.success) {
      console.error("TS build failed:");
      for (const message of result.logs) console.error(message);
      if (!isWatch) process.exit(1);
      return;
   }
   for (const output of result.outputs) console.log(`Built: ${output.path}`);
}

async function buildScss(): Promise<void> {
   try {
      const result = await sass.compileAsync(scssEntry, {
         style: isProd ? "compressed" : "expanded",
         sourceMap: !isProd,
      });

      const outPath = join(outDir, "index.css");
      await Bun.write(outPath, result.css);
      if (!isProd && result.sourceMap) {
         await Bun.write(`${outPath}.map`, JSON.stringify(result.sourceMap));
      }
      console.log(`Built: ${outPath}`);
   } catch (error) {
      console.error("SCSS build failed:", error);
      if (!isWatch) process.exit(1);
   }
}

await buildTs();
await buildScss();

if (isWatch) {
   console.log("Watching for changes...");
   watch(tsDir, { recursive: true }, () => buildTs());
   watch(join(scriptDir, "scss"), { recursive: true }, () => buildScss());
}
