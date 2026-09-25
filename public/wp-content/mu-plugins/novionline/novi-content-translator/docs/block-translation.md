# Block translation support (Novi Content Translator)

This MU-plugin translates Gutenberg `post_content` by:

- parsing blocks with `parse_blocks()`
- collecting translatable strings using `BlockContentTranslator::$blockConfigs`
- batch translating via DeepL
- applying translations back and serializing with `serialize_blocks()`

## Strategy guide (when adding blocks)

- **html**: safest when user-facing text is stored in `innerHTML` / `innerContent`. DeepL runs in HTML mode and should preserve tags.
- **regex**: use when only specific nodes should be translated (e.g. captions, labels) and translating the full HTML risks touching structural markup.
- **acf_fields**: use for ACF-backed blocks that store strings in `attrs.data` (supports basic strings + link titles + repeaters).
- **callback**: use when text is stored in complex/nested `attrs` structures (arrays/objects), or when multiple distinct fields must be translated and re-applied precisely.

## NectarBlocks

NectarBlocks block names are defined in `public/wp-content/plugins/nectar-blocks/build/blocks/*/block.json`.

### Implemented

- `nectar-blocks/text`: **html** (translates `innerHTML`)

### Next easiest candidates

- `nectar-blocks/button`: **html** (typically contains the visible label in the saved markup)
- `nectar-blocks/icon-list` / `nectar-blocks/icon-list-item`: **html** or **regex** (depending on whether there are multiple items/nested structures in markup)
- `nectar-blocks/accordion` / `nectar-blocks/accordion-section`: likely **html** (translate section titles/content; confirm saved markup vs attrs)

