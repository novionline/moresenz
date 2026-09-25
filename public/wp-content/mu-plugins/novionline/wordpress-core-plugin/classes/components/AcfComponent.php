<?php

namespace NoviOnline\Core;

/**
 * Class AcfComponent
 * @package NoviOnline
 */
class AcfComponent extends Singleton
{
    /**
     * AcfComponent constructor.
     */
    protected function __construct()
    {
        //add new toolbar preset
        if (is_admin()) add_filter('acf/fields/wysiwyg/toolbars', [$this, 'filterWysiwygEditorToolbars']);

        //change the name of ACF JSON files to a slugified version of the field group title
        $shouldSlugifyAcfJsonFiles = apply_filters('novi_should_slugify_acf_json_files', true);
        if ($shouldSlugifyAcfJsonFiles) {
            add_filter('acf/json/save_file_name', function (string $fileName, array $acfGroup): string {
                return Formatting::slugify($acfGroup['title']) . '.json';
            }, 10, 3);
        }
    }

    /**
     * Add new toolbar preset
     * @param array $toolbars
     * @return array
     */
    public static function filterWysiwygEditorToolbars(array $toolbars): array
    {
        $toolbars['Novi simple'] = [];
        $toolbars['Novi simple'][1] = [
            'bold',
            'italic',
            'underline',
            'link'
        ];

        return $toolbars;
    }

}