<?php

namespace NoviOnline;

use NoviOnline\Core\Gutenberg;
use NoviOnline\Core\Singleton;

/**
 * Class GravityFormsComponent
 * @package NoviOnline
 */
class GravityFormsComponent extends Singleton {

    private const GRAVITY_FORM_BLOCK = 'gravityforms/form';

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
            add_action('wp_enqueue_scripts', [$this, 'maybeConditionallyDequeueRecaptcha'], 100);
        }
    }

    /**
     * Restyle GF submit control as Nectar button (text only — match production)
     * Supports GF 3.x <button> markup and legacy <input type="submit">
     * @param string $buttonInput
     * @param array $form
     * @return string
     */
    public static function filterSubmitButton(string $buttonInput, array $form): string {
        $buttonText = !empty($form['button']['text']) ? $form['button']['text'] : __("Submit", Theme::TEXT_DOMAIN);

        //skip if already wrapped by this filter
        if (strpos($buttonInput, 'novi-button--form-submit') !== false) {
            return $buttonInput;
        }

        $buttonAttributeString = '';

        //GF 3.x+ outputs a <button> element by default
        if (preg_match('/<button([^>]*)>/i', $buttonInput, $buttonMatches)) {
            $buttonAttributeString = $buttonMatches[1];
        } elseif (preg_match("/<input([^\/>]*)(\s\/)*>/", $buttonInput, $buttonMatches)) {
            //legacy GF <input type="submit"> markup
            $buttonAttributeString = str_replace("value='" . $buttonText . "' ", "", $buttonMatches[1]);
            $buttonAttributeString = str_replace('value="' . $buttonText . '" ', '', $buttonAttributeString);
        } else {
            return $buttonInput;
        }

        $buttonAttributeString = trim($buttonAttributeString);

        //add nectar button classes (single- and double-quoted class attrs)
        $buttonAttributeString = str_replace("class='gform_button", "class='gform_button nectar__link nectar-blocks-button__inner nectar-font-label", $buttonAttributeString);
        $buttonAttributeString = str_replace("class='gform-button", "class='gform-button nectar__link nectar-blocks-button__inner nectar-font-label", $buttonAttributeString);
        $buttonAttributeString = str_replace('class="gform_button', 'class="gform_button nectar__link nectar-blocks-button__inner nectar-font-label', $buttonAttributeString);
        $buttonAttributeString = str_replace('class="gform-button', 'class="gform-button nectar__link nectar-blocks-button__inner nectar-font-label', $buttonAttributeString);

        //create new button HTML (no trailing icon — production MoreSenz is text-only)
        ob_start(); ?>

            <div class="wp-block-nectar-blocks-button nectar-blocks-button nectar-font-label novi-button novi-button--form-submit">
                <button <?php echo $buttonAttributeString; ?>>
                    <span class="nectar-blocks-button__text">
                        <?php echo esc_html($buttonText); ?>
                    </span>
                </button>
            </div>

            <?php return ob_get_clean();
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
     * Collect Gravity Forms embedded as blocks on the current page.
     *
     * Uses Gutenberg::getUsedBlocksByName() to scan global $post and $otherPosts,
     * including nested inner blocks and reusable core/block patterns.
     *
     * @return array<int, array>
     */
    private function collectFormsFromUsedBlocks(): array {
        $foundForms = [];
        $formBlocks = Gutenberg::getUsedBlocksByName(self::GRAVITY_FORM_BLOCK, true, true);

        foreach ($formBlocks as $block) {
            if (!is_array($block) || empty($block['attrs']['formId'])) {
                continue;
            }

            $formId = (int) $block['attrs']['formId'];
            $attributes = $block['attrs'];
            $attributes['ajax'] = isset($attributes['ajax']) ? (bool) $attributes['ajax'] : false;

            if (!isset($foundForms[$formId])) {
                $foundForms[$formId] = $attributes;
            }
        }

        return $foundForms;
    }

    /**
     * Check whether the current page contains at least one active Gravity Form.
     *
     * @return bool
     */
    private function pageHasGravityForm(): bool {
        if (!class_exists('GFAPI')) {
            return false;
        }

        foreach ($this->collectFormsFromUsedBlocks() as $formId => $attributes) {
            $form = \GFAPI::get_form((int) $formId);

            if ($form && $form['is_active'] && !$form['is_trash']) {
                return true;
            }
        }

        return false;
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
        if (!class_exists('GFFormDisplay') || !class_exists('GFAPI')) {
            return;
        }

        $foundForms = $this->collectFormsFromUsedBlocks();

        if (empty($foundForms)) {
            return;
        }

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

    /**
     * Dequeue reCAPTCHA scripts on pages without Gravity Forms.
     *
     * The GF reCAPTCHA add-on enqueues site-wide for v3 behavioral scoring;
     * we only need it when a form is actually present on the page.
     *
     * @return void
     */
    public function maybeConditionallyDequeueRecaptcha(): void {
        if (is_admin()) {
            return;
        }

        if (function_exists('rgget') && rgget('gf_page') === 'preview') {
            return;
        }

        if (is_preview()) {
            return;
        }

        if (apply_filters('novi_force_enqueue_recaptcha', false)) {
            return;
        }

        if ($this->pageHasGravityForm()) {
            return;
        }

        $recaptchaHandles = [
            'gforms_recaptcha_recaptcha',
            'gforms_recaptcha_frontend',
            'gforms_recaptcha_frontend-legacy',
        ];

        foreach ($recaptchaHandles as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
    }
}
