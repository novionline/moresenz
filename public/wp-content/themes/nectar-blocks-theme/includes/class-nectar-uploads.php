<?php

/**
 * Nectar Uploads Utility.
 *
 * Shared filesystem helpers for all theme features that store
 * generated files under wp-content/uploads/nectar-blocks/.
 *
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nectar_Theme_Uploads {
    /**
     * Root directory name inside uploads.
     */
    const BASE_DIR = 'nectar-blocks/theme';

    /**
     * Initialise WP_Filesystem and return the global instance.
     *
     * @return WP_Filesystem_Base|false
     */
    public static function filesystem() {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
        }
        return ! empty( $wp_filesystem ) ? $wp_filesystem : false;
    }

    /**
     * Get the base directory path and URL for a given sub-feature.
     *
     * Example: Nectar_Uploads::paths( 'used-css/v1' )
     * → { dir: /abs/wp-content/uploads/nectar-blocks/used-css/v1,
     *     url: https://site.com/wp-content/uploads/nectar-blocks/used-css/v1 }
     *
     * @param string $sub_path  Path segments after nectar-blocks/ (e.g. 'dynamic-styles' or 'used-css/v1').
     * @return array{dir: string, url: string}|false  False on wp_upload_dir error.
     */
    public static function paths( $sub_path = '' ) {
        $upload_info = wp_upload_dir();
        if ( ! empty( $upload_info['error'] ) ) {
            return false;
        }

        $relative = self::BASE_DIR;
        if ( $sub_path !== '' ) {
            $relative .= '/' . ltrim( $sub_path, '/' );
        }

        $dir = trailingslashit( $upload_info['basedir'] ) . $relative;
        $url = trailingslashit( $upload_info['baseurl'] ) . $relative;

        if ( is_ssl() ) {
            $url = set_url_scheme( $url, 'https' );
        }

        return [ 'dir' => $dir, 'url' => $url ];
    }

    /**
     * Ensure a directory exists inside the nectar-blocks uploads tree.
     *
     * @param string $sub_path
     * @return string|false  The absolute directory path, or false on failure.
     */
    public static function ensure_dir( $sub_path ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths ) {
            return false;
        }

        $fs = self::filesystem();
        if ( ! $fs ) {
            return false;
        }

        if ( ! ( method_exists( $fs, 'exists' ) && $fs->exists( $paths['dir'] ) ) ) {
            wp_mkdir_p( $paths['dir'] );
        }

        return $paths['dir'];
    }

    /**
     * Check if a directory is writable.
     *
     * @param string $sub_path
     * @return bool
     */
    public static function is_writable( $sub_path ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths ) {
            return false;
        }

        $fs = self::filesystem();
        if ( ! $fs ) {
            return false;
        }

        return method_exists( $fs, 'is_writable' ) && $fs->is_writable( $paths['dir'] );
    }

    /**
     * Check if a file exists.
     *
     * @param string $sub_path  e.g. 'used-css/v1/post_42.css'
     * @return bool
     */
    public static function file_exists( $sub_path ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths ) {
            return false;
        }

        $fs = self::filesystem();
        return $fs && method_exists( $fs, 'exists' ) && $fs->exists( $paths['dir'] );
    }

    /**
     * Write content to a file. Creates parent directories if needed.
     *
     * @param string $sub_path  e.g. 'used-css/v1/post_42.css'
     * @param string $content
     * @return bool  True on success.
     */
    public static function put_contents( $sub_path, $content ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths ) {
            return false;
        }

        $fs = self::filesystem();
        if ( ! $fs ) {
            return false;
        }

        // Ensure parent directory exists.
        $dir = dirname( $paths['dir'] );
        if ( ! ( method_exists( $fs, 'exists' ) && $fs->exists( $dir ) ) ) {
            wp_mkdir_p( $dir );
        }

        if ( method_exists( $fs, 'is_writable' ) && ! $fs->is_writable( $dir ) ) {
            return false;
        }

        // These files are live-served CSS: write to a temp file and rename it
        // over the target so a concurrent reader never sees a truncated file.
        $target = $paths['dir'];
        $tmp = $target . '.tmp-' . uniqid( '', true );
        $chmod = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : false;

        if ( ! $fs->put_contents( $tmp, $content, $chmod ) ) {
            self::discard_tmp( $fs, $tmp );
            return false;
        }

        if ( self::replace( $fs, $tmp, $target ) ) {
            return true;
        }

        self::discard_tmp( $fs, $tmp );

        // Nothing could be swapped into place — fall back to the previous
        // in-place write rather than failing the update outright.
        return (bool) $fs->put_contents( $target, $content, $chmod );
    }

    /**
     * Swap a fully written temp file over an existing target.
     *
     * Deliberately does *not* use WP_Filesystem_Direct::move(): with
     * $overwrite it unlinks the destination first and only then renames, so
     * there is a window in which a live-served stylesheet does not exist and
     * visitors get a 404 (worse than the truncation this whole dance avoids).
     * rename() replaces the destination in one step — atomically on POSIX
     * within a filesystem, and PHP's rename() also overwrites on Windows
     * (non-atomic there, but still without an unlink-first hole).
     *
     * Permissions carry over from the temp file, which put_contents() already
     * chmod'd to FS_CHMOD_FILE; rename keeps the inode's mode and owner.
     *
     * @param WP_Filesystem_Base $fs
     * @param string             $tmp
     * @param string             $target
     * @return bool
     */
    private static function replace( $fs, $tmp, $target ) {
        if ( self::is_direct( $fs ) ) {
            return @rename( $tmp, $target );
        }

        // Remote transports (ftpext/ssh2) have no atomic replace to offer, and
        // some servers can't rename over an existing file at all; move() is the
        // best available there and its failure is handled by the caller.
        return (bool) $fs->move( $tmp, $target, true );
    }

    /**
     * Remove a leftover temp file. These live in a publicly served directory,
     * so make sure one is never stranded when the filesystem abstraction
     * refuses to delete it.
     *
     * @param WP_Filesystem_Base $fs
     * @param string             $tmp
     * @return void
     */
    private static function discard_tmp( $fs, $tmp ) {
        if ( $fs->delete( $tmp ) || ! self::is_direct( $fs ) ) {
            return;
        }

        if ( file_exists( $tmp ) ) {
            @unlink( $tmp );
        }
    }

    /**
     * Whether the active WP_Filesystem transport operates on the local
     * filesystem (WP_Filesystem_Direct sets `method` to 'direct').
     *
     * @param WP_Filesystem_Base $fs
     * @return bool
     */
    private static function is_direct( $fs ) {
        return isset( $fs->method ) && $fs->method === 'direct';
    }

    /**
     * Get the public URL for a file.
     *
     * @param string $sub_path  e.g. 'used-css/v1/post_42.css'
     * @return string|false
     */
    public static function url( $sub_path ) {
        $paths = self::paths( $sub_path );
        return $paths ? $paths['url'] : false;
    }

    /**
     * Delete a file.
     *
     * @param string $sub_path
     * @return bool
     */
    public static function delete_file( $sub_path ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths ) {
            return false;
        }

        $fs = self::filesystem();
        if ( ! $fs ) {
            return false;
        }

        if ( method_exists( $fs, 'exists' ) && $fs->exists( $paths['dir'] ) ) {
            return (bool) $fs->delete( $paths['dir'] );
        }

        return false;
    }

    /**
     * Delete a directory and all its contents.
     * Uses scandir + realpath safety checks.
     *
     * @param string $sub_path  e.g. 'used-css/v1'
     * @return bool
     */
    public static function delete_dir( $sub_path ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths || ! is_dir( $paths['dir'] ) ) {
            return false;
        }

        $fs = self::filesystem();
        if ( ! $fs ) {
            return false;
        }

        return (bool) $fs->delete( $paths['dir'], true );
    }

    /**
     * List immediate subdirectories of a path, with realpath safety checks.
     * Useful for cleanup routines that need to iterate version directories.
     *
     * @param string $sub_path         e.g. 'used-css'
     * @param string $exclude_entry    Entry name to skip (e.g. current version).
     * @return string[]  Array of entry names (not full paths).
     */
    public static function list_dirs( $sub_path, $exclude_entry = '' ) {
        $paths = self::paths( $sub_path );
        if ( ! $paths || ! is_dir( $paths['dir'] ) ) {
            return [];
        }

        $parent_real = @realpath( $paths['dir'] );
        $entries = @scandir( $paths['dir'] );

        if ( ! is_array( $entries ) || ! $parent_real ) {
            return [];
        }

        $result = [];
        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }
            if ( $exclude_entry !== '' && $entry === $exclude_entry ) {
                continue;
            }

            $path = trailingslashit( $paths['dir'] ) . $entry;
            $path_real = @realpath( $path );

            // Safety: ensure path doesn't escape parent.
            if ( ! $path_real || strpos( $path_real, $parent_real ) !== 0 ) {
                continue;
            }

            if ( is_dir( $path_real ) ) {
                $result[] = $entry;
            }
        }

        return $result;
    }
}
