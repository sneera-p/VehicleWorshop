<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

use Override;
use Vwork\Shared\Types\Cast;
use Vwork\Web\WebError;

/**
 * What came in.
 *
 * Everything the app knows about what's being asked of it: method, path,
 * headers, and whatever the client sent along — query params, form
 * fields, uploaded files. Form data and files are read out at the very
 * moment the request is built from PHP's globals, because PHP only lets
 * you read the multipart body once — miss that window and it's gone.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class Request extends HttpMessage
{
    /**
     * "Content-Type" => "HTTP_CONTENT_TYPE", built once per worker
     * instead of re-deriving it for every header of every request.
     *
     * @return array<string, HttpHeaders>
     */
    private static function serverKeyMap(): array
    {
        /**
         * $_SERVER key ("HTTP_CONTENT_TYPE") => HttpHeaders case, built once
         * per worker process rather than re-deriving the name transformation
         * on every header of every request.
         *
         * @var array<string, HttpHeaders> | null
         */
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (HttpHeaders::cases() as $header) {
                $map['HTTP_' . strtoupper(str_replace('-', '_', $header->value))] = $header;
            }
        }

        return $map;
    }


    /**
     * Parsed on first read, then kept — a Request never changes.
     *
     * @var array<value-of<HttpCookies>, string>
     */
    #[Override]
    public array $cookies {
        get => $this->cookies ??= (function (): array {
            $acc = [];
            foreach (explode(';', $this->headers[HttpHeaders::Cookie->value][0] ?? '') as $pair) {
                $acc = [...$acc, ...self::parseCookiePair($pair)];
            }
            return $acc;
        })();
    }


    /**
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     * @param array<string, string> $query
     * @param array<string, string> $formData
     * @param array<string, UploadedFile> $files
     */
    private function __construct(
        public readonly HttpMethods $method,
        public readonly string $path,
        array $headers,
        public readonly array $query,
        public readonly string $body,
        public readonly array $formData,
        public readonly array $files,
        public readonly string $ip,
    ) {
        parent::__construct($headers);
    }


    /**
     * Drops anything HttpHeaders doesn't know about, since $headers is
     * typed to the closed enum set.
     *
     * @param array<string|int, mixed> $rawServer
     * @return array<value-of<HttpHeaders>, list<string>>
     */
    private static function extractHeaders(array $rawServer): array
    {
        /** @var array<value-of<HttpHeaders>, list<string>> */
        $acc = [];

        foreach (self::serverKeyMap() as $serverKey => $header) {
            $value = $rawServer[$serverKey] ?? null;
            if ($value === null) {
                continue;
            }

            /** @var string $value */
            $acc[$header->value][] = $value;
        }

        return $acc;
    }

    /**
     * Flattens $_FILES, which comes in two shapes:
     * *  single: ['name' => 'a.jpg', 'tmp_name' => '/tmp/1', ...]
     * *  multi:  ['name' => ['a.jpg', 'b.jpg'], 'tmp_name' => [...], ...]
     *
     * Multi-file fields become one entry per index, keyed "{field}[{index}]".
     *
     * @param array<mixed, mixed> $rawFiles - typically $_FILES
     * @return array<string, UploadedFile>
     */
    private static function extractFiles(array $rawFiles): array
    {
        /** @var array<string, UploadedFile> */
        $acc = [];

        /**
         * @var string $field
         * @var array{
         *    name: list<string>,
         *    tmp_name: list<string>,
         *    type: list<string>,
         *    size: list<int>,
         *    error: list<int>
         * } | array{
         *    name: string,
         *    tmp_name: string,
         *    type: string,
         *    size: int,
         *    error: int
         * } $fileData
         */
        foreach ($rawFiles as $field => $fileData) {

            if (is_array($fileData['name'])) {
                foreach (array_keys($fileData['name']) as $index) {
                    $acc["{$field}[{$index}]"] = UploadedFile::fromMultiArray($fileData, $index);
                }
            } else {
                $acc[$field] = UploadedFile::fromSingleArray($fileData);
            }
        }

        return $acc;
    }

    /**
     * 🔴 ⚠️ Reads from PHP's superglobals. ⚠️ 🔴
     *
     * Everything downstream works off the Request this builds. Reaching
     * for $_SERVER/$_GET/$_POST/$_FILES elsewhere means the data belongs
     * on Request instead.
     *
     * @throws WebError if the method isn't one HttpMethods knows
     */
    public static function fromGlobals(): self
    {
        $methodString = strtoupper(Cast::string($_SERVER['REQUEST_METHOD']));
        $method = HttpMethods::tryFrom($methodString);
        if ($method === null) {
            throw new WebError("Unsupported HTTP method: {$methodString}");
        }

        // body, formData and files all get captured here together: PHP eats
        // the multipart stream before userland runs, so php://input is empty
        // for uploads and there's no second chance at any of it
        $body = file_get_contents('php://input');
        if ($body === false) {
            $body = '';
        }

        return new self(
            method: $method,
            body: $body,
            headers: self::extractHeaders($_SERVER),
            files: self::extractFiles($_FILES),
            ip: Cast::string($_SERVER['REMOTE_ADDR']),
            path: explode('?', Cast::string($_SERVER['REQUEST_URI']), 2)[0],
            query: Cast::stringMap($_GET),
            formData: Cast::stringMap($_POST),
        );
    }
}
