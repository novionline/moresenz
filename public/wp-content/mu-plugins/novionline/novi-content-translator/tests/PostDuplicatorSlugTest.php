<?php

namespace {
    if (!function_exists('sanitize_title')) {
        //minimal WP slug sanitizer for unit tests
        function sanitize_title(string $title, string $fallbackTitle = '', string $context = 'save'): string
        {
            $title = strtolower(trim($title));
            $title = preg_replace('/[^a-z0-9\s\-]/', '', $title);
            $title = preg_replace('/[\s\-]+/', '-', (string) $title);
            $title = trim((string) $title, '-');
            return $title !== '' ? $title : $fallbackTitle;
        }
    }
}

namespace NoviOnline\ContentTranslator\Tests {
    use NoviOnline\ContentTranslator\Core\DeepLTranslator;
    use NoviOnline\ContentTranslator\Core\PostDuplicator;
    use PHPUnit\Framework\TestCase;

    class PostDuplicatorSlugTest extends TestCase
    {
        protected function tearDown(): void
        {
            DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
            parent::tearDown();
        }

        public function testBuildsSlugFromTranslatedSourcePostNameNotTitle(): void
        {
            DeepLTranslator::setTestTranslator(static function (array $texts): array {
                $map = [
                    'team' => 'team',
                    'over ons' => 'about us',
                ];
                $out = [];
                foreach ($texts as $text) {
                    $key = is_string($text) ? trim($text) : '';
                    $out[] = $map[$key] ?? $key;
                }
                return [
                    'success' => true,
                    'translations' => $out,
                    'error' => null,
                ];
            });

            $this->assertSame(
                'team',
                PostDuplicator::buildTargetPostName('team', 'nl', 'en')
            );
            $this->assertSame(
                'about-us',
                PostDuplicator::buildTargetPostName('over-ons', 'nl', 'en')
            );
        }

        public function testFallsBackToSourceSlugWhenTranslationEmpty(): void
        {
            DeepLTranslator::setTestTranslator(static function (array $texts): array {
                $out = [];
                foreach ($texts as $_) {
                    $out[] = '';
                }
                return [
                    'success' => true,
                    'translations' => $out,
                    'error' => null,
                ];
            });

            $this->assertSame(
                'team',
                PostDuplicator::buildTargetPostName('team', 'nl', 'en')
            );
        }

        public function testSameLanguageKeepsSanitizedSourceSlug(): void
        {
            $called = false;
            DeepLTranslator::setTestTranslator(static function (array $texts) use (&$called): array {
                $called = true;
                return [
                    'success' => true,
                    'translations' => $texts,
                    'error' => null,
                ];
            });

            $this->assertSame(
                'team',
                PostDuplicator::buildTargetPostName('team', 'nl', 'nl')
            );
            $this->assertFalse($called, 'DeepL should not be called for same-language slug build');
        }

        public function testEmptySourceSlugReturnsEmpty(): void
        {
            $this->assertSame('', PostDuplicator::buildTargetPostName('', 'nl', 'en'));
            $this->assertSame('', PostDuplicator::buildTargetPostName('   ', 'nl', 'en'));
        }

        public function testDoesNotDeriveSlugFromLongTranslatedTitle(): void
        {
            DeepLTranslator::setTestTranslator(static function (array $texts): array {
                $out = [];
                foreach ($texts as $text) {
                    $out[] = is_string($text) ? $text : '';
                }
                return [
                    'success' => true,
                    'translations' => $out,
                    'error' => null,
                ];
            });

            //source leaf slug stays short even when titles are long
            $this->assertSame(
                'team',
                PostDuplicator::buildTargetPostName('team', 'nl', 'en')
            );
            $this->assertNotSame(
                'our-core-team-the-people-behind-2bhonest',
                PostDuplicator::buildTargetPostName('team', 'nl', 'en')
            );
        }
    }
}
