<?php
/**
 * Categories
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 2.0.0
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<span class="meta-category nectar-font-label">

<?php
$terms = get_the_terms( get_the_ID(), 'portfolio_category' );

if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
  $output = [];

  foreach ( $terms as $term ) {
    $output[] = sprintf(
        '<a href="%s" class="nectar-inherit-border-radius">%s</a>',
        esc_url( get_term_link( $term ) ),
        esc_html( $term->name )
    );
  }

  echo apply_filters( 'nectar_portfolio_archive_categories', implode( ' ', $output ) ); // WPCS: XSS ok.
}
?>
</span>