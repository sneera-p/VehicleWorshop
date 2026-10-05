<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use JsonException;
use Vwork\Web\WebError;

/**
 * Reads web/build/assets.json and turns it into ready-to-use URLs:
 * "css/staff.css" → "/assets/staff-x4j2av4s.css".
 *
 * Views get the array itself (see toArray()), so they need no import:
 *
 *     /** @var array<string, string> $assets *\/
 *     <link rel="stylesheet" href="<?= $assets['css/staff.css'] ?>">
 *
 * Reads the manifest once. In worker mode that's once per worker, so a new
 * build needs a restart. Dev runs in classic mode, which reads it on every request.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class AssetParser implements IUtility
{
    /** @var array<string, string> source path → URL */
    public readonly array $urls;

    /**
     * @throws WebError if the manifest is missing or is not a JSON object (run the build)
     */
    public function __construct(string $manifestPath)
    {
        $real = realpath(__DIR__ . '/../../../' . $manifestPath);
        if ($real === false || !is_file($real)) {
            throw new WebError("Asset manifest not found: {$manifestPath} (run the front-end build)");
        }

        try {
            $raw = json_decode((string) file_get_contents($real), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new WebError("Asset manifest is not valid JSON: {$manifestPath}", $e);
        }

        if (!is_array($raw)) {
            throw new WebError("Asset manifest is not a JSON object: {$manifestPath}");
        }

        /** @var array<string, string> $raw */
        $this->urls = array_map(static fn (string $file): string => "/assets/{$file}", $raw);
    }
}
