/// <reference types="bun" />

// Builds web/resources into web/public/assets/ and writes web/build/assets.json,
// which PHP's Assets reads to turn "css/staff.css" into "/assets/staff-x4j2av4s.css".
//
//   bun run web/resources/build.config.ts                  production
//   bun run web/resources/build.config.ts --dev            development (readable, source maps)
//   bun run web/resources/build.config.ts --dev --watch    rebuild on every change
//
// Rule: CSS must not url() a file in static/. Every static/ file is an entry
// point, and Bun crashes when CSS references an entry point.
// Put images and fonts that CSS needs under css/ instead. static-guard
// turns the crash into a readable error.

import type { BunPlugin } from 'bun';
import { mkdir, readdir, rename, rm } from 'node:fs/promises';
import { existsSync, watch } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import tailwindcss from 'tailwindcss-bun-plugin';
import { stat } from 'node:fs/promises';

const entries = ['staff', 'customer'] as const;

const srcdir = import.meta.dir;                          // web/resources
const outdir = join(srcdir, '../public/assets');         // served by Caddy at /assets/
const manifestPath = join(srcdir, 'build/assets.json');  // read by PHP, never served

/** Entry points, relative to srcdir. Also the manifest keys for JS and CSS. */
const entrypoints = new Set<string>([
   ...entries.map(e => `css/${e}.css`),
   ...entries.map(e => `ts/${e}.ts`),
]);

const isdev = process.argv.includes('--dev');
const iswatch = process.argv.includes('--watch');

/** Source path relative to root: "web/resources/static/img/a.png" → "static/img/a.png". */
const fromRoot = (path: string): string => relative(srcdir, resolve(path));

async function listFiles(dir: string): Promise<string[]> {
   const dirents = await readdir(dir, { recursive: true, withFileTypes: true }).catch(() => []);
   return dirents
      .filter(d => d.isFile())
      .map(d => join(d.parentPath, d.name));
}

/** Fails the build with a clear message instead of letting Bun crash (see the rule above). */
const staticGuard: BunPlugin = {
   name: 'static-guard',
   setup(build) {
      build.onResolve({ filter: /static\// }, args => {
         if (args.importer.endsWith('.css'))
            throw new Error(`${fromRoot(args.importer)} references ${args.path}: CSS must not url() files in static/, put it under css/ instead`);
         return undefined;
      });
   },
};

/**
 * Writes the manifest: source path (relative to srcdir) → file under /assets/.
 *
 *   "ts/staff.ts"         → "staff-dx01rmp8.js"
 *   "css/staff.css"       → "staff-533e9wa2.css"
 *   "static/img/logo.png" → "static/img/logo-7zjyd5xx.png"
 *
 * Also deletes the JS wrapper Bun emits for every static entry point
 * (static/img/logo.png becomes logo.png AND a logo-*.js exporting its URL).
 */
const manifestPlugin: BunPlugin = {
   name: 'manifest',
   setup(build) {
      build.onEnd(async result => {
         if (!result.success || !result.metafile)
            return;

         const manifest: Record<string, string> = {};

         for (const [file, output] of Object.entries(result.metafile.outputs)) {
            if (!output.entryPoint)
               continue;                                      // shared chunk

            const source = fromRoot(output.entryPoint);

            if (source.startsWith('static/')) {
               await rm(join(outdir, file), { force: true });          // the wrapper
               await rm(join(outdir, `${file}.map`), { force: true }); // its source map, in dev
               continue;
            }

            if (entrypoints.has(source))                   // skips lazy import() modules like ts/staff/board.ts
               manifest[source] = file.replace(/^\.\//, '');
         }

         // Static files keep their folder ([dir] in asset naming), so removing
         // the hash from the output path gives back the source path.
         for (const output of result.outputs) {
            const file = relative(outdir, output.path);
            if (output.kind === 'asset' && file.startsWith('static/') && output.hash)
               manifest[file.replace(`-${output.hash}`, '')] = file;
         }

         // Write then rename: PHP never reads a half-written file
         await mkdir(dirname(manifestPath), { recursive: true });
         await Bun.write(`${manifestPath}.tmp`, JSON.stringify(manifest, null, 2));
         await rename(`${manifestPath}.tmp`, manifestPath);
      });
   },
};

/** Removes files this build didn't produce, once they're older than `keepMs`. */
async function prune(produced: Set<string>, keepMs: number): Promise<void> {
   for (const file of await listFiles(outdir)) {
      if (produced.has(file))
         continue;
      const { mtimeMs } = await stat(file);
      if (Date.now() - mtimeMs > keepMs)
         await rm(file, { force: true });
   }
}

async function build(): Promise<boolean> {
   const started = performance.now();

   await mkdir(outdir, { recursive: true });
   const result = await Bun.build({
      root: srcdir,
      entrypoints: [
         ...[...entrypoints].map(e => join(srcdir, e)),
         ...await listFiles(join(srcdir, 'static')),
      ],
      outdir,
      target: 'browser',
      format: 'esm',
      splitting: !isdev,
      minify: !isdev,
      naming: {
         entry: '[name]-[hash].[ext]',
         chunk: 'chunks/[hash].[ext]',            // a shared chunk's name is just whichever entry Bun saw first
         asset: '[dir]/[name]-[hash].[ext]',      // keeps static/img/… so the manifest can map it back
      },
      sourcemap: isdev ? 'external' : 'none',
      define: {
         __DEV__: isdev ? 'true' : 'false',
      },
      loader: {
         '.png': 'file',
         '.jpg': 'file',
         '.jpeg': 'file',
         '.webp': 'file',
         '.gif': 'file',
         '.svg': 'file',
         '.ico': 'file',
         '.woff2': 'file',
         '.txt': 'text',
      },
      publicPath: '/assets/',
      metafile: true,
      plugins: [tailwindcss, staticGuard, manifestPlugin],
      drop: isdev ? [] : ['console', 'debugger'],
      packages: 'bundle',
      throw: false,
   });

   if (!result.success) {
      for (const log of result.logs)
         console.error(log);
      return false;
   }

   const produced = new Set(result.outputs.map(o => o.path));
   await prune(produced, isdev ? 60_000 : 0);

   console.log(`Built in ${Math.round(performance.now() - started)} ms`);
   return true;
}

const ok = await build();

if (!iswatch) {
   process.exit(ok ? 0 : 1);
}

// --- Watch -------------------------------------------------------------------
//
// Bun.build has no watch mode, so this watches the sources itself.
// views/ is watched too: Tailwind scans it, so a new class in a view
// needs a CSS rebuild even though no CSS file changed.
//
// Saving several files at once (or one editor save firing several events)
// becomes one rebuild: changes are collected for a short moment first.
// A change that arrives mid-build triggers exactly one more build after it.

let building = false;
let pending = false;
let timer: Timer | undefined;

async function rebuild(): Promise<void> {
   if (building) {
      pending = true;
      return;
   }

   building = true;
   do {
      pending = false;
      await build();
   } while (pending);
   building = false;
}

function schedule(): void {
   clearTimeout(timer);
   timer = setTimeout(() => void rebuild(), 50);
}

for (const dir of ['css', 'ts', 'static', 'views']) {
   if (existsSync(join(srcdir, dir)))
      watch(join(srcdir, dir), { recursive: true }, schedule);
}

console.log('Watching css/, ts/, static/ and views/ for changes…');
