<?php

// PHP CS Fixer: https://cs.symfony.com
// This is the authoritative, CLI/CI-runnable PSR-12 enforcement layer —
// independent of any single editor's formatter, so style is enforced the
// same way whether it's applied on save in VS Code, in a pre-commit hook,
// or in CI.
//
// Run (from repo root — config must be pointed at explicitly since this
// file no longer lives in the directory PHP CS Fixer auto-discovers from):
//   vendor/bin/php-cs-fixer fix --config=tools/.php-cs-fixer.dist.php
// Check without modifying (e.g. in CI):
//   vendor/bin/php-cs-fixer fix --config=tools/.php-cs-fixer.dist.php --dry-run --diff

// __DIR__ now resolves to tools/, not repo root — the Finder needs to be
// pointed at the parent directory explicitly to still scan the whole repo.
$finder = (new PhpCsFixer\Finder())
   ->in(__DIR__ . '/..')
   ->exclude(['vendor', 'var', 'node_modules'])
   ->name('*.php')
   ->notName('*.blade.php')
   ->ignoreDotFiles(true)
   ->ignoreVCS(true);

return (new PhpCsFixer\Config())
   ->setRules([
      '@PSR12' => true,
      // Add project-specific rule overrides here as needed, e.g.:
      // 'array_syntax' => ['syntax' => 'short'],
   ])
   ->setIndent('    ') // PSR-12: 4 spaces, hard requirement, not a preference
   ->setLineEnding("\n")
   ->setFinder($finder);
