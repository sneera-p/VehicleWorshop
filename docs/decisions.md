# Decisions

What we chose, why, and what it costs us. When the team settles something new, add an entry at the bottom. When a decision changes, don't edit the old entry: add a new one that says what it replaces.

---

## No PHP framework

**Why:** vwork is a learning exercise. Writing the router, pipeline and HTTP layer ourselves is the point.
**Trade-off:** we maintain code a framework would give us for free, and new teammates can't lean on framework docs.

## FrankenPHP in worker mode

**Why:** the app boots once and stays in memory, so requests skip the start-up cost. It also holds SSE connections well.
**Trade-off:** state can leak between requests if code stores things in static variables. Anything request-specific must stay request-scoped.

## Mutable `Response` instead of PSR-7

**Why:** `addHeader()`, `rmHeader()` and friends change the response in place, which is simpler to read and write than PSR-7's `withX()` copies.
**Trade-off:** our HTTP classes don't plug into PSR-7 libraries.

## Adopt a PSR only when it pays off

**Why:** standards are worth it when they buy real interop. PSR-3 (logging) does, because Monolog works with it. The others didn't.
**Trade-off:** fewer drop-in third-party packages.

## Slow work goes to the worker

**Why:** sending emails and SMS can take seconds. web hands the job over through Valkey and answers the user immediately.
**Trade-off:** a second process to run and monitor, and a job can fail after the user has already seen "done".

## One live connection per page

**Why:** each page opens a single SSE connection, and different updates travel over it as named events. We started with five separate connections.
**Trade-off:** one stream carries everything, so its handler has to route events carefully.

## JSON by default, CBOR on request

**Why:** `payload()` picks JSON or CBOR from the `Accept` header. Browsers parse JSON natively, so it's the default; CBOR is there for clients that ask for it.
**Trade-off:** negotiation logic to test, and responses must send `Vary: Accept` so caches don't mix the formats up.

## CBOR through a native extension

**Why:** `ranvis/php-ext-cbor` is the only native CBOR encoder for PHP, and far faster than pure-PHP libraries.
**Trade-off:** its last release was in 2023, it isn't in the extension installer, so we build it from source in every image, pinned to 0.4.9.

## PayHere: popup for staff, redirect for customers

**Why:** staff pay often, so the popup keeps them on the page. Customers pay rarely, so a redirect is fine.
**Trade-off:** two payment flows to maintain. In both, only PayHere's server notification (with a verified hash) marks a payment as paid, never the browser.

## Cursor pagination

**Why:** lists load more as you scroll, and cursors stay correct when rows are added in between. Totals come from a separate `COUNT(*)`.
**Trade-off:** no "jump to page 7".

## One `composer.json` and one `package.json`

**Why:** one set of dependencies is simpler to reason about than per-folder ones.
**Trade-off:** every image installs everything, including packages only another part uses.

## Alpine images for web and worker

**Why:** smaller images.
**Trade-off:** Alpine uses musl instead of glibc. Some behaviour differs, for example text conversion with `iconv`.

## Test container built on Playwright's Ubuntu image

**Why:** browsers are the hard part to install, and Playwright doesn't support Alpine. Ubuntu 26.04 ships PHP 8.5 in its own repository.
**Trade-off:** tests run on the same PHP minor version as production, but a different build and libc. Compare `php -r 'echo PHP_VERSION;'` in `test` and `worker` from time to time.

## One test container for all suites

**Why:** unit, integration and browser tests run from one place, with one set of commands.
**Trade-off:** unit tests lose the guarantee of having no network. We make up for it by giving unit tests no database or cache settings.

## Every image pinned by digest

**Why:** a digest always means the same bytes, so builds are reproducible. All pins live in `.docker/.env`.
**Trade-off:** updates are manual. See the [development guide](development.md#updating-image-pins).
