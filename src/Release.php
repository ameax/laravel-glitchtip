<?php

namespace Ameax\Glitchtip;

/**
 * Detects the deployed release without calling a shell.
 *
 * Order: the REVISION file written by Deployer into each release directory,
 * then the checked out commit of a git working copy (deployments via `git pull`).
 */
class Release
{
    public static function detect(string $basePath): ?string
    {
        return self::fromRevisionFile($basePath) ?? self::fromGit($basePath);
    }

    public static function fromRevisionFile(string $basePath): ?string
    {
        $revision = self::read($basePath.'/REVISION');

        return $revision !== null && preg_match('/^[0-9a-zA-Z._\-]{1,64}$/', $revision) === 1 ? $revision : null;
    }

    public static function fromGit(string $basePath): ?string
    {
        $gitPath = $basePath.'/.git';
        $head = self::read($gitPath.'/HEAD');

        if ($head === null) {
            return null;
        }

        if (! str_starts_with($head, 'ref: ')) {
            return self::validHash($head);
        }

        $ref = substr($head, 5);

        return self::validHash(self::read($gitPath.'/'.$ref)) ?? self::packedRef($gitPath, $ref);
    }

    private static function packedRef(string $gitPath, string $ref): ?string
    {
        foreach (explode("\n", self::read($gitPath.'/packed-refs') ?? '') as $line) {
            $parts = explode(' ', trim($line));

            if (count($parts) === 2 && $parts[1] === $ref) {
                return self::validHash($parts[0]);
            }
        }

        return null;
    }

    private static function read(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false || trim($content) === '' ? null : trim($content);
    }

    private static function validHash(?string $hash): ?string
    {
        return $hash !== null && preg_match('/^[0-9a-f]{40}$/', $hash) === 1 ? $hash : null;
    }
}
