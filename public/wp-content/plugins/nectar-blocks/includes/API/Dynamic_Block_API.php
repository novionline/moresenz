<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route};
use Nectar\Global_Sections\Global_Sections;
use Nectar\Nectar_Templates\Nectar_Templates;

/**
 * Dynamic Block API
 * @version 0.0.9
 * @since 0.0.9
 */
class Dynamic_Block_API implements API_Route {
  const API_BASE = '/post_renderer';

  public function build_routes() {
    Router::add_route($this::API_BASE . '/render', [
      'callback' => [$this, 'get_dynamic_post'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return true;
      },
      'args' => [
        'pageID' => [
          'type' => 'string',
          'required' => true,
          'description' => 'Block to render'
        ],
        'blockID' => [
          'type' => 'string',
          'required' => true,
          'description' => 'BlockID to render'
        ],
        'overrideAttrs' => [
          'type' => 'object',
          'required' => false,
        ]
      ]
    ]);
  }

  /**
   * Find the block in the post content.
   * @since 0.0.9
   * @version 0.0.9
   * @param array $blocks
   * @param string $block_id
   * @return array|false
   */
  private function findBlock($blocks, $block_id) {
    foreach ($blocks as $block) {
      if ( ($block['attrs']['blockId'] ?? '') == $block_id ) {
        return $block;
      }

      if (! empty($block['innerBlocks'])) {
        if ($data = $this->findBlock($block['innerBlocks'], $block_id)) {
          return $data;
        }
      }
    }
    return false;
  }

  /**
   * Find a block inside published global-section / template CPTs.
   *
   * Their inner blocks are injected into pages via hooks (e.g. "after page
   * content") and are therefore NOT part of the viewed page's post_content, so
   * the primary findBlock() on the page misses them. Returns the first matching
   * block array, searching by the globally-unique blockId.
   *
   * @since 0.0.10
   * @version 0.0.10
   * @param string $block_id
   * @return array|false
   */
  private function findBlockInInjectedSources($block_id) {
    $query = new \WP_Query([
      'post_type' => [ Global_Sections::POST_TYPE, Nectar_Templates::POST_TYPE ],
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'no_found_rows' => true,
      'fields' => 'ids',
    ]);

    foreach ($query->posts as $source_id) {
      $content = get_post_field('post_content', $source_id);
      if (empty($content)) {
        continue;
      }

      if ($block = $this->findBlock(parse_blocks($content), $block_id)) {
        return $block;
      }
    }

    return false;
  }

  /**
   * Get the dynamic post.
   * @since 0.0.9
   * @version 0.0.9
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_dynamic_post(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $post_id = $json_body['pageID'];
    $block_id = $json_body['blockID'];
    $override_attrs = $json_body['overrideAttrs'] ?? [];

    $post = get_post($post_id);
    if (! $post) {
      return new \WP_REST_Response(
          [
          'status' => 'failure',
          'message' => 'Unable to find post'
        ],
          200
      );
    };

    $block = $this->findBlock(
        parse_blocks($post->post_content),
        $block_id
    );

    // The block may live in a global section / template CPT injected into the
    // page via a hook rather than in the page's own post_content.
    if ($block === false) {
      $block = $this->findBlockInInjectedSources($block_id);
    }

    if ($block === false) {
      return new \WP_REST_Response(
          [
            'status' => 'failure',
            'message' => 'Unable to find block in post'
          ],
          200
      );
    }

    $block['attrs'] = array_merge(
        $block['attrs'],
        $override_attrs
    );

    // Render with the requested page set up as the current post so the block's
    // render_callback sees the same context it had on the initial page load.
    // post-grid's excludeCurrentPost uses get_the_ID(), which must resolve to the
    // page being paginated — without this the AJAX re-render excludes a different
    // (or no) post than the first render, shifting the pagination window by one.
    //
    // Snapshot and restore the previous global $post explicitly: wp_reset_postdata()
    // restores from $wp_query->post, which is empty in a REST request, so it would
    // leave the paginated page as the global $post for later hooks (e.g.
    // rest_post_dispatch). The try/finally guarantees the restore even if
    // render_block() throws.
    $previous_post = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = $post;
    setup_postdata($post);
    try {
      $rendered = render_block($block);
    } finally {
      $GLOBALS['post'] = $previous_post;
      if ($previous_post instanceof \WP_Post) {
        setup_postdata($previous_post);
      } else {
        wp_reset_postdata();
      }
    }

    $response_data = [
      'status' => 'success',
      'html' => $rendered
    ];

    $response = new \WP_REST_Response($response_data, 200);
    return $response;
  }
}