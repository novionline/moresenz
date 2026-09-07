<?php

namespace NoviOnline\CodeSnippets;

/**
 * Writes snippet CSS/JS to hashed static files under uploads and serves them via AO-safe rewrite URLs.
 */
class SnippetAssetCache
{
    const OPTION_GENERATION = 'ncs_cache_generation';

    const BASE_SUBDIR = 'novi-code-snippets';

    const BASENAME_PATTERN = '/^[0-9]+-[a-f0-9]{12}\.(css|js)$/';

    const HASH_LENGTH = 12;

    /**
     * @return int
     */
    public static function getGeneration(): int
    {
        return max(1, (int) get_option(self::OPTION_GENERATION, 1));
    }

    /**
     * @return int
     */
    public static function bumpGeneration(): int
    {
        $generation = self::getGeneration() + 1;
        update_option(self::OPTION_GENERATION, $generation, false);
        return $generation;
    }

    /**
     * @return string
     */
    public static function getStorageRoot(): string
    {
        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return '';
        }
        return trailingslashit($upload['basedir']) . self::BASE_SUBDIR;
    }

    /**
     * @param string $type css|js
     * @return string
     */
    public static function getStorageDir(string $type): string
    {
        self::validateType($type);
        return self::getStorageRoot() . '/' . $type;
    }

    /**
     * @param string $type css|js
     * @param string $basename
     * @return string
     */
    public static function getPublicUrl(string $type, string $basename): string
    {
        self::validateType($type);
        if (!self::isValidBasename($basename)) {
            return '';
        }

        $upload = wp_upload_dir();
        if (empty($upload['error']) && !empty($upload['baseurl'])) {
            return trailingslashit($upload['baseurl']) . self::BASE_SUBDIR . '/' . $type . '/' . $basename;
        }

        //fallback when uploads is unavailable during CLI/cron
        return home_url('/novi-code-snippets/' . $type . '/' . $basename);
    }

    /**
     * @param string $code
     * @return string
     */
    public static function buildHash(string $code): string
    {
        $generation = self::getGeneration();
        return substr(hash('sha256', $code . '|' . $generation), 0, self::HASH_LENGTH);
    }

    /**
     * @param int $postId
     * @param string $type css|js
     * @param string $code
     * @return string
     */
    public static function buildFilename(int $postId, string $type, string $code): string
    {
        return $postId . '-' . self::buildHash($code) . '.' . $type;
    }

    /**
     * @param string $type css|js
     * @return string
     */
    public static function getMetaKey(string $type): string
    {
        return $type === 'css' ? SnippetValidator::META_CACHE_CSS_FILE : SnippetValidator::META_CACHE_JS_FILE;
    }

    /**
     * @param string $basename
     * @return bool
     */
    public static function isValidBasename(string $basename): bool
    {
        return (bool) preg_match(self::BASENAME_PATTERN, $basename);
    }

    /**
     * @param string $basename
     * @return string|null
     */
    public static function getHashVersionFromBasename(string $basename): ?string
    {
        if (preg_match('/^[0-9]+-([a-f0-9]{12})\.(css|js)$/', $basename, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * @return void
     */
    public static function ensureDirectories(): void
    {
        $root = self::getStorageRoot();
        if ($root === '') {
            return;
        }
        wp_mkdir_p($root . '/css');
        wp_mkdir_p($root . '/js');
        self::writeSilenceIndex($root);
        self::writeSilenceIndex($root . '/css');
        self::writeSilenceIndex($root . '/js');
        self::maybeWriteHtaccess($root);
    }

    /**
     * @param string $dir
     * @return void
     */
    protected static function writeSilenceIndex(string $dir): void
    {
        $indexPath = $dir . '/index.php';
        if (!is_file($indexPath)) {
            file_put_contents($indexPath, "<?php\n//silence is golden\n");
        }
    }

    /**
     * @param string $root
     * @return void
     */
    protected static function maybeWriteHtaccess(string $root): void
    {
        $htaccess = $root . '/.htaccess';
        if (is_file($htaccess)) {
            return;
        }
        $content = "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps|cgi|pl|py)$\">\n    Require all denied\n</FilesMatch>\n";
        @file_put_contents($htaccess, $content);
    }

    /**
     * @param \WP_Post $post
     * @param string $type css|js
     * @param string $minified
     * @return string|null public URL when a cache file is available
     */
    public static function writeForPost(\WP_Post $post, string $type, string $minified): ?string
    {
        $postId = (int) $post->ID;
        if ($postId <= 0 || $minified === '' || $post->post_status !== 'publish') {
            self::deleteForPost($postId, $type);
            return null;
        }
        self::validateType($type);
        self::ensureDirectories();
        $basename = self::buildFilename($postId, $type, $minified);
        if (!self::isValidBasename($basename)) {
            return null;
        }
        $dir = self::getStorageDir($type);
        $path = $dir . '/' . $basename;
        $oldBasename = get_post_meta($postId, self::getMetaKey($type), true);
        if (is_file($path) && file_get_contents($path) === $minified) {
            if ($oldBasename !== $basename) {
                update_post_meta($postId, self::getMetaKey($type), $basename);
                if (is_string($oldBasename) && $oldBasename !== '' && $oldBasename !== $basename) {
                    self::unlinkBasename($type, $oldBasename);
                }
            }
            self::sweepOrphans();
            return self::getPublicUrl($type, $basename);
        }
        $tmpPath = $path . '.' . wp_generate_password(8, false) . '.tmp';
        if (file_put_contents($tmpPath, $minified) === false) {
            @unlink($tmpPath);
            return null;
        }
        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            return null;
        }
        update_post_meta($postId, self::getMetaKey($type), $basename);
        if (is_string($oldBasename) && $oldBasename !== '' && $oldBasename !== $basename) {
            self::unlinkBasename($type, $oldBasename);
        }
        self::sweepOrphans();
        return self::getPublicUrl($type, $basename);
    }

    /**
     * @param int $postId
     * @param string|null $type css|js or null for both
     * @return void
     */
    public static function deleteForPost(int $postId, ?string $type = null): void
    {
        if ($postId <= 0) {
            return;
        }
        $types = $type ? [$type] : ['css', 'js'];
        foreach ($types as $assetType) {
            $basename = get_post_meta($postId, self::getMetaKey($assetType), true);
            delete_post_meta($postId, self::getMetaKey($assetType));
            if (is_string($basename) && $basename !== '') {
                self::unlinkBasename($assetType, $basename);
            }
        }
        self::sweepOrphans();
    }

    /**
     * @param string $type css|js
     * @param string $basename
     * @return void
     */
    protected static function unlinkBasename(string $type, string $basename): void
    {
        if (!self::isValidBasename($basename)) {
            return;
        }
        $path = self::getStorageDir($type) . '/' . $basename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param \WP_Post $post
     * @param string $type css|js
     * @return string|null public URL when a cache file is available
     */
    public static function ensureForPost(\WP_Post $post, string $type): ?string
    {
        $postId = (int) $post->ID;
        $valid = get_post_meta($postId, SnippetValidator::META_VALID, true);
        if ($valid !== '1') {
            return null;
        }
        $code = get_post_meta($postId, SnippetValidator::META_MINIFIED, true);
        if ($code === '' || $code === false) {
            $rawCode = get_field('snippet_code', $postId);
            if (!is_string($rawCode) || trim($rawCode) === '') {
                self::deleteForPost($postId, $type);
                return null;
            }
            try {
                $code = $type === 'css' ? Minify::css($rawCode) : Minify::js($rawCode);
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (!is_string($code) || $code === '') {
            return null;
        }
        $expectedBasename = self::buildFilename($postId, $type, $code);
        $storedBasename = get_post_meta($postId, self::getMetaKey($type), true);
        $path = self::getStorageDir($type) . '/' . $expectedBasename;
        if ($storedBasename === $expectedBasename && is_file($path)) {
            return self::getPublicUrl($type, $expectedBasename);
        }
        return self::writeForPost($post, $type, $code);
    }

    /**
     * @param bool $bumpGeneration
     * @return array{css: int, js: int, orphans: int}
     */
    public static function flushAll(bool $bumpGeneration = true): array
    {
        if ($bumpGeneration) {
            self::bumpGeneration();
        }
        $counts = ['css' => 0, 'js' => 0, 'orphans' => 0];
        $query = new \WP_Query([
            'post_type' => CodeSnippetsPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        foreach ($query->posts as $postId) {
            $postId = (int) $postId;
            $valid = get_post_meta($postId, SnippetValidator::META_VALID, true);
            if ($valid !== '1') {
                self::deleteForPost($postId);
                continue;
            }
            $snippetType = get_field('snippet_type', $postId);
            $code = get_post_meta($postId, SnippetValidator::META_MINIFIED, true);
            if (!is_string($code) || $code === '') {
                self::deleteForPost($postId);
                continue;
            }
            $post = get_post($postId);
            if (!$post) {
                continue;
            }
            if ($snippetType === 'css') {
                if (self::writeForPost($post, 'css', $code) !== null) {
                    $counts['css']++;
                }
                self::deleteForPost($postId, 'js');
            } elseif ($snippetType === 'js') {
                if (self::writeForPost($post, 'js', $code) !== null) {
                    $counts['js']++;
                }
                self::deleteForPost($postId, 'css');
            } else {
                self::deleteForPost($postId);
            }
        }
        wp_reset_postdata();
        $counts['orphans'] = self::sweepOrphans();
        return $counts;
    }

    /**
     * @return int
     */
    public static function sweepOrphans(): int
    {
        $referenced = [];
        global $wpdb;
        $metaKeys = [SnippetValidator::META_CACHE_CSS_FILE, SnippetValidator::META_CACHE_JS_FILE];
        foreach ($metaKeys as $metaKey) {
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value != ''",
                $metaKey,
                CodeSnippetsPostType::POST_TYPE
            ));
            foreach ($rows as $basename) {
                if (is_string($basename) && self::isValidBasename($basename)) {
                    $referenced[$basename] = true;
                }
            }
        }
        $deleted = 0;
        foreach (['css', 'js'] as $type) {
            $dir = self::getStorageDir($type);
            if (!is_dir($dir)) {
                continue;
            }
            $files = scandir($dir);
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === 'index.php') {
                    continue;
                }
                if (!self::isValidBasename($file) || !isset($referenced[$file])) {
                    @unlink($dir . '/' . $file);
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    /**
     * @return bool
     */
    public static function isStorageWritable(): bool
    {
        $root = self::getStorageRoot();
        if ($root === '') {
            return false;
        }
        if (is_dir($root)) {
            return is_writable($root);
        }
        $upload = wp_upload_dir();
        $basedir = $upload['basedir'] ?? '';
        return $basedir !== '' && is_writable($basedir);
    }

    /**
     * @param string $type css|js
     * @param string $basename
     * @return string|null absolute path when safe to serve
     */
    public static function resolveSecurePath(string $type, string $basename): ?string
    {
        if (!self::isValidBasename($basename)) {
            return null;
        }
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        if ($extension !== $type) {
            return null;
        }
        $dir = self::getStorageDir($type);
        $dirReal = realpath($dir);
        if ($dirReal === false) {
            return null;
        }
        $path = $dir . '/' . $basename;
        if (!is_file($path)) {
            return null;
        }
        $pathReal = realpath($path);
        if ($pathReal === false) {
            return null;
        }
        $dirPrefix = rtrim($dirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($pathReal, $dirPrefix) !== 0) {
            return null;
        }
        return $pathReal;
    }

    /**
     * @param string $type css|js
     * @return void
     */
    protected static function validateType(string $type): void
    {
        if ($type !== 'css' && $type !== 'js') {
            throw new \InvalidArgumentException('Invalid asset type: ' . $type);
        }
    }
}
