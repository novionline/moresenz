<?php

/**
 * Bundled demo posts used when the post-grid block is rendered with
 * `useDemoPosts = true` (set by the template library so previews look
 * populated on fresh installs).
 *
 * Images, titles, and categories are sourced from the aura-magazine
 * demo content (nectar-blocks-importer-exporter/.../aura-magazine).
 *
 * Two post-grids on the same template stay visually distinct because the
 * render path slices this array by the block's existing `postOffset` and
 * `postsPerPage` attributes — e.g. a featured post (offset 0, count 1) plus
 * a grid below (offset 1, count 3) automatically pick different items.
 *
 * Excerpts are kept short (~25 words) to match the typical `length` setting
 * on excerpt displayMeta in radnor/aura templates.
 */

return [
  [
    'title' => 'How one sketch a day reshaped my entire creative process',
    'excerpt' => 'A daily ritual that turned out to be less about drawing and more about noticing what was already there.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/alejandro-rosas-__sze47zDwE-unsplash-scaled.webp',
    'taxonomies' => [ 'Culture', 'Wellness' ],
    'author' => 'Mia Chen',
    'date' => '2026-03-15',
    'read_time' => 6,
  ],
  [
    'title' => 'What this designer\'s morning says about her creative process',
    'excerpt' => 'A slow start, two notebooks, and a deliberate refusal to check the phone before noon. Inside a working ritual built around attention.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/bruno-ngarukiye-spT1mnfE_4g-unsplash-1-scaled-e1746067974477.webp',
    'taxonomies' => [ 'Inspiration' ],
    'author' => 'Noor Whitfield',
    'date' => '2026-03-08',
    'read_time' => 5,
  ],
  [
    'title' => 'A beauty routine that made me fall back in love with mornings',
    'excerpt' => 'Five products, three minutes, and an unexpected shift in how I show up to the rest of the day.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/zach-heiberg-Lgate-uRWp4-unsplash-scaled.webp',
    'taxonomies' => [ 'Wellness' ],
    'author' => 'Saoirse Lin',
    'date' => '2026-02-26',
    'read_time' => 4,
  ],
  [
    'title' => 'A look inside the calmest home in Copenhagen',
    'excerpt' => 'No clutter, no statement pieces, no compromises. A long-time stylist walks us through every room of her quietest project yet.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/sana-h-p1dpq9T4WLo-unsplash-scaled.webp',
    'taxonomies' => [ 'Living' ],
    'author' => 'Theo Aalborg',
    'date' => '2026-02-19',
    'read_time' => 8,
  ],
  [
    'title' => 'Where the air begins to thin and the soul rises freely',
    'excerpt' => 'A field guide to the high-altitude towns shaping a new kind of slow travel, from rural Peru to the northern Alps.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/martin-masson-BkAEKU26osY-unsplash-scaled.webp',
    'taxonomies' => [ 'Fashion' ],
    'author' => 'Iris Halberg',
    'date' => '2026-02-11',
    'read_time' => 7,
  ],
  [
    'title' => 'The return of quiet luxury and how to wear it now',
    'excerpt' => 'Less logo, more shape. The wardrobes worth keeping over the next five seasons start with three pieces you already own.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/marcus-santos-xYKimlLuiiE-unsplash-scaled.webp',
    'taxonomies' => [ 'Fashion' ],
    'author' => 'Caro Renoir',
    'date' => '2026-02-02',
    'read_time' => 5,
  ],
  [
    'title' => 'A new wave of travel that\'s more mindful',
    'excerpt' => 'How a generation of slow-travel converts is reshaping where we go, how long we stay, and what we actually do when we arrive.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/pexels-zeke-syrcle-2150599202-31225680-scaled.webp',
    'taxonomies' => [ 'Inspiration' ],
    'author' => 'June Park',
    'date' => '2026-01-24',
    'read_time' => 6,
  ],
  [
    'title' => 'Inside the creative rituals of legendary designers',
    'excerpt' => 'Five working artists on the unglamorous practices behind their best-known work — and the small daily choices that compound.',
    'image' => 'https://demos.nectarblocks.com/aura-magazine/wp-content/uploads/sites/9/2025/04/aura2.webp',
    'taxonomies' => [ 'Wellness' ],
    'author' => 'Atlas Greene',
    'date' => '2026-01-16',
    'read_time' => 9,
  ],
];
