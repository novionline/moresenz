<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class GravityFormsComponent
 * @package NoviOnline
 */
class GravityFormsComponent extends Singleton {

    /**
     * GravityFormsComponent constructor.
     */
    protected function __construct() {
        global $pagenow;

        if (!is_admin() || (is_admin() && $pagenow === 'post.php') || (is_admin() && function_exists('acf_is_ajax') && acf_is_ajax())) {

            //change submit button from input to button
            add_filter('gform_submit_button', [$this, 'filterSubmitButton'], 10, 2);

            //disable legacy css
            add_filter('gform_enable_legacy_markup', '__return_false', 10, 2);

            //add space placeholder to fields that support placeholders when none is configured
            add_filter('gform_field_content', [$this, 'addSpacePlaceholder'], 10, 5);

            //filter validation message
            add_filter('gform_validation_message', [$this, 'filterValidationMessage'], 10, 2);

            //filter confirmation message
            add_filter('gform_confirmation', [$this, 'filterConfirmationMessage'], 10, 2);
        }

        //ensure Gravity Forms assets are enqueued for forms in global sections
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'ensureGlobalSectionFormsEnqueued'], 10);
        }
    }

    /**
     * Filter GF input[type=submit] to button element
     * @param string $buttonInput
     * @param array $form
     * @return string
     */
    public static function filterSubmitButton(string $buttonInput, array $form): string {
        $buttonText = !empty($form['button']['text']) ? $form['button']['text'] : __("Submit", Theme::TEXT_DOMAIN);

        //save attribute string to $button_match[1]
        preg_match("/<input([^\/>]*)(\s\/)*>/", $buttonInput, $buttonMatches);

        if (count($buttonMatches) > 0) {

            //remove value attribute
            $buttonAttributeString = str_replace("value='" . $buttonText . "' ", "", $buttonMatches[1]);

            //add primary class to button
            $buttonAttributeString = str_replace("class='gform_button", "class='gform_button nectar__link nectar-blocks-button__inner nectar-font-label", $buttonAttributeString);
            $buttonAttributeString = str_replace("class='gform-button", "class='gform-button nectar__link nectar-blocks-button__inner nectar-font-label", $buttonAttributeString);

            //create new button HTML
            ob_start(); ?>

            <div class="wp-block-nectar-blocks-button nectar-blocks-button nectar-font-label novi-button novi-button--form-submit novi-button--arrow-right">
                <button <?php echo $buttonAttributeString; ?>>
                    <span class="nectar-blocks-button__text">
                        <?php echo $buttonText; ?>
                    </span>
                </button>
            </div>

            <?php return ob_get_clean();
        }

        return $buttonInput;
    }

    /**
     * Add space placeholder to fields that support placeholders when none is configured
     * @param string $fieldContent
     * @param object $field
     * @param mixed $value
     * @param int $leadId
     * @param int $formId
     * @return string
     */
    public function addSpacePlaceholder(string $fieldContent, object $field, mixed $value, int $leadId, int $formId): string {
        //list of field types that support placeholders
        $placeholderSupportedTypes = ['text', 'textarea', 'email', 'phone', 'number', 'website', 'password', 'date', 'time', 'post_title', 'post_content', 'post_excerpt', 'post_tags', 'post_category', 'post_custom_field', 'product', 'quantity', 'price', 'name', 'address', 'fileupload', 'calculation', 'singleproduct', 'hiddenproduct'];

        //check if field type supports placeholders
        if (!in_array($field->type, $placeholderSupportedTypes, true)) {
            return $fieldContent;
        }

        //check if field already has a placeholder configured
        $hasPlaceholder = false;
        
        //check main field placeholder
        if (!empty($field->placeholder)) {
            $hasPlaceholder = true;
        }
        
        //check input placeholders for multi-input fields (like name, address)
        if (!$hasPlaceholder && !empty($field->inputs) && is_array($field->inputs)) {
            foreach ($field->inputs as $input) {
                if (!empty($input['placeholder'])) {
                    $hasPlaceholder = true;
                    break;
                }
            }
        }

        //if placeholder already exists, return content as is
        if ($hasPlaceholder) {
            return $fieldContent;
        }

        //add space placeholder to input and textarea elements that don't have placeholder attribute
        $fieldContent = preg_replace_callback(
            '/(<(input|textarea)(?:\s[^>]*?)?)(\s*\/?>)/i',
            function($matches) {
                $tag = $matches[1];
                $elementType = strtolower($matches[2]);
                $closing = $matches[3];
                
                //check if placeholder attribute already exists
                if (preg_match('/\splaceholder\s*=/i', $tag)) {
                    return $matches[0];
                }
                
                //check if it's a submit button, hidden input, checkbox, or radio
                if (preg_match('/\stype\s*=\s*["\']?(?:submit|button|hidden|checkbox|radio|file|image|reset)/i', $tag)) {
                    return $matches[0];
                }
                
                //add placeholder attribute with space character before the closing
                return $tag . ' placeholder=" "' . $closing;
            },
            $fieldContent
        );

        return $fieldContent;
    }

    /**
     * Render a Gravity Forms alert message
     * @param string $type
     * @param string $text
     * @param string $ariaLive
     * @return string
     */
    private static function renderAlert(string $type, string $text, string $ariaLive): string {
        $typeClass = $type === 'error' ? 'novi-alert--error' : 'novi-alert--success';

        ob_start(); ?>

        <div class="novi-alert <?php echo esc_attr($typeClass); ?> gform-theme__no-reset--el gform-theme__no-reset--children" role="alert" aria-live="<?php echo esc_attr($ariaLive); ?>">
            <span class="novi-alert__icon" aria-hidden="true">
                <?php if ($type === 'success') { ?>
                    <svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
                        <path d="M8 12.3333L10.4615 15L16 9M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                <?php } else { ?>
                    <svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
                        <path d="M9 9L15 15M15 9L9 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                <?php } ?>
            </span>
            <div class="novi-alert__content">
                <p class="novi-alert__text">
                    <?php echo esc_html($text); ?>
                </p>
            </div>
        </div>

        <?php
        return trim(ob_get_clean());
    }

    /**
     * Filter validation message
     * @param string $message
     * @param array $form
     * @return string
     */
    public static function filterValidationMessage(string $message, array $form): string {
        $messageText = strip_tags($message) ?: __('There was a problem with your submission. Please check the fields below.', Theme::TEXT_DOMAIN);

        return self::renderAlert('error', $messageText, 'assertive');
    }

    /**
     * Filter confirmation message
     * @param array|string $confirmation
     * @param array $form
     * @return array|string
     */
    public static function filterConfirmationMessage(array|string $confirmation, array $form): array|string {

        if (is_array($confirmation)) {
            return $confirmation;
        }

        $messageText = strip_tags($confirmation) ?: __('Your message has been sent. Thank you for contacting us.', Theme::TEXT_DOMAIN);

        return self::renderAlert('success', $messageText, 'polite');
    }

    /**
     * Ensure Gravity Forms assets are enqueued for forms in global sections.
     * 
     * Gravity Forms only enqueues assets when it detects forms in $wp_query->posts.
     * This method ensures forms in global sections (like footers) also get their assets enqueued.
     * 
     * @return void
     */
    public function ensureGlobalSectionFormsEnqueued(): void {
        //check if Gravity Forms is available
        if (!class_exists('GFFormDisplay') || !class_exists('GFAPI')) {
            return;
        }

        //get visible global sections
        $globalSectionComponent = GlobalSectionComponent::getInstance();
        $visibleSections = $globalSectionComponent->getVisibleGlobalSectionPosts();

        if (empty($visibleSections)) {
            return;
        }

        $foundForms = [];

        //parse each section for forms
        foreach ($visibleSections as $section) {
            $sectionContent = $section->post_content ?? '';
            if (empty($sectionContent)) {
                continue;
            }

            $sectionForms = [];
            $sectionBlocks = [];
            \GFFormDisplay::parse_forms($sectionContent, $sectionForms, $sectionBlocks);

            //merge found forms, deduplicating by form ID
            foreach ($sectionForms as $formId => $attributes) {
                if (!isset($foundForms[$formId])) {
                    $foundForms[$formId] = $attributes;
                }
            }
        }

        //enqueue assets for each found form
        foreach ($foundForms as $formId => $attributes) {
            $formId = (int) $formId;
            $form = \GFAPI::get_form($formId);

            if (!$form || !$form['is_active'] || $form['is_trash']) {
                continue;
            }

            $ajax = $attributes['ajax'] ?? false;
            $form['theme'] = !empty($attributes['theme']) ? $form['theme'] : \GFForms::get_default_theme();
            $form['styles'] = \GFFormDisplay::get_form_styles($attributes);

            \GFFormDisplay::enqueue_form_scripts($form, $ajax, $form['theme']);
        }
    }
}
