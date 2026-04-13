<?php

namespace Nectar\Utilities;

/**
 * @since 1.3.4
 * @version 1.3.4
 */
class HTTP {
  public static function maybe_force_https($url) {
    return is_ssl() ? set_url_scheme($url, 'https') : $url;
  }
}
