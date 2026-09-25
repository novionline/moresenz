<?php

/**
 * @param string $hash
 */

//handle default param values
if (!isset($hash)) $hash = '';

$paginationLinksArgs = [
    'mid_size' => 1,
    'end_size' => 0,
    'prev_text' => '<svg class="icon"><use xlink:href="' . ICON_PATH . 'icon-arrow-left"/></svg>',
    'next_text' => '<svg class="icon"><use xlink:href="' . ICON_PATH . 'icon-arrow-right"/></svg>',
    'type' => 'plain'
];

//handle hash
if (str_contains($hash, '#')) $hash = str_replace($hash, '#', '');
if ($hash) $paginationLinksArgs['add_fragment'] = '#' . $hash;

//get array of pagination links
$paginationLinks = paginate_links($paginationLinksArgs);

//replace span for active item to link with different CSS class
$paginationLinks = preg_replace('#<span aria-current="page" class="page-numbers current">(.*)</span>#m', '<a href="#" class="active">$1</a>', $paginationLinks);

?>

<section class="pagination container">
    <?php echo $paginationLinks; ?>
</section>