<?php

namespace TCG\Voyager\Support;

/**
 * Decides which file names and paths Voyager may write to a storage disk.
 *
 * The extension of an upload comes from the client, and the media disk is usually
 * served by the same web server that runs PHP, so a file named shell.php (or a GIF
 * polyglot called that) would be executed when requested (CVE-2024-55417). Every
 * dot-separated segment is checked because Apache's AddHandler also runs
 * shell.php.jpg as PHP. The denylist applies regardless of configuration; the
 * `voyager.media.allowed_extensions` allowlist narrows it further.
 */
class SafeUpload
{
    /**
     * Extensions that a web server may execute or that change how it serves a folder.
     */
    public const DENIED_EXTENSIONS = '/^(php\d*|phtml|pht|phar|phps|pgif|shtml|htaccess|htpasswd|cgi|pl|py|sh|asp|aspx|jsp)$/';

    /**
     * Used when `voyager.media.allowed_extensions` is missing from a published config.
     */
    public const DEFAULT_ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif', 'svg', 'ico', 'tif', 'tiff',
        'mp4', 'm4v', 'mov', 'webm', 'ogv', 'avi', 'mkv', 'wmv',
        'mp3', 'm4a', 'wav', 'ogg', 'oga', 'flac', 'aac',
        'pdf', 'txt', 'csv', 'rtf', 'md', 'json',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'zip', 'rar', '7z', 'gz', 'tar',
    ];

    /**
     * A plain file name (no directory part) that the web server will not execute.
     */
    public static function isSafeName($name): bool
    {
        if (!is_string($name) || in_array($name, ['', '.', '..'], true) || strpbrk($name, "/\\\0") !== false) {
            return false;
        }

        $name = strtolower($name);
        if ($name === '.user.ini') {
            return false;
        }

        // The first segment is the base name, not an extension ("php.jpg" is fine).
        foreach (array_slice(explode('.', $name), 1) as $segment) {
            if (preg_match(self::DENIED_EXTENSIONS, rtrim($segment, " \t"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * A safe name whose extension is also on the configured allowlist.
     */
    public static function isAllowedFileName($name): bool
    {
        if (!static::isSafeName($name)) {
            return false;
        }

        $allowed = config('voyager.media.allowed_extensions', self::DEFAULT_ALLOWED_EXTENSIONS);
        if ($allowed === '*') {
            return true;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // No extension: nothing maps it to a handler, and the hard denylist already ran.
        return $extension === '' || in_array($extension, array_map('strtolower', (array) $allowed), true);
    }

    /**
     * A path inside the disk: no "..", no null bytes, no drive letters.
     *
     * A leading slash is allowed: the media manager sends folders relative to the disk root as "/folder".
     */
    public static function isSafePath($path): bool
    {
        if ($path === null) {
            return true;
        }

        if (!is_string($path) || str_contains($path, "\0") || preg_match('/^[a-z]:/i', $path)) {
            return false;
        }

        return !in_array('..', preg_split('#[/\\\\]#', $path), true);
    }
}
