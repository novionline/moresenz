<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route};

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
    $rendered = render_block($block);

    $response_data = [
      'status' => 'success',
      'html' => $rendered
    ];

    $response = new \WP_REST_Response($response_data, 200);
    return $response;
  }
}