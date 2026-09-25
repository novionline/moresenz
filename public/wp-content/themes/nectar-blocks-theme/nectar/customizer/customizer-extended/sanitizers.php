function sanitize_custom_option($input) {
  return ( 'Yes' === $input ) ? 'Yes' : 'No';
};

function sanitize_custom_text($input) {
  return sanitize_text_field( $input );
};

function sanitize_custom_url($input) {
  return filter_var($input, FILTER_SANITIZE_URL);
};

function sanitize_custom_email($input) {
  return filter_var($input, FILTER_SANITIZE_EMAIL);
};

function nectar_sanitize_hex_color( $color ) {
  if ( '' === $color ) {
    return '';
  }

  // 3 or 6 hex digits, or the empty string.
  if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
    return $color;
  }

  return '';
};
