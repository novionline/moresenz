<?php

namespace NoviOnline\ContentTranslator\Core;

/**
 * Translate Gravity Forms by locale suffix (e.g. "Contact - NL" -> "Contact - EN").
 *
 * Creates a duplicate when the target title is missing, otherwise updates the existing
 * target form from the source form structure + DeepL strings.
 */
final class GravityFormsTranslator
{
    /** @var array<int, string> */
    private static array $lastWarnings = [];

    /** @var array<int, string>|null */
    private static ?array $testFormTitlesById = null;

    /**
     * @return array<int, string>
     */
    public static function consumeWarnings(): array
    {
        $warnings = self::$lastWarnings;
        self::$lastWarnings = [];
        return $warnings;
    }

    /**
     * @return array<int, string>
     */
    public static function getLastWarnings(): array
    {
        return self::$lastWarnings;
    }

    public static function stripLocaleSuffix(string $name, string $upperLocale): string
    {
        $suffix = ' - ' . strtoupper(trim($upperLocale));
        if ($suffix !== ' - ' && str_ends_with($name, $suffix)) {
            return substr($name, 0, -strlen($suffix));
        }
        return $name;
    }

    public static function buildTargetTitle(string $sourceTitle, string $sourceLang, string $targetLang): string
    {
        $base = self::stripLocaleSuffix($sourceTitle, strtoupper($sourceLang));
        return rtrim($base) . ' - ' . strtoupper(trim($targetLang));
    }

    public static function findFormIdByTitle(string $title): int
    {
        $title = trim($title);
        if ($title === '') {
            return 0;
        }

        if (is_array(self::$testFormTitlesById)) {
            foreach (self::$testFormTitlesById as $id => $formTitle) {
                if ((string) $formTitle === $title) {
                    return (int) $id;
                }
            }
        }

        if (class_exists('\GFFormsModel') && method_exists('\GFFormsModel', 'get_form_id')) {
            $id = (int) \GFFormsModel::get_form_id($title);
            if ($id > 0) {
                return $id;
            }
        }

        if (class_exists('\GFFormsModel') && method_exists('\GFFormsModel', 'get_forms')) {
            $forms = \GFFormsModel::get_forms(null);
            if (is_array($forms)) {
                foreach ($forms as $form) {
                    if (is_object($form) && isset($form->id, $form->title) && (string) $form->title === $title) {
                        return (int) $form->id;
                    }
                }
            }
            return 0;
        }

        if (!class_exists('\GFAPI') || !method_exists('\GFAPI', 'get_forms')) {
            return 0;
        }

        foreach ([true, false] as $active) {
            $forms = \GFAPI::get_forms($active);
            if (!is_array($forms)) {
                continue;
            }
            foreach ($forms as $form) {
                $formTitle = '';
                $formId = 0;
                if (is_array($form)) {
                    $formTitle = (string) ($form['title'] ?? '');
                    $formId = (int) ($form['id'] ?? 0);
                } elseif (is_object($form)) {
                    $formTitle = (string) ($form->title ?? '');
                    $formId = (int) ($form->id ?? 0);
                }
                if ($formId > 0 && $formTitle === $title) {
                    return $formId;
                }
            }
        }

        return 0;
    }

    /**
     * Resolve the target-locale form ID for a source form via title suffix swap.
     * Requires the source form title to end with " - {SOURCE}" (uppercase).
     */
    public static function resolveTargetFormId(int $sourceFormId, string $sourceLang, string $targetLang): int
    {
        $sourceFormId = (int) $sourceFormId;
        $sourceLang = trim($sourceLang);
        $targetLang = trim($targetLang);
        if ($sourceFormId <= 0 || $sourceLang === '' || $targetLang === '' || strcasecmp($sourceLang, $targetLang) === 0) {
            return 0;
        }

        $sourceTitle = self::getFormTitleById($sourceFormId);
        if ($sourceTitle === '') {
            return 0;
        }

        $suffix = ' - ' . strtoupper($sourceLang);
        if (!str_ends_with($sourceTitle, $suffix)) {
            return 0;
        }

        $targetTitle = self::buildTargetTitle($sourceTitle, $sourceLang, $targetLang);
        if ($targetTitle === '' || $targetTitle === $sourceTitle) {
            return 0;
        }

        return self::findFormIdByTitle($targetTitle);
    }

    /**
     * Deterministic form ID mapper based on form title suffix swap (e.g. "- NL" -> "- EN").
     *
     * @param array{mapped?:int,unmapped?:int} $stats
     * @return callable(int):int
     */
    public static function buildFormIdMapperBySuffix(string $sourceLang, string $targetLang, array &$stats): callable
    {
        $sourceLang = strtolower(trim($sourceLang));
        $targetLang = strtolower(trim($targetLang));
        $cache = [];

        return static function (int $sourceFormId) use ($sourceLang, $targetLang, &$stats, &$cache): int {
            if ($sourceFormId <= 0) {
                return 0;
            }
            if (isset($cache[$sourceFormId])) {
                return (int) $cache[$sourceFormId];
            }

            $targetFormId = self::resolveTargetFormId($sourceFormId, $sourceLang, $targetLang);
            $cache[$sourceFormId] = $targetFormId;
            if ($targetFormId > 0) {
                $stats['mapped'] = (int) ($stats['mapped'] ?? 0) + 1;
            } else {
                $stats['unmapped'] = (int) ($stats['unmapped'] ?? 0) + 1;
            }
            return $targetFormId;
        };
    }

    /**
     * Replace gravityforms/form attrs.formId inside Gutenberg content.
     *
     * @param callable(int):int $mapFn
     * @return array{content:string,updated_blocks:int}
     */
    public static function replaceGravityFormsBlockFormIds(string $content, callable $mapFn): array
    {
        if (!function_exists('has_blocks') || !function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
            return ['content' => $content, 'updated_blocks' => 0];
        }
        if (!has_blocks($content)) {
            return ['content' => $content, 'updated_blocks' => 0];
        }

        $blocks = parse_blocks($content);
        if (empty($blocks) || !is_array($blocks)) {
            return ['content' => $content, 'updated_blocks' => 0];
        }

        $updatedBlocks = 0;
        $changed = self::replaceFormIdsInBlocks($blocks, $mapFn, $updatedBlocks);
        if (!$changed) {
            return ['content' => $content, 'updated_blocks' => 0];
        }

        $serialized = serialize_blocks($blocks);
        $serialized = is_string($serialized) ? $serialized : $content;

        return ['content' => $serialized, 'updated_blocks' => $updatedBlocks];
    }

    /**
     * Test override: map form id => title without GFAPI.
     *
     * @param array<int, string>|null $titlesById
     */
    public static function setTestFormTitlesById(?array $titlesById): void
    {
        self::$testFormTitlesById = $titlesById;
    }

    private static function getFormTitleById(int $formId): string
    {
        if (is_array(self::$testFormTitlesById) && isset(self::$testFormTitlesById[$formId])) {
            return (string) self::$testFormTitlesById[$formId];
        }

        if (!class_exists('\GFAPI') || !method_exists('\GFAPI', 'get_form')) {
            return '';
        }

        $form = \GFAPI::get_form($formId);
        if (!is_array($form)) {
            return '';
        }

        return isset($form['title']) && is_string($form['title']) ? (string) $form['title'] : '';
    }

    /**
     * @param array<int, mixed> $blocks
     * @param callable(int):int $mapFn
     */
    private static function replaceFormIdsInBlocks(array &$blocks, callable $mapFn, int &$updatedBlocks): bool
    {
        $changed = false;
        foreach ($blocks as &$block) {
            if (!is_array($block)) {
                continue;
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                if (self::replaceFormIdsInBlocks($block['innerBlocks'], $mapFn, $updatedBlocks)) {
                    $changed = true;
                }
            }

            $name = (string) ($block['blockName'] ?? '');
            if ($name !== 'gravityforms/form') {
                continue;
            }
            if (!isset($block['attrs']) || !is_array($block['attrs'])) {
                continue;
            }
            if (!array_key_exists('formId', $block['attrs'])) {
                continue;
            }

            $raw = $block['attrs']['formId'];
            $sourceFormId = is_numeric($raw) ? (int) $raw : 0;
            if ($sourceFormId <= 0) {
                continue;
            }

            $targetFormId = (int) $mapFn($sourceFormId);
            if ($targetFormId <= 0 || $targetFormId === $sourceFormId) {
                continue;
            }

            //GF block markup stores formId as a string in the saved comment JSON
            $block['attrs']['formId'] = (string) $targetFormId;
            $updatedBlocks++;
            $changed = true;
        }
        return $changed;
    }

    /**
     * Protect Gravity Forms merge tags from DeepL corruption.
     *
     * @return array{0: string, 1: array<string, string>} protected text + token => original map
     */
    public static function protectMergeTags(string $text): array
    {
        if ($text === '' || !str_contains($text, '{')) {
            return [$text, []];
        }

        $map = [];
        $i = 0;
        $protected = preg_replace_callback(
            '/\{[^{}]+\}/',
            static function (array $m) use (&$map, &$i): string {
                $token = 'NCTGFMT' . $i . 'X';
                $map[$token] = $m[0];
                $i++;
                return $token;
            },
            $text
        );

        return [is_string($protected) ? $protected : $text, $map];
    }

    /**
     * @param array<string, string> $map
     */
    public static function restoreMergeTags(string $text, array $map): string
    {
        if ($map === [] || $text === '') {
            return $text;
        }
        return strtr($text, $map);
    }

    /**
     * @param array{only_missing?:bool, force?:bool} $options
     * @return int|false Target form ID
     */
    public static function translateForm(int $sourceFormId, string $sourceLang, string $targetLang, array $options = [])
    {
        self::$lastWarnings = [];

        $sourceFormId = (int) $sourceFormId;
        $sourceLang = trim($sourceLang);
        $targetLang = trim($targetLang);
        if ($sourceFormId <= 0 || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return false;
        }

        if (!class_exists('\GFAPI')) {
            self::$lastWarnings[] = 'GFAPI is not available.';
            return false;
        }

        $onlyMissing = array_key_exists('only_missing', $options) ? (bool) $options['only_missing'] : false;
        $force = array_key_exists('force', $options) ? (bool) $options['force'] : false;

        $sourceForm = \GFAPI::get_form($sourceFormId);
        if (!is_array($sourceForm) || empty($sourceForm['id'])) {
            self::$lastWarnings[] = sprintf('Source form %d not found.', $sourceFormId);
            return false;
        }

        $sourceTitle = (string) ($sourceForm['title'] ?? '');
        $targetTitle = self::buildTargetTitle($sourceTitle, $sourceLang, $targetLang);
        $existingTargetId = self::findFormIdByTitle($targetTitle);

        if ($existingTargetId > 0 && $onlyMissing && !$force) {
            return $existingTargetId;
        }

        $targetFormId = $existingTargetId;
        if ($targetFormId <= 0) {
            $duplicated = \GFAPI::duplicate_form($sourceFormId);
            if (is_wp_error($duplicated) || !is_numeric($duplicated) || (int) $duplicated <= 0) {
                $msg = is_wp_error($duplicated) ? $duplicated->get_error_message() : 'unknown error';
                self::$lastWarnings[] = sprintf('Failed duplicating form %d: %s', $sourceFormId, $msg);
                return false;
            }
            $targetFormId = (int) $duplicated;
        }

        $formMeta = json_decode((string) wp_json_encode($sourceForm), true);
        if (!is_array($formMeta)) {
            self::$lastWarnings[] = 'Failed encoding source form meta.';
            return false;
        }

        $formMeta = self::translateFormMeta($formMeta, $sourceLang, $targetLang);
        $formMeta['id'] = $targetFormId;
        $formMeta['title'] = $targetTitle;
        if (!isset($formMeta['is_active'])) {
            $formMeta['is_active'] = true;
        }

        $updated = \GFAPI::update_form($formMeta, $targetFormId);
        if ($updated !== true) {
            $msg = is_wp_error($updated) ? $updated->get_error_message() : 'unknown error';
            self::$lastWarnings[] = sprintf('Failed updating form %d: %s', $targetFormId, $msg);
            return false;
        }

        return $targetFormId;
    }

    /**
     * Translate user-facing strings + remap confirmation targets on a form meta array.
     * Public for unit tests (no GFAPI write).
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public static function translateFormMeta(array $form, string $sourceLang, string $targetLang): array
    {
        $plainPaths = [];
        $htmlPaths = [];

        self::collectTranslatablePaths($form, $plainPaths, $htmlPaths);

        $plainMap = self::batchTranslateUnique($plainPaths, $sourceLang, $targetLang, 'plain');
        $htmlMap = self::batchTranslateUnique($htmlPaths, $sourceLang, $targetLang, 'html');
        $map = $plainMap + $htmlMap;

        self::applyTranslations($form, $map);
        self::remapConfirmationTargets($form, $sourceLang, $targetLang);

        return $form;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<int, string> $plainPaths collected strings (values)
     * @param array<int, string> $htmlPaths collected strings (values)
     */
    private static function collectTranslatablePaths(array $form, array &$plainOut, array &$htmlOut): void
    {
        if (!empty($form['description']) && is_string($form['description'])) {
            $htmlOut[] = $form['description'];
        }
        if (!empty($form['customRequiredIndicator']) && is_string($form['customRequiredIndicator'])) {
            $plainOut[] = $form['customRequiredIndicator'];
        }
        if (isset($form['button']) && is_array($form['button'])) {
            if (!empty($form['button']['text']) && is_string($form['button']['text'])) {
                $plainOut[] = $form['button']['text'];
            }
            if (!empty($form['button']['imageAlt']) && is_string($form['button']['imageAlt'])) {
                $plainOut[] = $form['button']['imageAlt'];
            }
        }
        if (isset($form['save']['button']['text']) && is_string($form['save']['button']['text']) && $form['save']['button']['text'] !== '') {
            $plainOut[] = $form['save']['button']['text'];
        }
        //legacy GF key still present on older forms alongside save.button.text
        if (!empty($form['saveButtonText']) && is_string($form['saveButtonText'])) {
            $plainOut[] = $form['saveButtonText'];
        }

        if (isset($form['pagination']) && is_array($form['pagination'])) {
            if (!empty($form['pagination']['pages']) && is_array($form['pagination']['pages'])) {
                foreach ($form['pagination']['pages'] as $pageTitle) {
                    if (is_string($pageTitle) && $pageTitle !== '') {
                        $plainOut[] = $pageTitle;
                    }
                }
            }
            foreach (['nextButton', 'previousButton'] as $btnKey) {
                if (!empty($form['pagination'][$btnKey]['text']) && is_string($form['pagination'][$btnKey]['text'])) {
                    $plainOut[] = $form['pagination'][$btnKey]['text'];
                }
            }
        }

        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                self::collectFieldStrings($field, $plainOut, $htmlOut);
            }
        }

        if (!empty($form['confirmations']) && is_array($form['confirmations'])) {
            foreach ($form['confirmations'] as $confirmation) {
                if (!is_array($confirmation)) {
                    continue;
                }
                if (!empty($confirmation['name']) && is_string($confirmation['name'])) {
                    $plainOut[] = $confirmation['name'];
                }
                if (!empty($confirmation['message']) && is_string($confirmation['message'])) {
                    $htmlOut[] = $confirmation['message'];
                }
            }
        }

        if (!empty($form['notifications']) && is_array($form['notifications'])) {
            foreach ($form['notifications'] as $notification) {
                if (!is_array($notification)) {
                    continue;
                }
                if (!empty($notification['name']) && is_string($notification['name'])) {
                    $plainOut[] = $notification['name'];
                }
                if (!empty($notification['subject']) && is_string($notification['subject'])) {
                    $plainOut[] = $notification['subject'];
                }
                if (!empty($notification['message']) && is_string($notification['message'])) {
                    $htmlOut[] = $notification['message'];
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, string> $plainOut
     * @param array<int, string> $htmlOut
     */
    private static function collectFieldStrings(array $field, array &$plainOut, array &$htmlOut): void
    {
        foreach (['label', 'placeholder', 'errorMessage', 'checkboxLabel'] as $key) {
            if (!empty($field[$key]) && is_string($field[$key])) {
                $plainOut[] = $field[$key];
            }
        }
        if (!empty($field['description']) && is_string($field['description'])) {
            $htmlOut[] = $field['description'];
        }
        if (!empty($field['content']) && is_string($field['content'])) {
            $htmlOut[] = $field['content'];
        }

        if (!empty($field['choices']) && is_array($field['choices'])) {
            foreach ($field['choices'] as $choice) {
                if (!is_array($choice)) {
                    continue;
                }
                $text = isset($choice['text']) && is_string($choice['text']) ? $choice['text'] : '';
                if ($text !== '') {
                    $plainOut[] = $text;
                }
            }
        }

        if (!empty($field['inputs']) && is_array($field['inputs'])) {
            foreach ($field['inputs'] as $input) {
                if (!is_array($input)) {
                    continue;
                }
                foreach (['label', 'placeholder', 'customLabel'] as $key) {
                    if (!empty($input[$key]) && is_string($input[$key])) {
                        $plainOut[] = $input[$key];
                    }
                }
            }
        }

        foreach (['nextButton', 'previousButton'] as $btnKey) {
            if (!empty($field[$btnKey]['text']) && is_string($field[$btnKey]['text'])) {
                $plainOut[] = $field[$btnKey]['text'];
            }
        }
    }

    /**
     * Built-in GF / form UI labels DeepL commonly mistranslates (e.g. Verzenden → Shipping).
     * Applied before DeepL so submit buttons stay product UI language, not logistics jargon.
     *
     * @return array<string, array<string, array<string, string>>> sourceLang => targetLang => source => target
     */
    private static function getFixedLabelMap(): array
    {
        return [
            'nl' => [
                'en' => [
                    'Verzenden' => 'Submit',
                    'Verstuur' => 'Submit',
                    'Versturen' => 'Submit',
                    'Downloaden' => 'Download',
                ],
            ],
        ];
    }

    /**
     * Resolve a fixed GF label override, or null when DeepL should translate.
     */
    public static function resolveFixedLabel(string $text, string $sourceLang, string $targetLang): ?string
    {
        $sourceLang = strtolower(trim($sourceLang));
        $targetLang = strtolower(trim($targetLang));
        $needle = trim($text);
        if ($needle === '' || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return null;
        }
        $map = self::getFixedLabelMap()[$sourceLang][$targetLang] ?? [];
        if (!isset($map[$needle])) {
            return null;
        }
        return (string) $map[$needle];
    }

    /**
     * @param array<int, string> $texts
     * @return array<string, string> original => translated
     */
    private static function batchTranslateUnique(array $texts, string $sourceLang, string $targetLang, string $context): array
    {
        $unique = [];
        foreach ($texts as $text) {
            if (!is_string($text) || trim($text) === '') {
                continue;
            }
            $unique[$text] = true;
        }
        $list = array_keys($unique);
        if ($list === []) {
            return [];
        }

        $map = [];
        $needDeepL = [];
        foreach ($list as $original) {
            $fixed = self::resolveFixedLabel($original, $sourceLang, $targetLang);
            if ($fixed !== null) {
                $map[$original] = $fixed;
                continue;
            }
            $needDeepL[] = $original;
        }

        if ($needDeepL === []) {
            return $map;
        }

        $protectedList = [];
        $tokenMaps = [];
        foreach ($needDeepL as $i => $text) {
            [$protected, $tokenMap] = self::protectMergeTags($text);
            $protectedList[$i] = $protected;
            $tokenMaps[$i] = $tokenMap;
        }

        $result = DeepLTranslator::translateTexts($protectedList, $sourceLang, $targetLang, [
            'context' => $context,
        ]);
        $translations = is_array($result['translations'] ?? null) ? $result['translations'] : $protectedList;

        foreach ($needDeepL as $i => $original) {
            $translated = isset($translations[$i]) && is_string($translations[$i]) ? $translations[$i] : $protectedList[$i];
            $translated = self::restoreMergeTags($translated, $tokenMaps[$i] ?? []);
            $map[$original] = $translated;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $map
     */
    private static function applyTranslations(array &$form, array $map): void
    {
        if ($map === []) {
            return;
        }

        $t = static function (string $value) use ($map): string {
            return array_key_exists($value, $map) ? (string) $map[$value] : $value;
        };

        if (!empty($form['description']) && is_string($form['description'])) {
            $form['description'] = $t($form['description']);
        }
        if (!empty($form['customRequiredIndicator']) && is_string($form['customRequiredIndicator'])) {
            $form['customRequiredIndicator'] = $t($form['customRequiredIndicator']);
        }
        if (isset($form['button']) && is_array($form['button'])) {
            if (!empty($form['button']['text']) && is_string($form['button']['text'])) {
                $form['button']['text'] = $t($form['button']['text']);
            }
            if (!empty($form['button']['imageAlt']) && is_string($form['button']['imageAlt'])) {
                $form['button']['imageAlt'] = $t($form['button']['imageAlt']);
            }
        }
        if (isset($form['save']['button']['text']) && is_string($form['save']['button']['text'])) {
            $form['save']['button']['text'] = $t($form['save']['button']['text']);
        }
        if (!empty($form['saveButtonText']) && is_string($form['saveButtonText'])) {
            $form['saveButtonText'] = $t($form['saveButtonText']);
        }

        if (isset($form['pagination']) && is_array($form['pagination'])) {
            if (!empty($form['pagination']['pages']) && is_array($form['pagination']['pages'])) {
                foreach ($form['pagination']['pages'] as $i => $pageTitle) {
                    if (is_string($pageTitle) && $pageTitle !== '') {
                        $form['pagination']['pages'][$i] = $t($pageTitle);
                    }
                }
            }
            foreach (['nextButton', 'previousButton'] as $btnKey) {
                if (!empty($form['pagination'][$btnKey]['text']) && is_string($form['pagination'][$btnKey]['text'])) {
                    $form['pagination'][$btnKey]['text'] = $t($form['pagination'][$btnKey]['text']);
                }
            }
        }

        if (!empty($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $fi => $field) {
                if (!is_array($field)) {
                    continue;
                }
                foreach (['label', 'placeholder', 'errorMessage', 'checkboxLabel', 'description', 'content'] as $key) {
                    if (!empty($field[$key]) && is_string($field[$key])) {
                        $form['fields'][$fi][$key] = $t($field[$key]);
                    }
                }
                if (!empty($field['choices']) && is_array($field['choices'])) {
                    foreach ($field['choices'] as $ci => $choice) {
                        if (!is_array($choice)) {
                            continue;
                        }
                        $text = isset($choice['text']) && is_string($choice['text']) ? $choice['text'] : '';
                        $value = isset($choice['value']) && is_string($choice['value']) ? $choice['value'] : '';
                        if ($text !== '') {
                            $newText = $t($text);
                            $form['fields'][$fi]['choices'][$ci]['text'] = $newText;
                            //keep value in sync when it matched the original label
                            if ($value === $text) {
                                $form['fields'][$fi]['choices'][$ci]['value'] = $newText;
                            }
                        }
                    }
                }
                if (!empty($field['inputs']) && is_array($field['inputs'])) {
                    foreach ($field['inputs'] as $ii => $input) {
                        if (!is_array($input)) {
                            continue;
                        }
                        foreach (['label', 'placeholder', 'customLabel'] as $key) {
                            if (!empty($input[$key]) && is_string($input[$key])) {
                                $form['fields'][$fi]['inputs'][$ii][$key] = $t($input[$key]);
                            }
                        }
                    }
                }
                foreach (['nextButton', 'previousButton'] as $btnKey) {
                    if (!empty($field[$btnKey]['text']) && is_string($field[$btnKey]['text'])) {
                        $form['fields'][$fi][$btnKey]['text'] = $t($field[$btnKey]['text']);
                    }
                }
            }
        }

        if (!empty($form['confirmations']) && is_array($form['confirmations'])) {
            foreach ($form['confirmations'] as $id => $confirmation) {
                if (!is_array($confirmation)) {
                    continue;
                }
                if (!empty($confirmation['name']) && is_string($confirmation['name'])) {
                    $form['confirmations'][$id]['name'] = $t($confirmation['name']);
                }
                if (!empty($confirmation['message']) && is_string($confirmation['message'])) {
                    $form['confirmations'][$id]['message'] = $t($confirmation['message']);
                }
            }
        }

        if (!empty($form['notifications']) && is_array($form['notifications'])) {
            foreach ($form['notifications'] as $id => $notification) {
                if (!is_array($notification)) {
                    continue;
                }
                foreach (['name', 'subject', 'message'] as $key) {
                    if (!empty($notification[$key]) && is_string($notification[$key])) {
                        $form['notifications'][$id][$key] = $t($notification[$key]);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $form
     */
    private static function remapConfirmationTargets(array &$form, string $sourceLang, string $targetLang): void
    {
        if (empty($form['confirmations']) || !is_array($form['confirmations'])) {
            return;
        }

        foreach ($form['confirmations'] as $id => $confirmation) {
            if (!is_array($confirmation)) {
                continue;
            }
            $type = isset($confirmation['type']) ? (string) $confirmation['type'] : '';

            if ($type === 'page') {
                $pageId = (int) ($confirmation['pageId'] ?? $confirmation['page'] ?? 0);
                if ($pageId <= 0) {
                    continue;
                }
                $translatedPageId = self::translatePostId($pageId, $targetLang);
                if ($translatedPageId > 0 && $translatedPageId !== $pageId) {
                    $form['confirmations'][$id]['pageId'] = $translatedPageId;
                    if (array_key_exists('page', $form['confirmations'][$id])) {
                        $form['confirmations'][$id]['page'] = $translatedPageId;
                    }
                } else {
                    self::$lastWarnings[] = sprintf(
                        'Confirmation "%s": no Polylang %s translation for pageId %d; kept source.',
                        (string) ($confirmation['name'] ?? $id),
                        $targetLang,
                        $pageId
                    );
                }
                continue;
            }

            if ($type === 'redirect') {
                $url = isset($confirmation['url']) && is_string($confirmation['url']) ? trim($confirmation['url']) : '';
                if ($url === '') {
                    continue;
                }
                if (self::shouldKeepUrlVerbatim($url)) {
                    continue;
                }
                $rewritten = self::rewriteInternalConfirmationUrl($url, $sourceLang, $targetLang);
                if ($rewritten !== '' && $rewritten !== $url) {
                    $form['confirmations'][$id]['url'] = $rewritten;
                } else {
                    self::$lastWarnings[] = sprintf(
                        'Confirmation "%s": could not remap internal redirect URL to %s; kept source.',
                        (string) ($confirmation['name'] ?? $id),
                        $targetLang
                    );
                }
            }
        }
    }

    private static function translatePostId(int $postId, string $targetLang): int
    {
        if ($postId <= 0 || !function_exists('pll_get_post')) {
            return 0;
        }
        $translated = pll_get_post($postId, $targetLang);
        return is_numeric($translated) ? (int) $translated : 0;
    }

    private static function shouldKeepUrlVerbatim(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }
        $lower = strtolower($url);
        foreach (['mailto:', 'tel:', 'sms:', 'javascript:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return true;
            }
        }

        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $pathLower = strtolower($path);
        if ($pathLower !== '' && (str_contains($pathLower, '/wp-content/uploads/') || preg_match('/\\.[a-z0-9]{2,6}$/i', $path))) {
            return true;
        }

        return false;
    }

    private static function rewriteInternalConfirmationUrl(string $url, string $sourceLang, string $targetLang): string
    {
        //prefer resolving to a post ID then Polylang remap
        if (function_exists('url_to_postid')) {
            $postId = (int) url_to_postid($url);
            if ($postId > 0) {
                $translatedId = self::translatePostId($postId, $targetLang);
                if ($translatedId > 0 && function_exists('get_permalink')) {
                    $permalink = (string) get_permalink($translatedId);
                    if ($permalink !== '') {
                        return $permalink;
                    }
                }
            }
        }

        if (class_exists(InternalLinkTranslator::class)) {
            $rewrite = InternalLinkTranslator::rewriteInternalUrl($url, $targetLang, $sourceLang, [
                'strict' => true,
            ]);
            if (is_array($rewrite) && !empty($rewrite['changed']) && isset($rewrite['url']) && is_string($rewrite['url'])) {
                return (string) $rewrite['url'];
            }
        }

        return $url;
    }
}
