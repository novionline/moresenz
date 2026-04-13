<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class GravityFormsValidationComponent
 * @package NoviOnline
 */
class GravityFormsValidationComponent extends Singleton {

    const VALIDATION_PATTERNS = [
        'none' => [
            'pattern' => null,
        ],
        'alpha_only' => [
            'pattern' => '/^[a-zA-Z\s]+$/',
        ],
        'phone_numeric' => [
            'pattern' => '/^[\d\+\s\(\)]+$/',
        ],
    ];

    /**
     * Store form data for forms that need localized data output
     * @var array
     */
    private static $formsData = [];

    /**
     * GravityFormsValidationComponent constructor.
     */
    protected function __construct() {
        //add custom field to text and phone field settings
        add_action('gform_field_standard_settings', [$this, 'addValidationDropdown'], 10, 2);
        
        //add tooltips for custom field
        add_filter('gform_tooltips', [$this, 'addValidationTooltip']);
        
        //save custom field value
        add_action('gform_editor_js', [$this, 'editorScript']);
        
        //validate field on form submission
        add_filter('gform_field_validation', [$this, 'validateField'], 10, 4);
        
        //integrate with real-time validation plugin
        add_action('gform_enqueue_scripts', [$this, 'integrateRealTimeValidation'], 20, 2);
        
        //collect all forms' data to fix multiple forms issue
        add_action('gform_enqueue_scripts', [$this, 'collectFormData'], 30, 2);
    }

    /**
     * Get translated label for validation pattern
     * @param string $key
     * @return string
     */
    private function getValidationLabel(string $key): string {
        switch ($key) {
            case 'none':
                return __('None', Theme::TEXT_DOMAIN);
            case 'alpha_only':
                return __('Only a-z characters', Theme::TEXT_DOMAIN);
            case 'phone_numeric':
                return __('Only +, spaces, round brackets and numeric values', Theme::TEXT_DOMAIN);
            default:
                return '';
        }
    }

    /**
     * Add custom validation dropdown to field settings
     * @param int $position
     * @param int $formId
     * @return void
     */
    public function addValidationDropdown(int $position, int $formId): void {
        //display dropdown at position 25 (after conditional logic)
        if ($position !== 25) {
            return;
        }

        //create dropdown options
        $options = '';
        foreach (self::VALIDATION_PATTERNS as $key => $pattern) {
            $options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($key),
                esc_html($this->getValidationLabel($key))
            );
        }

        //output dropdown field
        ?>
        <li class="novi_regex_validation_setting field_setting">
            <label for="novi_regex_validation" class="section_label">
                <?php esc_html_e('Regex Validation', Theme::TEXT_DOMAIN); ?>
                <?php gform_tooltip('novi_regex_validation_tooltip'); ?>
            </label>
            <select id="novi_regex_validation" onchange="SetFieldProperty('noviRegexValidation', this.value);">
                <?php echo $options; ?>
            </select>
        </li>
        <?php
    }

    /**
     * Add tooltip for custom validation field
     * @param array $tooltips
     * @return array
     */
    public function addValidationTooltip(array $tooltips): array {
        $tooltips['novi_regex_validation_tooltip'] = sprintf(
            '<h6>%s</h6>%s',
            __('Regex Validation', Theme::TEXT_DOMAIN),
            __('Select a validation pattern to restrict input for this field. The validation will be checked when the form is submitted.', Theme::TEXT_DOMAIN)
        );
        return $tooltips;
    }

    /**
     * Add JavaScript to handle field settings in the editor
     * @return void
     */
    public function editorScript(): void {
        ?>
        <script type="text/javascript">
            //bind to the load field settings event to initialize the value
            fieldSettings.text += ', .novi_regex_validation_setting'
            fieldSettings.phone += ', .novi_regex_validation_setting'

            //when field is loaded, populate the custom setting
            jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                const validationValue = field.noviRegexValidation || 'none'
                jQuery('#novi_regex_validation').val(validationValue)
            })
        </script>
        <?php
    }

    /**
     * Get translated error message for validation pattern
     * Uses plugin text domain to match Gravity Forms translations
     * @param string $validationPattern
     * @return string
     */
    private function getErrorMessage(string $validationPattern): string {
        switch ($validationPattern) {
            case 'alpha_only':
                //use same string as Gravity Forms for consistency
                return __('This field can only contain letters (a-z)', Theme::TEXT_DOMAIN);
            case 'phone_numeric':
                //use same string as Gravity Forms for consistency
                return __('This field can only contain numbers, spaces, the + character, and round brackets.', Theme::TEXT_DOMAIN);
            default:
                return __('Invalid input format.', Theme::TEXT_DOMAIN);
        }
    }

    /**
     * Validate field based on selected regex pattern
     * @param array $result
     * @param mixed $value
     * @param array $form
     * @param object $field
     * @return array
     */
    public function validateField(array $result, $value, array $form, object $field): array {
        //check if field has custom validation set
        $validationPattern = $field->noviRegexValidation ?? 'none';
        
        //skip if no validation or field is empty (use required field setting for empty check)
        if ($validationPattern === 'none' || empty($value)) {
            return $result;
        }

        //get pattern configuration
        $patternConfig = self::VALIDATION_PATTERNS[$validationPattern] ?? null;
        
        if (!$patternConfig || !$patternConfig['pattern']) {
            return $result;
        }

        //validate against regex pattern
        if (!preg_match($patternConfig['pattern'], $value)) {
            $result['is_valid'] = false;
            $result['message'] = $this->getErrorMessage($validationPattern);
        }

        return $result;
    }

    /**
     * Integrate custom regex validations with real-time validation plugin
     * @param array $form
     * @param bool $is_ajax
     * @return void
     */
    public function integrateRealTimeValidation(array $form, bool $is_ajax): void {
        //check if real-time validation is enabled for this form
        if (!array_key_exists("enable_gfrtv", $form) || empty($form['enable_gfrtv'])) {
            return;
        }

        $formId = $form['id'];
        $scriptHandle = 'gfrtv_script';
        $dataVarName = 'gfrtv_' . $formId;

        //get fields with custom validation
        $fieldsWithCustomValidation = [];
        foreach ($form['fields'] as $field) {
            $validationPattern = $field->noviRegexValidation ?? 'none';
            
            if ($validationPattern !== 'none' && ($field->type == 'text' || $field->type == 'phone' || $field->type == 'number')) {
                $patternConfig = self::VALIDATION_PATTERNS[$validationPattern] ?? null;
                if ($patternConfig && $patternConfig['pattern']) {
                    //remove leading and trailing slashes from pattern for JavaScript
                    $pattern = $patternConfig['pattern'];
                    if (substr($pattern, 0, 1) === '/' && substr($pattern, -1) === '/') {
                        $pattern = substr($pattern, 1, -1);
                    }
                    $fieldsWithCustomValidation[$field->id] = [
                        'pattern' => $pattern,
                        'errorMessage' => $this->getErrorMessage($validationPattern),
                        'patternKey' => $validationPattern,
                    ];
                }
            }
        }

        if (empty($fieldsWithCustomValidation)) {
            return;
        }

        //add inline script to modify the localized data
        //this modifies the data after the script loads but before the plugin processes it
        $js = sprintf(
            'jQuery(function($) {
                var dataVarName = "%s";
                var customValidation = %s;
                
                function modifyValidationData() {
                    if (typeof window[dataVarName] !== "undefined" && window[dataVarName].elements) {
                        window[dataVarName].elements = window[dataVarName].elements.map(function(element) {
                            try {
                                var fieldData = JSON.parse(element);
                                var fieldId = fieldData.id;
                                
                                if (customValidation[fieldId]) {
                                    fieldData.regErrorMessage = customValidation[fieldId].errorMessage;
                                    fieldData.regex = customValidation[fieldId].pattern;
                                    fieldData.enableRegex = true;
                                }
                                
                                return JSON.stringify(fieldData);
                            } catch(e) {
                                return element;
                            }
                        });
                        return true;
                    }
                    return false;
                }
                
                //replace restrictive keypress handler for fields with custom validation
                //the plugin blocks non-numeric characters for phone fields, but we need to allow + for phone_numeric
                function replaceKeypressHandler() {
                    Object.keys(customValidation).forEach(function(fieldId) {
                        var inputId = "#input_%s_" + fieldId;
                        var fieldConfig = customValidation[fieldId];
                        var patternKey = fieldConfig.patternKey;
                        
                        //remove all existing keypress handlers
                        $(inputId).off("keypress");
                        
                        //for phone_numeric pattern, allow digits (0-9), plus character (+), spaces, and round brackets
                        if (patternKey === "phone_numeric") {
                            $(inputId).on("keypress", function(e) {
                                var charCode = e.which ? e.which : e.keyCode;
                                //allow digits (48-57), plus character (43), space (32), opening bracket (40), closing bracket (41)
                                if ((charCode >= 48 && charCode <= 57) || charCode === 43 || charCode === 32 || charCode === 40 || charCode === 41) {
                                    return true;
                                }
                                return false;
                            });
                        }
                    });
                }
                
                //try to modify immediately
                modifyValidationData();
                
                //also hook into gform_post_render to modify data before plugin processes it
                $(document).on("gform_post_render", function(event, form_id, current_page) {
                    if (form_id !== %s) return;
                    modifyValidationData();
                    //replace keypress handlers after plugin initializes
                    //use setTimeout to ensure plugin\'s handlers are attached first
                    setTimeout(function() {
                        replaceKeypressHandler();
                    }, 150);
                });
                
                //also try to replace handlers immediately in case form is already rendered
                setTimeout(function() {
                    replaceKeypressHandler();
                }, 200);
            });',
            $dataVarName,
            json_encode($fieldsWithCustomValidation),
            $formId,
            $formId
        );

        //add script directly to footer to ensure it runs
        //use higher priority to run after plugin scripts are output
        add_action('wp_footer', function() use ($js) {
            echo '<script type="text/javascript">' . "\n";
            echo $js . "\n";
            echo '</script>' . "\n";
        }, 999);
    }

    /**
     * Collect form data for all forms to fix multiple forms issue
     * This runs after the plugin's enqueue (priority 30 vs plugin's 10)
     * @param array $form
     * @param bool $is_ajax
     * @return void
     */
    public function collectFormData(array $form, bool $is_ajax): void {
        //check if real-time validation is enabled for this form
        if (!array_key_exists("enable_gfrtv", $form) || empty($form['enable_gfrtv'])) {
            return;
        }

        $formId = $form['id'];
        
        //check if plugin instance is available
        if (!function_exists('gf_real_time_validation')) {
            return;
        }

        $pluginInstance = gf_real_time_validation();
        if (!$pluginInstance) {
            return;
        }

        //generate fields data using plugin's public method
        $fieldsData = [];
        $supportedFieldTypes = ['text', 'textarea', 'image_choice', 'multi_choice', 'radio', 'checkbox', 'select', 'number', 'name', 'date', 'phone', 'email', 'address', 'website', 'time', 'consent', 'product', 'quantity', 'option', 'shipping'];
        
        foreach ($form['fields'] as $field) {
            if (in_array($field->type, $supportedFieldTypes) && ($field->isRequired || ($field->enableGFRTV && $field->regexpValue))) {
                //use plugin's public genarate_js_data method directly
                $fieldData = $pluginInstance->genarate_js_data($field);
                if ($fieldData) {
                    $fieldsData[] = json_encode($fieldData);
                }
            }
        }

        if (!empty($fieldsData)) {
            self::$formsData[$formId] = $fieldsData;
        }
    }
}

