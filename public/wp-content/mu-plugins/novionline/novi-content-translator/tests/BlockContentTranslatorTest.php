<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\GravityFormsTranslator;
use NoviOnline\ContentTranslator\Core\InternalLinkTranslator;
use NoviOnline\ContentTranslator\Core\StringOverrules;
use PHPUnit\Framework\TestCase;

class BlockContentTranslatorTest extends TestCase
{
    private function loadFixture(string $name): string
    {
        $path = __DIR__ . '/fixtures/' . $name;
        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'Fixture not found: ' . $path);
        return $contents;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_pll_term_map'] = [];
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_permalink_map'] = [];
        $GLOBALS['__nct_home_url'] = 'https://example.test/';
        InternalLinkTranslator::resetRuntimeCache();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($GLOBALS['__nct_default_test_translator']) && is_callable($GLOBALS['__nct_default_test_translator'])) {
            DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator']);
        }
    }

    public function testNectarTextTranslatesAttrsAndKeepsWrapperClasses(): void
    {
        $content = $this->loadFixture('nectar-text-basic.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('Welkom bij Datalyzer', $translated);

        //typography class must be preserved for validation
        $this->assertMatchesRegularExpression(
            '/<p[^>]+id="block-ldBA39xuZG"[^>]+class="[^"]*\\bnectar-gt-HJZwONsMLg\\b[^"]*"[^>]*>Welkom bij Datalyzer<\\/p>/',
            $translated
        );
    }

    public function testNectarTextWithAnimationPreservesWrapperDataAttributes(): void
    {
        $content = $this->loadFixture('nectar-text-with-animation-attrs.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //content is translated (dictionary)
        $this->assertStringContainsString('Welkom bij Datalyzer', $translated);

        //animation data attributes are preserved when emit_wrapper rebuilds wrapper
        $this->assertStringContainsString('data-nectar-block-animation="', $translated);
        $this->assertStringContainsString('data-await-in-view-desktop=""', $translated);
        $this->assertStringContainsString('data-await-in-view-tablet=""', $translated);
        $this->assertStringContainsString('data-await-in-view-mobile=""', $translated);
    }

    public function testNectarTextRewritesInternalLinkAndKeepsAttrsInSyncForValidation(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '134|es' => 3995,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            3995 => 'https://example.test/es/pongase-en-contacto-con/',
        ];

        $content = $this->loadFixture('nectar-text-internal-link-with-metadata.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'es');

        $this->assertTrue(has_blocks($translated));
        //inner HTML should be rewritten
        $this->assertStringContainsString('href="https://example.test/es/pongase-en-contacto-con/"', $translated);
        $this->assertStringContainsString('id="3995"', $translated);

        //serialized attrs should be rewritten too (comment JSON), otherwise Gutenberg validation fails
        $this->assertStringContainsString('\u003ca href=\u0022https://example.test/es/pongase-en-contacto-con/\u0022 type=\u0022page\u0022 id=\u00223995\u0022\u003e', $translated);
        $this->assertStringNotContainsString('https://example.test/about-us/contact-us/', $translated);
    }

    public function testNectarTextEscapesLiteralAngleBracketsInContent(): void
    {
        $content = $this->loadFixture('nectar-text-literal-angle-brackets.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //must not emit raw <...> which can be treated as HTML and break validation
        $this->assertStringContainsString('[NL]&lt;Promise on service to customer&gt;', $translated);
        $this->assertStringNotContainsString('<Promise on service to customer>', $translated);
    }

    public function testNectarTextEntityEncodedAngleBracketsStayWellFormedAfterTranslation(): void
    {
        $content = $this->loadFixture('nectar-text-entity-encoded-angle-brackets.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //should not end up with &amp;gt (either in markup or in serialized comment attrs json)
        $this->assertStringNotContainsString('&amp;gt', $translated);
        $this->assertStringNotContainsString('\\u0026amp;gt', $translated);
        $this->assertStringContainsString('&lt;Belofte over service aan klant&gt', $translated);
    }

    public function testNectarTextPreservesHighlightAnimationClassesOnWrapper(): void
    {
        $content = $this->loadFixture('nectar-text-highlight-animation-classes.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //wrapper classes from original markup must remain after emit_wrapper rebuild
        $this->assertStringContainsString('has-text-highlight-animation', $translated);
        $this->assertStringContainsString('text-animation--desktop', $translated);
        $this->assertStringContainsString('text-animation--tablet', $translated);
        $this->assertStringContainsString('text-animation--mobile', $translated);

        //data attribute should remain too
        $this->assertStringContainsString('data-text-animation="', $translated);
    }

    public function testNectarTextPreservesInlineMarkTagMarkup(): void
    {
        $content = $this->loadFixture('nectar-text-inline-mark-tag.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // mark tags must not be escaped to &lt;mark ...&gt;
        $this->assertStringContainsString('<mark', $translated);
        $this->assertStringContainsString('</mark>', $translated);
        $this->assertStringNotContainsString('&lt;mark', $translated);
        $this->assertStringNotContainsString('&lt;/mark', $translated);
    }

    public function testNectarRowScrollIntoViewAnimationAttributesRemainAfterNestedTranslation(): void
    {
        $content = $this->loadFixture('nectar-row-scroll-into-view-animation.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('Welkom bij Datalyzer', $translated);

        //row wrapper must keep its animation attributes (we don't rewrite row markup)
        $this->assertStringContainsString('id="block-row-anim-a"', $translated);
        $this->assertStringContainsString('data-nectar-block-animation="', $translated);
        $this->assertStringContainsString('data-await-in-view-desktop=""', $translated);
        $this->assertStringContainsString('data-await-in-view-tablet=""', $translated);
        $this->assertStringContainsString('data-await-in-view-mobile=""', $translated);
    }

    public function testNectarRowScrollPositionAnimationAttributesRemainAfterNestedTranslation(): void
    {
        $content = $this->loadFixture('nectar-row-scroll-position-animation.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('Gecertificeerd', $translated);

        //row wrapper must keep scrollPosition config intact
        $this->assertStringContainsString('id="block-row-anim-b"', $translated);
        $this->assertStringContainsString('data-nectar-block-animation="', $translated);
        $this->assertStringContainsString('&quot;scrollPosition&quot;', $translated);
    }

    public function testWpBlockRefReplacesWhenTranslationExists(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '46|nl' => 99,
        ];

        $content = $this->loadFixture('wp-block-ref-translation-exists.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('<!-- wp:block {"ref":99} /-->', $translated);
    }

    public function testWpBlockRefKeepsOriginalWhenTranslationMissing(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            //explicitly missing 46|nl mapping
        ];

        $content = $this->loadFixture('wp-block-ref-translation-missing.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('<!-- wp:block {"ref":46} /-->', $translated);
    }

    public function testWpBlockRefReplacesFromRuntimeMapWhenPolylangLookupMissing(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            // explicitly missing 46|nl mapping to force runtime map fallback
        ];

        $content = $this->loadFixture('wp-block-ref-translation-missing.txt');
        BlockContentTranslator::setRuntimeReusableRefMap('nl', [46 => 2713]);
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        BlockContentTranslator::clearRuntimeReusableRefMap('nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('<!-- wp:block {"ref":2713} /-->', $translated);
    }

    public function testGetReusableBlockRefsReturnsUniqueSortedRefs(): void
    {
        $content = <<<EOT
<!-- wp:block {"ref":46} /-->
<!-- wp:group -->
<div class="wp-block-group"><!-- wp:block {"ref":99} /--><!-- wp:block {"ref":46} /--></div>
<!-- /wp:group -->
EOT;

        $refs = BlockContentTranslator::getReusableBlockRefs($content);

        $this->assertSame([46, 99], $refs);
    }

    public function testGetReusableBlockRefsSkipsInvalidRefs(): void
    {
        $content = <<<EOT
<!-- wp:block {"ref":"foo"} /-->
<!-- wp:block {"ref":0} /-->
<!-- wp:block {"ref":77} /-->
EOT;

        $refs = BlockContentTranslator::getReusableBlockRefs($content);

        $this->assertSame([77], $refs);
    }

    public function testNectarTaxonomyGridTermIdsReplaceWhenTranslationExists(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [
            '34|nl' => 340,
            '56|nl' => 560,
        ];

        $content = $this->loadFixture('nectar-taxonomy-grid-translation-exists.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomies":[340,560]', $translated);
    }

    public function testNectarTaxonomyGridKeepsOriginalTermIdsWhenTranslationMissing(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [
            //explicitly missing 34|nl and 56|nl mappings
        ];

        $content = $this->loadFixture('nectar-taxonomy-grid-translation-missing.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomies":[34,56]', $translated);
    }

    public function testNectarAccordionSectionTranslatesTitleSpan(): void
    {
        $content = $this->loadFixture('nectar-accordion-section-basic.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('>Verenigde Staten<', $translated);
        $this->assertMatchesRegularExpression(
            '/<span class="nectar-blocks-accordion-section__title__text">Verenigde Staten<\\/span>/',
            $translated
        );
    }

    public function testNectarAccordionMultipleSectionsTranslateEachTitleSpan(): void
    {
        $content = $this->loadFixture('nectar-accordion-multi-section.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //both section titles should be translated and synced into the title__text span
        $this->assertStringContainsString(
            '<span class="nectar-blocks-accordion-section__title__text">Verenigde Staten</span>',
            $translated
        );
        $this->assertStringContainsString(
            '<span class="nectar-blocks-accordion-section__title__text">Gecertificeerd</span>',
            $translated
        );
    }

    public function testNectarAccordionSectionDoesNotLeaveWhitespaceOnlyContentFragments(): void
    {
        $content = $this->loadFixture('nectar-accordion-section-whitespace-in-content.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString(
            '<span class="nectar-blocks-accordion-section__title__text">Verenigde Staten</span>',
            $translated
        );

        // Regression: ensure the inner block stays inside the content wrapper after translation
        // (Block Editor validation is strict about this structure for accordion sections).
        $this->assertMatchesRegularExpression(
            '/nectar-blocks-accordion-section__content[^>]*>[\\s\\S]*<!-- wp:nectar-blocks\\/text[\\s\\S]*<!-- \\/wp:nectar-blocks\\/text -->[\\s\\S]*<\\/div><\\/div>\\s*<!-- \\/wp:nectar-blocks\\/accordion-section -->/',
            $translated
        );
    }

    public function testNectarAccordionSectionCollapsesWhitespaceWhenContentIsEmpty(): void
    {
        $content = $this->loadFixture('nectar-accordion-section-empty-content-with-whitespace.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString(
            '<span class="nectar-blocks-accordion-section__title__text">Verenigde Staten</span>',
            $translated
        );

        // Critical for validation: empty content wrapper should serialize as immediate close (no whitespace).
        $this->assertStringContainsString(
            '<div class="nectar-blocks-accordion-section__content parent-block-block-sec4"></div>',
            $translated
        );
    }

    public function testNectarImageTranslatesAltAndTitle(): void
    {
        $content = $this->loadFixture('nectar-image-alt-title.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // Alt should translate.
        $this->assertStringContainsString('alt="Deze is een alt-tag"', $translated);

        // Title should translate too.
        $this->assertStringContainsString('title="Deze is een titel"', $translated);
    }

    public function testNectarTestimonialTranslatesConfiguredFieldsAndKeepsNameText(): void
    {
        $content = $this->loadFixture('nectar-testimonial-full.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(4, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        // translated attrs
        $this->assertStringContainsString('"quoteText":"DataLyzer is een belangrijke partner voor Philips Healthcare."', $translated);
        $this->assertStringContainsString('"subtitleText":"Yield engineer, Philips"', $translated);
        $this->assertStringContainsString('"alt":"Testimonial portret"', $translated);
        $this->assertStringContainsString('"title":"Testimonial profielfoto"', $translated);

        // name must remain untouched
        $this->assertStringContainsString('"nameText":"Dave Beeren"', $translated);

        // html sync
        $this->assertStringContainsString('>DataLyzer is een belangrijke partner voor Philips Healthcare.</p>', $translated);
        $this->assertStringContainsString('>Yield engineer, Philips</span>', $translated);
        $this->assertStringContainsString('alt="Testimonial portret"', $translated);
        $this->assertStringContainsString('title="Testimonial profielfoto"', $translated);
        $this->assertStringContainsString('>Dave Beeren</span>', $translated);
    }

    public function testNectarTestimonialHandlesImageDisabledWithoutBreakingStructure(): void
    {
        $content = $this->loadFixture('nectar-testimonial-image-disabled.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(4, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        // translated quote + subtitle
        $this->assertStringContainsString('"quoteText":"DataLyzer is een belangrijke partner voor Philips Healthcare."', $translated);
        $this->assertStringContainsString('"subtitleText":"Yield engineer, Philips"', $translated);
        $this->assertStringContainsString('>DataLyzer is een belangrijke partner voor Philips Healthcare.</p>', $translated);
        $this->assertStringContainsString('>Yield engineer, Philips</span>', $translated);

        // name remains unchanged and there is no img in body markup
        $this->assertStringContainsString('"nameText":"Dave Beeren"', $translated);
        $this->assertStringNotContainsString('<img ', $translated);
    }

    public function testNectarTestimonialRewritesInlineLinksInQuoteNameAndSubtitle(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
            'https://example.test/cases/' => 201,
            'https://example.test/cheeses/' => 301,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 1156,
            '201|nl' => 1201,
            '301|nl' => 1301,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            1156 => 'https://example.test/nl/over-ons/',
            1201 => 'https://example.test/nl/cases-nl/',
            1301 => 'https://example.test/nl/kazen/',
        ];

        $content = $this->loadFixture('nectar-testimonial-inline-links.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // quoteText anchors rewritten (absolute + relative)
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('href="/nl/cases-nl/"', $translated);
        $this->assertStringNotContainsString('href="https://example.test/about-us/"', $translated);
        $this->assertStringNotContainsString('href="/cases/"', $translated);

        // nameText anchor rewritten even though nameText is not translated
        $this->assertStringContainsString('RobbertJan <a href="/nl/kazen/">', $translated);
        $this->assertStringNotContainsString('RobbertJan <a href="/cheeses/">', $translated);
    }

    public function testCoreParagraphAndListRewriteMultipleInternalLinksAndSkipHashLinks(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            // absolute
            'https://example.test/about-us/' => 156,
            'https://example.test/cheeses/' => 301,
            // sometimes link normalizers pass relative through
            '/cheeses/' => 301,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 1156,
            '301|nl' => 1301,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            1156 => 'https://example.test/nl/over-ons/',
            1301 => 'https://example.test/nl/kazen/',
        ];

        $content = $this->loadFixture('core-paragraph-and-list-multiple-links.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // about-us: absolute stays absolute, but language switched
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringNotContainsString('href="https://example.test/about-us/"', $translated);

        // cheeses: source was relative, keep relative style
        $this->assertStringContainsString('href="/nl/kazen/"', $translated);
        $this->assertStringNotContainsString('href="/cheeses/"', $translated);

        // hash links must never be translated
        $this->assertStringContainsString('href="#datalyzer-test-123"', $translated);
    }

    public function testNectarAccordionSectionKeepsInnerBlocksInsideContentWrapper(): void
    {
        $content = $this->loadFixture('nectar-accordion-section-realistic-multi-innerblocks.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // Title must be translated.
        $this->assertStringContainsString(
            '<span class="nectar-blocks-accordion-section__title__text">Verenigde Staten</span>',
            $translated
        );

        // Regression: inner blocks must remain inside the content wrapper, not after it.
        $this->assertStringNotContainsString(
            '</div></div><!-- wp:nectar-blocks/text',
            str_replace(["\n", "\r", "\t", ' '], '', $translated)
        );
        $this->assertMatchesRegularExpression(
            '/nectar-blocks-accordion-section__content[^"]*parent-block-block-46pg8hg6q6ha[^"]*">\\s*<!--\\s*wp:nectar-blocks\\/text\\b/',
            $translated
        );
    }

    public function testAcfNoviMenuTranslatesHeadingField(): void
    {
        $content = $this->loadFixture('acf-novi-menu-heading.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"heading":"Dit is een menu kop"', $translated);
    }

    public function testNectarButtonTranslatesLabelSpan(): void
    {
        $content = $this->loadFixture('nectar-button-basic.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('<span class="nectar-blocks-button__text">Knop</span>', $translated);
    }

    public function testNectarButtonRewritesInternalHrefInAttrsAndMarkup(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/get-a-demo/' => 77,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '77|nl' => 770,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            770 => 'https://example.test/nl/krijg-een-demo/',
        ];

        $content = $this->loadFixture('nectar-button-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // attrs in comment JSON updated
        $this->assertStringContainsString('"value":"https://example.test/nl/krijg-een-demo/"', $translated);
        $this->assertStringNotContainsString('"value":"https://example.test/get-a-demo/"', $translated);

        // markup href updated
        $this->assertStringContainsString('href="https://example.test/nl/krijg-een-demo/"', $translated);
        $this->assertStringNotContainsString('href="https://example.test/get-a-demo/"', $translated);

        // label still translated
        $this->assertStringContainsString('<span class="nectar-blocks-button__text">Plan een demo</span>', $translated);
    }

    public function testNectarButtonRewritesInternalHrefAndPreservesQueryAndFragment(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/get-a-demo/' => 77,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '77|nl' => 770,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            770 => 'https://example.test/nl/krijg-een-demo/',
        ];

        $content = str_replace(
            'https://example.test/get-a-demo/',
            'https://example.test/get-a-demo/?src=cta#demo',
            $this->loadFixture('nectar-button-internal-link.txt')
        );

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('https://example.test/nl/krijg-een-demo/?src=cta#demo', $translated);
        $this->assertStringNotContainsString('https://example.test/get-a-demo/?src=cta#demo', $translated);
    }

    public function testCoreParagraphTranslatesHtmlContent(): void
    {
        $content = $this->loadFixture('core-paragraph-basic.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(1, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString(
            'Dit beleid biedt het kader voor een veilige en veerkrachtige omgeving die vertrouwen, naleving en operationele excellentie waarborgt.',
            $translated
        );
        $this->assertMatchesRegularExpression('/<!-- wp:paragraph -->\\s*<p>.*<\\/p>\\s*<!-- \\/wp:paragraph -->/s', $translated);
    }

    public function testCoreListTranslatesNestedListItemsAndKeepsStructure(): void
    {
        $content = $this->loadFixture('core-list-with-items.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(3, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        $this->assertStringContainsString(
            'Doelstellingen voor informatiebeveiliging worden vastgesteld, gemonitord en regelmatig herzien ter ondersteuning van onze bedrijfsdoelen.',
            $translated
        );
        $this->assertStringContainsString(
            'Alle toepasselijke wettelijke, regelgevende, contractuele en stakeholdervereisten worden geïdentificeerd en nageleefd.',
            $translated
        );
        $this->assertStringContainsString(
            'Risico’s voor informatie-assets worden systematisch beoordeeld en beheerd met passende beheersmaatregelen.',
            $translated
        );

        // Regression: keep list block + list-item structure intact after translation.
        $this->assertMatchesRegularExpression('/<!-- wp:list -->[\\s\\S]*<!-- wp:list-item -->[\\s\\S]*<!-- \\/wp:list-item -->[\\s\\S]*<!-- \\/wp:list -->/', $translated);
    }

    public function testAccordionCoreListItemRewritesInternalHrefInsideAccordionContent(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];

        $content = $this->loadFixture('nectar-accordion-core-list-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertMatchesRegularExpression('/href="(?:https:\\/\\/example\\.test)?\\/nl\\/over-ons\\/?"/', $translated);
        $this->assertStringNotContainsString('href="/about-us/"', $translated);
    }

    public function testNectarIconListItemKeepsContentWrapperForDigitLeadingTitle(): void
    {
        $content = $this->loadFixture('nectar-icon-list-item-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //must retain the content div wrapper (regression for preg_replace backref ambiguity)
        $this->assertMatchesRegularExpression(
            '/<div class="nectar-blocks-icon-list-item__content">\\[NL\\]040 998 772 771<\\/div>/',
            $translated
        );
    }

    public function testNectarIconListItemFooterMailtoAndLinkedInKeepLabelsEnToDe(): void
    {
        //brand needle in mailto/LinkedIn href must not empty icon-list title labels (DE footer)
        StringOverrules::setRulesOverride([
            'en' => [
                [
                    'source' => '2bhonest',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                    'target' => '2bhonest',
                ],
            ],
        ]);
        StringOverrules::setAutoTitlesOverride([]);

        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $isHtml = (($options['tag_handling'] ?? '') === 'html') || (($options['context'] ?? '') === 'html');
            $out = [];
            foreach ($texts as $text) {
                if ($isHtml) {
                    $out[] = preg_replace('/(<a\b[^>]*>)(.*?)(<\/a>)/is', '$1$3', (string) $text);
                } else {
                    $out[] = (string) $text;
                }
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        try {
            $content = $this->loadFixture('nectar-icon-list-item-footer-mailto-linkedin.txt');
            $translated = BlockContentTranslator::translatePostContent($content, 'en', 'de');
        } finally {
            StringOverrules::setRulesOverride(null);
            StringOverrules::setAutoTitlesOverride(null);
            DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
        }

        $this->assertTrue(has_blocks($translated));

        $this->assertStringContainsString('href="mailto:info@2bhonest.nl"', $translated);
        $this->assertStringContainsString('>info@2bhonest.nl</a>', $translated);
        $this->assertMatchesRegularExpression(
            '/nectar-blocks-icon-list-item__content"><a href="mailto:info@2bhonest\\.nl">info@2bhonest\\.nl<\\/a><\\/div>/',
            $translated
        );

        $this->assertStringContainsString('href="https://www.linkedin.com/company/2bhonest"', $translated);
        $this->assertStringContainsString('>LinkedIn</a>', $translated);
        $this->assertDoesNotMatchRegularExpression(
            '/mailto:info@2bhonest\\.nl"[^>]*>\\s*<\\/a>/',
            $translated
        );
        $this->assertDoesNotMatchRegularExpression(
            '/linkedin\\.com\\/company\\/2bhonest"[^>]*>\\s*<\\/a>/',
            $translated
        );
    }

    public function testNectarIconListItemTranslatesTitleAndDescription(): void
    {
        $content = $this->loadFixture('nectar-icon-list-item-desc-enabled.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //dictionary mappings
        $this->assertStringContainsString('>Gecertificeerd<', $translated);
        $this->assertStringContainsString('>Vertrouwd door<', $translated);

        //desc wrapper must remain
        $this->assertMatchesRegularExpression(
            '/<span class="nectar-blocks-icon-list-item__desc[^"]*">Vertrouwd door<\\/span>/',
            $translated
        );
    }

    public function testNectarIconListItemRewritesInternalHrefInAttrsAndMarkup(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/jobs/' => 134,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '134|nl' => 9134,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9134 => 'https://example.test/nl/over-ons/vacatures/',
        ];

        $content = $this->loadFixture('nectar-icon-list-item-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // attrs in comment JSON updated
        $this->assertStringContainsString('"value":"https://example.test/nl/over-ons/vacatures/"', $translated);
        $this->assertStringNotContainsString('"value":"https://example.test/about-us/jobs/"', $translated);

        // markup href updated
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/vacatures/"', $translated);
        $this->assertStringNotContainsString('href="https://example.test/about-us/jobs/"', $translated);
    }

    public function testNectarIconListItemRewritesLegacyStringHrefFormat(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/jobs/' => 134,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '134|nl' => 9134,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9134 => 'https://example.test/nl/over-ons/vacatures/',
        ];

        $content = str_replace(
            '"href":{"value":"https://example.test/about-us/jobs/","type":"core"}',
            '"href":"https://example.test/about-us/jobs/"',
            $this->loadFixture('nectar-icon-list-item-internal-link.txt')
        );

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"href":"https://example.test/nl/over-ons/vacatures/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/vacatures/"', $translated);
        $this->assertStringNotContainsString('"href":"https://example.test/about-us/jobs/"', $translated);
    }

    public function testNectarIconListItemRewritesAnchorsInsideTitleHtml(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/artikelen/lca-pcf/' => 200,
            'https://example.test/artikelen/lca-pcf' => 200,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '200|en' => 1200,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            1200 => 'https://example.test/en/articles/lca-pcf/',
        ];

        $content = $this->loadFixture('nectar-icon-list-item-title-anchor.txt');
        $out = BlockContentTranslator::syncLinksOnly($content, 'nl', 'en');

        $this->assertTrue(has_blocks($out));
        $this->assertStringContainsString('https://example.test/en/articles/lca-pcf/', $out);
        $this->assertStringNotContainsString('https://example.test/artikelen/lca-pcf/', $out);
    }

    public function testTranslatePostContentNormalizesLostBackslashUnicodeEscapesInSerializedOutput(): void
    {
        // Simulate a DeepL output where JSON unicode escapes lost their backslashes:
        // "\u003cbr\u003e" becomes literal "u003cbru003e", "&" becomes "u0026".
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $out = [];
            foreach ($texts as $t) {
                if (str_contains($t, 'More than just software')) {
                    $out[] = 'Meer dan alleen software:u003cbru003eGage Management u0026 MSA.u003cbru003e40+ jaar.';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $content = <<<EOT
<!-- wp:nectar-blocks/text {"blockId":"block-x","content":"More than just software:<br>Gage Management &amp; MSA.<br>40+ years.","textElement":"p","typography":"body"} -->
<p id="block-x" class="wp-block-nectar-blocks-text nectar-blocks-text">More than just software:<br>Gage Management &amp; MSA.<br>40+ years.</p>
<!-- /wp:nectar-blocks/text -->
EOT;

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        // Allow valid JSON escapes (e.g. "\u003cbr\u003e") but forbid the broken form ("u003cbr").
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)u003cbr/i', $translated);
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)u0026/i', $translated);
        $this->assertStringContainsString('<br>', $translated);
        $this->assertStringContainsString('&', $translated);
    }

    public function testHasAndRepairLostUnicodeEscapeBackslashes(): void
    {
        $good = '{"content":"\u003cem\u003eHi\u003c/em\u003e","svg":"\u003csvg viewBox=\u00220 0 24 24\u0022\u003e"}';
        $broken = stripslashes($good);

        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($good));
        $this->assertTrue(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($broken));
        $this->assertStringContainsString('u003c', $broken);
        $this->assertStringNotContainsString('\\u003c', $broken);

        $repaired = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($broken);
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($repaired));
        $this->assertStringContainsString('\\u003c', $repaired);
        $this->assertStringContainsString('\\u0022', $repaired);
        //must not decode u0022 into a raw quote (that would break JSON)
        $this->assertMatchesRegularExpression('/"svg":"\\\\u003csvg viewBox=\\\\u00220 0 24 24\\\\u0022/', $repaired);
    }

    public function testHasLostUnicodeEscapesIgnoresProseAndBlockIds(): void
    {
        //houdbaar contains "udbaa"; block IDs often contain u+4hex — must not flag or mutate those
        $safe = '<!-- wp:nectar-blocks/icon-list-item {"blockId":"block-u605bmft2ht1","title":"houdbaar – impact"} /-->'
            . "\n"
            . '<!-- wp:nectar-blocks/flex-box {"blockId":"block-8h5suc34bk"} /-->';

        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($safe));
        $this->assertSame($safe, BlockContentTranslator::repairLostUnicodeEscapeBackslashes($safe));
    }

    public function testRepairRestoresHyphenEscapesInUrlsWithoutTouchingBlockIds(): void
    {
        $broken = '<!-- wp:nectar-blocks/image {"blockId":"block-u605bmft2ht1","url":"https://example.test/2BH-kernwaardenu002du002d150x150.png"} /-->';
        $this->assertTrue(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($broken));

        $repaired = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($broken);
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($repaired));
        $this->assertStringContainsString('block-u605bmft2ht1', $repaired);
        $this->assertStringContainsString('kernwaarden\\u002d\\u002d150x150.png', $repaired);
        $this->assertStringNotContainsString('kernwaardenu002du002d', $repaired);
    }

    public function testCountLostUnicodeEscapeSymptoms(): void
    {
        $broken = '"content":"u003cstrongu003eHiu003c/strongu003e","url":"...u002du002d.png","svg":"u003csvg viewBox=u00220u0022"';
        $counts = BlockContentTranslator::countLostUnicodeEscapeSymptoms($broken);
        $this->assertSame(3, $counts['u003c']);
        $this->assertSame(2, $counts['u003e']);
        $this->assertSame(2, $counts['u0022']);
        $this->assertSame(2, $counts['u002d']);
        $this->assertSame(0, $counts['u0026']);
    }

    public function testFooterLikeUnicodeEscapesSurviveTranslationAndStripslashesRepair(): void
    {
        DeepLTranslator::setTestTranslator(function (array $texts): array {
            $out = [];
            foreach ($texts as $t) {
                $out[] = str_replace(
                    ['Echte vooruitgang', 'duurzaamheid', 'LinkedIn'],
                    ['Genuine progress', 'sustainability', 'LinkedIn'],
                    $t
                );
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $content = $this->loadFixture('nectar-footer-unicode-escapes.txt');
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($content));

        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertFalse(
            BlockContentTranslator::hasLostUnicodeEscapeBackslashes($translated),
            'translated serialized blocks must not contain lost \\uXXXX backslashes'
        );
        $this->assertStringContainsString('Genuine progress', $translated);
        //svg attribute must keep JSON-safe escapes (backslash present before u0022)
        $this->assertMatchesRegularExpression('/"svg":"[^"]*\\\\u0022/', $translated);
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)u0022/', $translated);

        //simulate the bug: wp_update_post without wp_slash → wp_unslash/stripslashes eats \u backslashes
        $corrupted = stripslashes($translated);
        $this->assertTrue(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($corrupted));
        $this->assertMatchesRegularExpression('/"content":"u003c/i', $corrupted);

        $repaired = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($corrupted);
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($repaired));
        $this->assertMatchesRegularExpression('/"content":"\\\\u003c/i', $repaired);
        $this->assertStringContainsString('\\u0022', $repaired);
    }

    public function testNectarIconRewritesInternalHrefInAttrsAndMarkupForAbsoluteAndRelativeUrls(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];
        $GLOBALS['__nct_pll_translated_slugs'] = [
            'slug_archive_cheese' => [
                'slug' => 'cheeses',
                'translations' => [
                    'nl' => 'kazen',
                ],
            ],
        ];

        $content = $this->loadFixture('nectar-icon-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // First icon (absolute URL) rewritten in attrs + markup.
        $this->assertStringContainsString('"value":"https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringNotContainsString('"value":"https://example.test/about-us/"', $translated);

        // Second icon (relative URL) rewritten with slug fallback in attrs + markup.
        $this->assertStringContainsString('"value":"/nl/kazen/"', $translated);
        $this->assertStringContainsString('href="/nl/kazen/"', $translated);
        $this->assertStringNotContainsString('"value":"/cheeses/"', $translated);
    }

    public function testNectarIconRewritesLegacyStringHrefFormat(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];

        $content = str_replace(
            '"href":{"value":"https://example.test/about-us/","type":"core"}',
            '"href":"https://example.test/about-us/"',
            $this->loadFixture('nectar-icon-internal-link.txt')
        );

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"href":"https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringNotContainsString('"href":"https://example.test/about-us/"', $translated);
    }

    public function testNectarIconDoesNotInjectImgTitleAttribute(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];

        $content = $this->loadFixture('nectar-icon-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringNotContainsString('<img title=', $translated);
    }

    public function testNectarFlexBoxRewritesInternalHrefInAttrsAndMarkupForAbsoluteAndRelativeUrls(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];
        $GLOBALS['__nct_pll_translated_slugs'] = [
            'slug_archive_cheese' => [
                'slug' => 'cheeses',
                'translations' => [
                    'nl' => 'kazen',
                ],
            ],
        ];

        $content = $this->loadFixture('nectar-flex-box-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"value":"https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('"value":"/nl/kazen/"', $translated);
        $this->assertStringContainsString('href="/nl/kazen/"', $translated);
    }

    public function testNectarFlexBoxRewritesLegacyStringHrefFormat(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];

        $content = str_replace(
            '"href":{"value":"https://example.test/about-us/","type":"core"}',
            '"href":"https://example.test/about-us/"',
            $this->loadFixture('nectar-flex-box-internal-link.txt')
        );

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"href":"https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringNotContainsString('"href":"https://example.test/about-us/"', $translated);
    }

    public function testNectarFlexBoxWithInnerBlocksRewritesHrefInAttrsAndMarkup(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/' => 156,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '156|nl' => 9156,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            9156 => 'https://example.test/nl/over-ons/',
        ];

        $content = $this->loadFixture('nectar-flex-box-innerblocks-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"value":"https://example.test/nl/over-ons/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/"', $translated);
        $this->assertStringNotContainsString('href="https://example.test/about-us/"', $translated);
    }

    public function testNectarButtonLeavesExternalHrefUnchanged(): void
    {
        $content = str_replace(
            'https://example.test/get-a-demo/',
            'https://external.example/path?utm=1#frag',
            $this->loadFixture('nectar-button-internal-link.txt')
        );

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"value":"https://external.example/path?utm=1#frag"', $translated);
        $this->assertStringContainsString('href="https://external.example/path?utm=1#frag"', $translated);
    }

    public function testNectarImageRewritesInternalHrefInAttrsAndMarkup(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/contact-us/' => 134,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '134|nl' => 3994,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            3994 => 'https://example.test/nl/over-ons/neem-contact-op-met/',
        ];

        $content = $this->loadFixture('nectar-image-internal-link.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"value":"https://example.test/nl/over-ons/neem-contact-op-met/"', $translated);
        $this->assertStringContainsString('href="https://example.test/nl/over-ons/neem-contact-op-met/"', $translated);
        $this->assertStringNotContainsString('https://example.test/about-us/contact-us/', $translated);
    }

    public function testNectarImageRewritesInternalHrefPreservingQueryAndFragment(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/about-us/contact-us/' => 134,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '134|nl' => 3994,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            3994 => 'https://example.test/nl/over-ons/neem-contact-op-met/',
        ];

        $content = str_replace(
            'https://example.test/about-us/contact-us/',
            'https://example.test/about-us/contact-us/?src=img#hero',
            $this->loadFixture('nectar-image-internal-link.txt')
        );
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('https://example.test/nl/over-ons/neem-contact-op-met/?src=img#hero', $translated);
        $this->assertStringNotContainsString('https://example.test/about-us/contact-us/?src=img#hero', $translated);
    }

    public function testPageSnippetCoversNestingInlineTagsAndMultipleBlocks(): void
    {
        $content = $this->loadFixture('nectar-page-snippet-so-far.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //text: typography class must remain + dictionary translation applied
        $this->assertMatchesRegularExpression(
            '/<p[^>]+id="block-ldBA39xuZG"[^>]+class="[^"]*\\bnectar-gt-HJZwONsMLg\\b[^"]*"[^>]*>Welkom bij Datalyzer<\\/p>/',
            $translated
        );

        //text: inline tags preserved inside translated paragraph (fallback prefixes are fine)
        $this->assertStringContainsString('<a href="#">deliver</a>', $translated);
        $this->assertStringContainsString('<strong>implement</strong>', $translated);
        $this->assertStringContainsString('<em>software</em>', $translated);
        $this->assertStringContainsString('nectar-font-body', $translated);

        //icon-list-item: keep content wrapper for digit-leading phone number
        $this->assertMatchesRegularExpression(
            '/<div class="nectar-blocks-icon-list-item__content">\\[NL\\]040 998 772 771<\\/div>/',
            $translated
        );

        //button: label translated via dictionary and wrapper span intact
        $this->assertStringContainsString('<span class="nectar-blocks-button__text">Plan een demo</span>', $translated);
    }

    public function testNectarScrollingMarqueeTextOnlyTranslatesAttrsAndBothRows(): void
    {
        $content = $this->loadFixture('nectar-scrolling-marquee-text-only.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"text":"Farmacie"', $translated);
        $this->assertStringContainsString('"text":"Voeding \\u0026 Drank"', $translated);
        $this->assertStringContainsString('"text":"Luchtvaart"', $translated);

        //visible marquee row
        $this->assertStringContainsString('<div>Farmacie</div>', $translated);
        $this->assertStringContainsString('<div>Voeding &amp; Drank</div>', $translated);
        $this->assertStringContainsString('<div>Luchtvaart</div>', $translated);

        //mirrored accessibility row
        $this->assertStringContainsString('<span aria-hidden="true" role="presentation">Farmacie</span>', $translated);
        $this->assertStringContainsString('<span aria-hidden="true" role="presentation">Voeding &amp; Drank</span>', $translated);
        $this->assertStringContainsString('<span aria-hidden="true" role="presentation">Luchtvaart</span>', $translated);
    }

    public function testNectarScrollingMarqueeMixedRepeaterTranslatesTextAndImageMeta(): void
    {
        $content = $this->loadFixture('nectar-scrolling-marquee-mixed-text-image.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //text items translated
        $this->assertStringContainsString('"text":"Automotive"', $translated);
        $this->assertStringContainsString('"text":"Elektronica"', $translated);
        $this->assertStringContainsString('<div>Automotive</div>', $translated);
        $this->assertStringContainsString('<div>Elektronica</div>', $translated);
        $this->assertStringContainsString('<span aria-hidden="true" role="presentation">Automotive</span>', $translated);
        $this->assertStringContainsString('<span aria-hidden="true" role="presentation">Elektronica</span>', $translated);

        //image metadata translated in attrs and synced to <img>
        $this->assertStringContainsString('"alt":"Fabriek pictogram"', $translated);
        $this->assertStringContainsString('"title":"Fabriekslabel"', $translated);
        $this->assertMatchesRegularExpression('/<img[^>]*alt="Fabriek pictogram"[^>]*title="Fabriekslabel"[^>]*>/', $translated);
    }

    public function testNectarScrollingMarqueeSkipsEmptyValuesAndCountsOnlyNonEmptyStrings(): void
    {
        $content = $this->loadFixture('nectar-scrolling-marquee-empty-values.txt');

        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(1, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        //only the non-empty text entry should be translated
        $this->assertStringContainsString('"text":"Luchtvaart"', $translated);

        //empty image meta remains empty
        $this->assertStringContainsString('"alt":""', $translated);
        $this->assertStringContainsString('"title":""', $translated);
        $this->assertMatchesRegularExpression('/<img[^>]*alt=""[^>]*title=""[^>]*>/', $translated);
    }

    public function testNectarScrollingMarqueeImageTitlesStayInSyncAcrossBothRows(): void
    {
        $content = $this->loadFixture('nectar-scrolling-marquee-image-row-parity.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        //image titles in attrs are translated
        $this->assertStringContainsString('"title":"[NL]Mars-300x300"', $translated);
        $this->assertStringContainsString('"title":"[NL]Ford-300x300"', $translated);

        //both marquee rows must have the same translated title values (no stale old values)
        $this->assertSame(2, preg_match_all('/title="\\[NL\\]Mars-300x300"/', $translated));
        $this->assertSame(2, preg_match_all('/title="\\[NL\\]Ford-300x300"/', $translated));
        $this->assertStringNotContainsString('title="Mars-300x300"', $translated);
        $this->assertStringNotContainsString('title="Ford-300x300"', $translated);
    }

    public function testNectarScrollingMarqueeDecodesEntityEncodedTextWithoutDoubleEscaping(): void
    {
        $content = $this->loadFixture('nectar-scrolling-marquee-entity-encoded-text.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'de');

        $this->assertTrue(has_blocks($translated));

        //attrs should no longer keep encoded entity text after translation normalization
        $this->assertStringContainsString('"text":"[NL]Food \\u0026 Beverage"', $translated);

        //markup must contain a single escaped ampersand and never double-escaped output
        $this->assertStringContainsString('>[NL]Food &amp; Beverage<', $translated);
        $this->assertStringNotContainsString('&amp;amp;', $translated);
    }

    public function testNectarScrollingMarqueeRewritesInternalLinkFieldInRepeaterItems(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/get-a-demo/' => 77,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '77|nl' => 770,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            770 => 'https://example.test/nl/krijg-een-demo/',
        ];

        $content = $this->loadFixture('nectar-scrolling-marquee-link-field.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // marquee text still translated + synced
        $this->assertStringContainsString('"text":"Farmacie"', $translated);
        $this->assertStringContainsString('<div>Farmacie</div>', $translated);

        // href field in attrs updated
        $this->assertStringContainsString('"value":"https://example.test/nl/krijg-een-demo/"', $translated);
        $this->assertStringNotContainsString('"value":"https://example.test/get-a-demo/"', $translated);

        // markup href updated (when marquee uses <a> wrapper)
        $this->assertStringContainsString('href="https://example.test/nl/krijg-een-demo/"', $translated);
        $this->assertStringNotContainsString('href="https://example.test/get-a-demo/"', $translated);
    }

    public function testNectarImageGridTranslatesMetadataAndSyncsMarkup(): void
    {
        $content = $this->loadFixture('nectar-image-grid-metadata.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(4, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        // attrs translation for all four fields
        $this->assertStringContainsString('"alt":"Raster alt een"', $translated);
        $this->assertStringContainsString('"caption":"Raster bijschrift een"', $translated);
        $this->assertStringContainsString('"description":"Raster beschrijving een"', $translated);
        $this->assertStringContainsString('"title":"Raster titel een"', $translated);

        // markup sync
        $this->assertStringContainsString('data-sub-html="Raster beschrijving een"', $translated);
        $this->assertStringContainsString('alt="Raster alt een"', $translated);
        $this->assertStringContainsString('>Raster titel een</h3>', $translated);

        // empty second item remains empty
        $this->assertStringContainsString('"alt":""', $translated);
        $this->assertStringContainsString('"title":""', $translated);
    }

    public function testNectarImageGalleryTranslatesMetadataAndSyncsAltMarkup(): void
    {
        $content = $this->loadFixture('nectar-image-gallery-metadata.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(4, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        // attrs translation for all four fields
        $this->assertStringContainsString('"alt":"Galerij alt een"', $translated);
        $this->assertStringContainsString('"caption":"Galerij bijschrift een"', $translated);
        $this->assertStringContainsString('"description":"Galerij beschrijving een"', $translated);
        $this->assertStringContainsString('"title":"Galerij titel een"', $translated);

        // markup sync for rendered slides
        $this->assertStringContainsString('<img src="https://example.test/gallery-1.jpg" alt="Galerij alt een">', $translated);
        $this->assertStringContainsString('<img src="https://example.test/gallery-2.jpg" alt="">', $translated);
    }

    public function testNectarTabsTranslatesTabItemsAndSyncsNavMarkup(): void
    {
        $content = $this->loadFixture('nectar-tabs-items-metadata.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(4, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        // attrs translation
        $this->assertStringContainsString('"label":"SPC module"', $translated);
        $this->assertStringContainsString('"description":"Problemen vroeg detecteren"', $translated);
        $this->assertStringContainsString('"alt":"Diagram pictogram"', $translated);
        $this->assertStringContainsString('"label":"FMEA module"', $translated);

        // html sync
        $this->assertStringContainsString('<span class="nectar-blocks-tabs__nav__link__title">SPC module</span>', $translated);
        $this->assertStringContainsString('<span class="nectar-blocks-tabs__nav__link__desc nectar-font-body">Problemen vroeg detecteren</span>', $translated);
        $this->assertStringContainsString('alt="Diagram pictogram"', $translated);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*\\stitle="[^"]*"/', $translated);

        // empty description/icon meta stay empty for second item
        $this->assertStringContainsString('"description":""', $translated);
        $this->assertStringContainsString('alt=""', $translated);
        $this->assertStringContainsString('"title":""', $translated);
    }

    public function testNectarTabSectionHasNoDirectStringsButInnerBlocksStillTranslate(): void
    {
        $content = $this->loadFixture('nectar-tab-section-inner-content-only.txt');
        $count = BlockContentTranslator::countTranslatableBlockStrings($content);
        $this->assertSame(1, $count);

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $this->assertTrue(has_blocks($translated));

        // tab-section itself is passthrough, while nested text block keeps translating.
        $this->assertStringContainsString('id="block-tab-section-a"', $translated);
        $this->assertStringContainsString('Welkom bij Datalyzer', $translated);
    }

    public function testNectarTabsWithInnerBlocksSyncsNavAndKeepsNestedBlocks(): void
    {
        $content = $this->loadFixture('nectar-tabs-with-innerblocks.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));

        // nav text must be synced in body html (regression for tabs with innerBlocks)
        $this->assertStringContainsString('<span class="nectar-blocks-tabs__nav__link__title">SPC module</span>', $translated);
        $this->assertStringContainsString('<span class="nectar-blocks-tabs__nav__link__desc nectar-font-body">Problemen vroeg detecteren</span>', $translated);

        // nested content remains present and still translates
        $this->assertStringContainsString('id="block-tab-section-inner"', $translated);
        $this->assertStringContainsString('Welkom bij Datalyzer', $translated);

        // regression: tab-section blocks must remain inside the tabs wrapper
        $firstTabSectionPos = strpos($translated, '<!-- wp:nectar-blocks/tab-section');
        $tabsClosePos = strpos($translated, '<!-- /wp:nectar-blocks/tabs -->');
        $this->assertNotFalse($firstTabSectionPos);
        $this->assertNotFalse($tabsClosePos);
        $this->assertLessThan($tabsClosePos, $firstTabSectionPos);
    }

    public function testNectarPostGridRemapsTaxonomyTermIdsWhenTranslationExists(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [
            '101|nl' => 201,
            '107|nl' => 207,
        ];

        $content = $this->loadFixture('nectar-post-grid-taxonomies.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomies":[201,207]', $translated);
        $this->assertStringNotContainsString('"taxonomies":[101,107]', $translated);
    }

    public function testNectarPostGridKeepsOriginalTaxonomyTermIdsWhenTranslationMissing(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [];

        $content = $this->loadFixture('nectar-post-grid-taxonomies.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomies":[101,107]', $translated);
    }

    public function testNectarTaxonomyGridRemapsTaxonomyTermIdsWhenTranslationExists(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [
            '34|nl' => 134,
            '55|nl' => 155,
        ];

        $content = $this->loadFixture('nectar-taxonomy-grid-taxonomies.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomies":[134,155]', $translated);
        $this->assertStringNotContainsString('"taxonomies":[34,55]', $translated);
    }

    public function testNectarTaxonomyGridKeepsOriginalTaxonomyTermIdsWhenTranslationMissing(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [];

        $content = $this->loadFixture('nectar-taxonomy-grid-taxonomies.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomies":[34,55]', $translated);
    }

    public function testNectarTaxonomyTermsIsPassthroughForTranslator(): void
    {
        $content = <<<EOT
<!-- wp:nectar-blocks/taxonomy-terms {"blockId":"block-tax-terms-a","taxonomy":"resource-category","enableLink":true,"enableAllLink":true,"displayType":"all_terms"} /-->
EOT;

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"taxonomy":"resource-category"', $translated);
        $this->assertStringContainsString('"enableLink":true', $translated);
        $this->assertStringContainsString('"enableAllLink":true', $translated);
        $this->assertStringContainsString('"displayType":"all_terms"', $translated);
    }

    public function testGetUnsupportedBlockNamesReturnsUniqueUnmappedBlocks(): void
    {
        $content = <<<EOT
<!-- wp:unknown/first --><div>One</div><!-- /wp:unknown/first -->
<!-- wp:unknown/second --><div>Two</div><!-- /wp:unknown/second -->
<!-- wp:unknown/first --><div>Three</div><!-- /wp:unknown/first -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/first', 'unknown/second'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsMappedNoTextBlocks(): void
    {
        $content = <<<EOT
<!-- wp:nectar-blocks/tab-section {"blockId":"block-test"} -->
<div class="wp-block-nectar-blocks-tab-section" id="block-test"></div>
<!-- /wp:nectar-blocks/tab-section -->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsTaxonomyBlocksMappedAsNoText(): void
    {
        $content = <<<EOT
<!-- wp:nectar-blocks/taxonomy-grid {"blockId":"block-a","postType":"resource","taxonomies":[34]} /-->
<!-- wp:nectar-blocks/taxonomy-terms {"blockId":"block-b","taxonomy":"case-category","displayType":"all_terms"} /-->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsTestimonialWhenMapped(): void
    {
        $content = <<<EOT
<!-- wp:nectar-blocks/testimonial {"blockId":"block-testimonial-a","quoteText":"DataLyzer is a key partner for Philips Healthcare.","nameText":"Dave Beeren","subtitleText":"Yield Engineer, Philips","authorImage":{"enabled":true,"image":{"url":"https://example.test/testimonial.jpg","alt":"Testimonial portrait","title":"Testimonial headshot","type":"wpm","id":1267}}} /-->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsWrapperNoTextBlocks(): void
    {
        $content = <<<EOT
<!-- wp:nectar-blocks/accordion {"blockId":"block-acc"} -->
<div class="wp-block-nectar-blocks-accordion" id="block-acc"></div>
<!-- /wp:nectar-blocks/accordion -->
<!-- wp:nectar-blocks/icon-list {"blockId":"block-icon-list"} -->
<div class="wp-block-nectar-blocks-icon-list" id="block-icon-list"></div>
<!-- /wp:nectar-blocks/icon-list -->
<!-- wp:nectar-blocks/post-content {"blockId":"block-post-content"} /-->
<!-- wp:nectar-blocks/post-grid {"blockId":"block-post-grid"} /-->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsGravityFormsAndYoastBreadcrumbs(): void
    {
        $content = <<<EOT
<!-- wp:gravityforms/form {"formId":"1","title":true,"description":true,"ajax":false,"fieldValues":""} /-->
<!-- wp:yoast-seo/breadcrumbs {"className":"custom-breadcrumbs"} /-->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsNoviArticleAuthorsAndAlias(): void
    {
        $content = <<<EOT
<!-- wp:novi/article-authors /-->
<!-- wp:novi/connected-team-members /-->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesFlagsRemovedDatalyzerGrids(): void
    {
        $content = <<<EOT
<!-- wp:acf/block-datalyzer-animated-grid /-->
<!-- wp:acf/block-datalyzer-dots-grid /-->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(
            ['acf/block-datalyzer-animated-grid', 'acf/block-datalyzer-dots-grid'],
            $unsupported
        );
    }

    public function testTranslatePostContentRemapsGravityFormsFormId(): void
    {
        GravityFormsTranslator::setTestFormTitlesById([
            1 => 'Contact - NL',
            14 => 'Contact - EN',
        ]);

        $content = '<!-- wp:gravityforms/form {"formId":"1","title":false,"description":false,"ajax":true} /-->';
        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"formId":"14"', $out);
        $this->assertStringNotContainsString('"formId":"1"', $out);

        GravityFormsTranslator::setTestFormTitlesById(null);
    }

    public function testTranslatePostContentKeepsUnmappedGravityFormsFormId(): void
    {
        GravityFormsTranslator::setTestFormTitlesById([
            1 => 'Contact - NL',
        ]);

        $content = '<!-- wp:gravityforms/form {"formId":"1","title":false} /-->';
        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"formId":"1"', $out);

        GravityFormsTranslator::setTestFormTitlesById(null);
    }

    public function testTranslatePostContentRemapsAcfNoviMenuIdsViaPolylang(): void
    {
        $GLOBALS['__nct_pll_term_map'] = [
            '15|en' => 928,
            '302|en' => 929,
            '303|en' => 930,
        ];

        $content = <<<'EOT'
<!-- wp:acf/block-novi-menu {"name":"acf/block-novi-menu","data":{"heading_type":"no-heading","_heading_type":"field_627a54f212421","menu":"15","_menu":"field_627a1c10765dc"},"mode":"preview"} /-->
<!-- wp:acf/block-novi-menu {"name":"acf/block-novi-menu","data":{"heading_type":"no-heading","_heading_type":"field_627a54f212421","menu":"302","_menu":"field_627a1c10765dc"},"mode":"preview"} /-->
<!-- wp:acf/block-novi-menu {"name":"acf/block-novi-menu","data":{"heading_type":"no-heading","_heading_type":"field_627a54f212421","menu":"303","_menu":"field_627a1c10765dc"},"mode":"preview"} /-->
EOT;

        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"menu":"928"', $out);
        $this->assertStringContainsString('"menu":"929"', $out);
        $this->assertStringContainsString('"menu":"930"', $out);
        $this->assertStringNotContainsString('"menu":"15"', $out);
        $this->assertStringNotContainsString('"menu":"302"', $out);
        $this->assertStringNotContainsString('"menu":"303"', $out);

        unset($GLOBALS['__nct_pll_term_map']);
    }

    public function testGetUnsupportedBlockNamesSkipsCoreParagraphAndListBlocks(): void
    {
        $content = <<<EOT
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>One</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Two</p>
<!-- /wp:paragraph -->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testCoreParagraphAndListItemRemainActiveWhenLegacyNoviBlocksArePresent(): void
    {
        $content = <<<EOT
<!-- wp:paragraph -->
<p>Our policy supports secure operations.</p>
<!-- /wp:paragraph -->
<!-- wp:list-item -->
<li>Policy objective</li>
<!-- /wp:list-item -->
<!-- wp:novionline/paragraph -->
<p>Legacy paragraph</p>
<!-- /wp:novionline/paragraph -->
EOT;

        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');
        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($translated);

        $this->assertStringContainsString('[NL]', $translated);
        $this->assertStringContainsString('<p>Our policy supports secure operations.</p>', $translated);
        $this->assertStringContainsString('<li>Policy objective</li>', $translated);
        $this->assertSame(['novionline/paragraph'], $unsupported);
    }

    public function testGetUnsupportedBlockNamesSkipsCoreBlockReusableRef(): void
    {
        $content = <<<EOT
<!-- wp:block {"ref":46} /-->
<!-- wp:unknown/not-mapped --><div>Missing mapping</div><!-- /wp:unknown/not-mapped -->
EOT;

        $unsupported = BlockContentTranslator::getUnsupportedBlockNames($content);

        $this->assertSame(['unknown/not-mapped'], $unsupported);
    }

    public function testNectarTableOfContentsTranslatesHeadingsAttrsAndLinkText(): void
    {
        $content = $this->loadFixture('nectar-table-of-contents-basic.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertSame([], BlockContentTranslator::getUnsupportedBlockNames($translated));
        $this->assertStringContainsString('[NL]1. Sterkere en waardevollere leveranciersrelaties', $translated);
        $this->assertStringContainsString('href="#block-e537f0d51e">[NL]1. Sterkere en waardevollere leveranciersrelaties', $translated);
        $this->assertStringContainsString('"content":"[NL]1. Sterkere en waardevollere leveranciersrelaties"', $translated);
    }

    public function testNectarVideoPlayerTranslatesPlayButtonLabel(): void
    {
        $content = $this->loadFixture('nectar-video-player-play-label.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('[NL]Nice to meet', $translated);
        $this->assertStringContainsString('nectar-blocks-video-player__center-play-label">[NL]Nice to meet<', $translated);
        $this->assertStringContainsString('"playButtonLabel":"[NL]Nice to meet"', $translated);
    }

    public function testNectarSearchTranslatesPlaceholderAttrAndInput(): void
    {
        $content = $this->loadFixture('nectar-search-placeholder.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"placeholder":"[NL]Zoeken op de site"', $translated);
        //popup mode renders placeholder on multiple inputs — all must stay in sync
        preg_match_all('/placeholder="([^"]*)"/', $translated, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $placeholder) {
            $this->assertSame('[NL]Zoeken op de site', $placeholder);
        }
    }

    public function testNectarIconTranslatesAriaLabelAttrAndMarkup(): void
    {
        $content = $this->loadFixture('nectar-icon-aria-label.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"ariaLabel":"[NL]Icoon label voor toegankelijkheid"', $translated);
        $this->assertStringContainsString('aria-label="[NL]Icoon label voor toegankelijkheid"', $translated);
    }

    public function testNectarMilestoneTranslatesTextAttrAndContent(): void
    {
        $content = $this->loadFixture('nectar-milestone-text.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('"text":"[NL]250+ projecten"', $translated);
        $this->assertStringContainsString('nectar-blocks-milestone__content">[NL]250+ projecten<', $translated);
    }

    public function testNectarVideoLightboxTranslatesTextContent(): void
    {
        $content = $this->loadFixture('nectar-video-lightbox-text.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('[NL]Bekijk de video', $translated);
        $this->assertStringContainsString('nectar-blocks-video-lightbox__play-button__text">[NL]Bekijk de video<', $translated);
    }

    public function testAllNectarBlocksAreSupportedInConfig(): void
    {
        $blockDir = dirname(__DIR__, 4) . '/plugins/nectar-blocks/build/blocks';
        if (!is_dir($blockDir)) {
            $blockDir = '/Users/petertenhoor/sites/2bhonest/public/wp-content/plugins/nectar-blocks/build/blocks';
        }
        $this->assertDirectoryExists($blockDir);

        $configs = BlockContentTranslator::getBlockConfigs();
        $missing = [];
        foreach (scandir($blockDir) as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $name = 'nectar-blocks/' . $slug;
            if (!isset($configs[$name])) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Missing NCT configs for NB blocks: ' . implode(', ', $missing));
    }

    public function testNb3DutchFixturesTranslateWithoutUnsupportedBlocks(): void
    {
        $fixturesDir = __DIR__ . '/fixtures';
        $files = array_merge(
            glob($fixturesDir . '/*-nl.txt') ?: [],
            [
                $fixturesDir . '/nectar-search-placeholder.txt',
                $fixturesDir . '/nectar-milestone-text.txt',
                $fixturesDir . '/nectar-table-of-contents-basic.txt',
                $fixturesDir . '/nectar-video-lightbox-text.txt',
                $fixturesDir . '/nectar-icon-aria-label.txt',
                $fixturesDir . '/nectar-carousel-basic.txt',
                $fixturesDir . '/nectar-divider-basic.txt',
                $fixturesDir . '/nectar-star-rating-basic.txt',
                $fixturesDir . '/nectar-taxonomy-terms-basic.txt',
            ]
        );

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertFileExists($file);
            $content = (string) file_get_contents($file);
            $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');
            $this->assertTrue(has_blocks($translated), basename($file) . ' should remain blocks');
            $this->assertSame(
                [],
                BlockContentTranslator::getUnsupportedBlockNames($translated),
                basename($file) . ' introduced unsupported blocks'
            );
        }
    }

    public function testNectarButtonBasicNlTranslatesLabel(): void
    {
        $content = $this->loadFixture('nectar-button-basic-nl.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');
        $this->assertStringContainsString('"text":"[NL]Ontvang brochure"', $translated);
        $this->assertStringContainsString('nectar-blocks-button__text">[NL]Ontvang brochure<', $translated);
    }

    public function testNectarTestimonialFullNlTranslatesQuoteAndSubtitle(): void
    {
        $content = $this->loadFixture('nectar-testimonial-full-nl.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');
        $this->assertStringContainsString('"quoteText":"[NL]Dit is een testimonial quote."', $translated);
        $this->assertStringContainsString('"subtitleText":"[NL]Functietitel"', $translated);
        $this->assertStringContainsString('"nameText":"Test Persoon"', $translated);
    }

    
    public function testNectarTabsNb3TitleFieldTranslatesAndSyncsNav(): void
    {
        $content = <<<HTML
<!-- wp:nectar-blocks/tabs {"blockId":"nct-tabs-title","tabItems":[{"title":"Tab één","description":"Eerste tabbeschrijving"},{"title":"Tab twee","description":""}]} -->
<div class="wp-block-nectar-blocks-tabs nectar-blocks-tabs" id="nct-tabs-title"><div class="nectar-blocks-tabs__inner"><div class="nectar-blocks-tabs__nav-wrap"><div class="nectar-blocks-tabs__nav parent-block-nct-tabs-title nectar-font-h5"><a href="#nectar-tab-pane-0" role="tab" class="nectar-blocks-tabs__nav__link is-active"><span class="nectar-blocks-tabs__nav__link__text"><span class="nectar-blocks-tabs__nav__link__title">Tab één</span><span class="nectar-blocks-tabs__nav__link__desc nectar-font-body">Eerste tabbeschrijving</span></span></a><a href="#nectar-tab-pane-1" role="tab" class="nectar-blocks-tabs__nav__link"><span class="nectar-blocks-tabs__nav__link__text"><span class="nectar-blocks-tabs__nav__link__title">Tab twee</span><span class="nectar-blocks-tabs__nav__link__desc nectar-font-body"></span></span></a></div></div><div class="nectar-blocks-tabs__content parent-block-nct-tabs-title"></div></div></div>
<!-- /wp:nectar-blocks/tabs -->
HTML;
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');
        $this->assertStringContainsString('"label":"[NL]Tab één"', $translated);
        $this->assertStringNotContainsString('"title":', $translated);
        $this->assertStringContainsString('"description":"[NL]Eerste tabbeschrijving"', $translated);
        $this->assertStringContainsString('<span class="nectar-blocks-tabs__nav__link__title">[NL]Tab één</span>', $translated);
        $this->assertStringContainsString('<span class="nectar-blocks-tabs__nav__link__desc nectar-font-body">[NL]Eerste tabbeschrijving</span>', $translated);
    }

    public function testNectarTextBasicNlTranslatesContent(): void
    {
        $content = $this->loadFixture('nectar-text-basic-nl.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');
        $this->assertStringContainsString('[NL]Inhoudsopgave', $translated);
    }

    public function testNectarTextMapsExternalLinkKeepsAnchorTextWhenDeepLEmptiesIt(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $isHtml = (($options['tag_handling'] ?? '') === 'html') || (($options['context'] ?? '') === 'html');
            $out = [];
            foreach ($texts as $text) {
                $text = (string) $text;
                if ($isHtml) {
                    //simulate DeepL emptying Maps/external link labels in HTML mode
                    $out[] = preg_replace('/(<a\b[^>]*>)(.*?)(<\/a>)/is', '$1$3', $text);
                } else {
                    $out[] = str_replace('Routebeschrijving', 'Directions', $text);
                }
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $content = $this->loadFixture('nectar-text-maps-external-link-nl.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('href="https://maps.app.goo.gl/LjgtLZ3ueRN5o4bA7"', $translated);
        $this->assertStringContainsString('>Directions</a>', $translated);
        $this->assertDoesNotMatchRegularExpression(
            '/maps\\.app\\.goo\\.gl[^"]*"[^>]*>\\s*<\\/a>/',
            $translated
        );
        //attrs.content must stay in sync for Gutenberg validation
        $this->assertStringContainsString(
            'noopener\\u0022\\u003eDirections\\u003c/a\\u003e',
            $translated
        );
    }

    public function testTranslatePostContentRemapsOpenPopupIdAndPopmakeClass(): void
    {
        $GLOBALS['__nct_pll_post_map']['703|en'] = 1703;

        $content = <<<'EOT'
<!-- wp:nectar-blocks/button {"blockId":"block-test","openPopupId":"703","className":"novi-button"} -->
<div class="wp-block-nectar-blocks-button"><a class="nectar-button popmake-703" href="#">Showroom</a></div>
<!-- /wp:nectar-blocks/button -->
EOT;

        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"1703"', $out);
        $this->assertStringNotContainsString('"openPopupId":"703"', $out);
        $this->assertStringContainsString('popmake-1703', $out);
        $this->assertStringNotContainsString('popmake-703', $out);
    }

    public function testSyncLinksOnlyRemapsOpenPopupIdAndPopmakeClass(): void
    {
        $GLOBALS['__nct_pll_post_map']['823|en'] = 1823;

        $content = <<<'EOT'
<!-- wp:nectar-blocks/button {"blockId":"block-test","openPopupId":"823","className":"novi-button popmake-823"} -->
<a class="nectar-button popmake-823" href="#">Brochure</a>
<!-- /wp:nectar-blocks/button -->
EOT;

        $out = BlockContentTranslator::syncLinksOnly($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"1823"', $out);
        $this->assertStringContainsString('popmake-1823', $out);
        $this->assertStringNotContainsString('popmake-823', $out);
        $this->assertStringNotContainsString('"openPopupId":"823"', $out);
    }
}

