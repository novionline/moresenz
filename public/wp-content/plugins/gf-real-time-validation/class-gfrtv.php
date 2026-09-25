<?php
if (! defined('ABSPATH')) exit;
GFForms::include_addon_framework();

class GFRTVAddOn extends GFAddOn {

    protected $_version = REAL_TIME_VALIDATION_ADDON_VERSION;
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'real-time-validation';
    protected $_path = 'gf-real-time-validation/real-time-validation.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Real Time Validation For Gravity Forms';
    protected $_short_title = 'Real Time Validation';

    protected $_supported_field_types = ['text', 'textarea', 'image_choice', 'multi_choice', 'radio', 'checkbox', 'select', 'number', 'name', 'date', 'phone', 'email', 'address', 'website', 'time', 'consent', 'product', 'quantity', 'option', 'shipping'];
    protected $_input_has_multiple_types = ['product', 'quantity', 'option', 'shipping'];
    protected $_input_has_sub_types = ['address', 'name', 'time'];

    private static $_instance = null;

    /**
     * Get an instance of this class.
     *
     * @return GFRTVAddOn
     */
    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFRTVAddOn();
        }
        return self::$_instance;
    }

    /**
     * Handles hooks and loading of language files.
     */
    public function init() {
        parent::init();
        add_action('gform_enqueue_scripts', array($this, 'gfrtv_frontend_enqueue_scripts'), 10, 2);
        add_filter('gform_form_settings_fields', array($this, 'gfrtv_form_settings'), 10, 2);

        add_action('gform_field_advanced_settings', array($this, 'gfrtv_advanced_settings'), 10, 2);
        add_action('gform_editor_js', array($this, 'gfrtv_editor_script'));
        add_filter('gform_field_validation', array($this, 'gfrtv_custom_validation'), 10, 4);
        add_filter('gform_tooltips', array($this, 'gfrtv_add_tooltips'));
    }

    public function gfrtv_advanced_settings($position, $form_id) {
        if ($position == 150) {
?>
            <li class="enable_gfrtv_setting field_setting">
                <input type="checkbox" id="enable_gfrtv" onclick="SetFieldProperty('enableGFRTV', this.checked);" />
                <label for="enable_gfrtv" style="display:inline;">
                    <?php esc_html_e("Enable Real Time Validation", "gfrtv"); ?>
                    <?php gform_tooltip("gfrtv_enable_regex"); ?>
                </label>
            </li>
            <li class="regexp_setting field_setting">
                <label for="regexp_value" class="section_label">Enter RegEx pattern <?php gform_tooltip("gfrtv_pattern"); ?></label>
                <textarea id="regexp_value" fieldheight-2 placeholder="Your regex..." onChange="SetFieldProperty('regexpValue', jQuery(this).val());"></textarea>
            </li>
            <li class="regexp_validation_message field_setting">
                <label for="regexp_validation_message" class="section_label">
                    <?php esc_html_e("Validation failed message", "gfrtv"); ?>
                    <?php gform_tooltip("gfrtv_error_message"); ?>
                </label>
                <input type="text" name="regexp_validation_message" id="regexp_validation_message" onChange="SetFieldProperty('regexVFMessage', this.value);">
            </li>
        <?php
        }
    }

    public function gfrtv_editor_script() {
        ?>
        <script type='text/javascript'>
            //adding setting to fields of type "phone"
            fieldSettings.text += ", .enable_gfrtv_setting";
            fieldSettings.text += ", .regexp_validation_message";
            fieldSettings.text += ", .regexp_setting";
            fieldSettings.number += ", .enable_gfrtv_setting";
            fieldSettings.number += ", .regexp_setting";
            fieldSettings.number += ", .regexp_validation_message";

            //binding to the load field settings event to initialize the checkbox
            jQuery(document).bind("gform_load_field_settings", function(event, field, form) {
                jQuery("#enable_gfrtv").prop('checked', Boolean(rgar(field, 'enableGFRTV')));
                jQuery("#regexp_value").val(field["regexpValue"]);
                jQuery("#regexp_validation_message").val(field["regexVFMessage"]);

                if (jQuery("#enable_gfrtv").is(":checked")) {
                    jQuery(".regexp_setting, .regexp_validation_message").show();
                } else {
                    jQuery(".regexp_setting, .regexp_validation_message").hide();
                }
            });

            jQuery(document).on('change', '#enable_gfrtv', function(e) {
                jQuery(".regexp_setting, .regexp_validation_message").slideToggle();
            });
        </script>
<?php
    }

    public function get_error_message($field) {
        $texts = array(
            'text'              => esc_html__('This field is required', 'gfrtv'),
            'textarea'          => esc_html__('This field is required.', 'gfrtv'),
            'select'            => esc_html__('This field is required.', 'gfrtv'),
            'number'            => esc_html__('This field is required.', 'gfrtv'),
            'checkbox'          => esc_html__('This field is required.', 'gfrtv'),
            'radio'             => esc_html__('This field is required.', 'gfrtv'),
            'date'              => esc_html__('This field is required.', 'gfrtv'),
            'phone'             => esc_html__('This field is required.', 'gfrtv'),
            'email'             => esc_html__('The email address entered is invalid, please check the formatting (e.g. email@domain.com)', 'gfrtv'),
            'website'           => esc_html__('This field is required.', 'gfrtv'),
            'list'              => esc_html__('This field is required.', 'gfrtv'),
            'multiselect'       => esc_html__('This field is required.', 'gfrtv'),
            'consent'           => esc_html__('This field is required.', 'gfrtv'),
            'post_title'        => esc_html__('This field is required.', 'gfrtv'),
            'post_body'         => esc_html__('This field is required.', 'gfrtv'),
            'post_content'      => esc_html__('This field is required.', 'gfrtv'),
            'post_excerpt'      => esc_html__('This field is required.', 'gfrtv'),
            'post_tags'         => esc_html__('This field is required.', 'gfrtv'),
            'post_category'     => esc_html__('This field is required.', 'gfrtv'),
            'post_custom_field' => esc_html__('This field is required.', 'gfrtv'),
            'product'           => esc_html__('This field is required.', 'gfrtv'),
            'quantity'          => esc_html__('This field is required.', 'gfrtv'),
            'option'            => esc_html__('This field is required.', 'gfrtv'),
            'shipping'          => esc_html__('This field is required.', 'gfrtv')
            // 'multi_choice'      => esc_html__('This field is required.', 'gfrtv'),
        );

        if (array_key_exists($field->type, $texts)) {
            return $field->errorMessage ? $field->errorMessage : $texts[$field->type];
        }

        if (in_array($field->type, $this->_input_has_sub_types)) {
            $default_text = esc_html__(' Please complete the following fields:', 'gfrtv');
            return $field->errorMessage ? $field->errorMessage . $default_text : esc_html__('This field is required. Please complete the following fields:', 'gfrtv');
        }

        if ($field->type == 'multi_choice' || $field->type == 'image_choice') {
            if ($field->choiceLimit == 'exactly') {
                return $field->errorMessage ? $field->errorMessage : esc_html(
                    sprintf(
                        /* translators: 1: minimum choices */
                        esc_html__('Select exactly %d choices.', 'gfrtv'),
                        $field->choiceLimitNumber
                    )
                );
            }

            if ($field->choiceLimit == 'range') {
                return $field->errorMessage ? $field->errorMessage :
                    sprintf(
                        /* translators: 1: minimum choices, 2: maximum choices */
                        esc_html__('Select between %1$d and %2$d choices.', 'gfrtv'),
                        $field->choiceLimitMin,
                        $field->choiceLimitMax
                    );
            }

            if ($field->choiceLimit == 'unlimited') {
                return $field->errorMessage ? $field->errorMessage : esc_html__('This field is required.', 'gfrtv');
            }
        }
    }

    public function genarate_js_data($field) {

        $field_data = array();

        $field_data['id']           = $field->id;
        $field_data['formId']       = $field->formId;
        $field_data['type']         = $field->type;

        if ($field->isRequired) {
            $field_data['errorMessage'] = $this->get_error_message($field);
        }

        if ($field->enableGFRTV && ($field->type == 'text' || $field->type == 'number')) {
            $field_data['regErrorMessage']   = $field->regexVFMessage ? $field->regexVFMessage : '';
            $field_data['regex'] = $field->regexpValue;
            $field_data['enableRegex'] = $field->enableGFRTV;
        }

        if (in_array($field->type, $this->_input_has_sub_types)) {
            $field_data['inputs'] = (object) $this->filterd_data_object($field->inputs, $field->type);
        }
        if (in_array($field->type, $this->_input_has_multiple_types)) {
            $field_data['inputType'] = $field->inputType;
        }

        if ($field->type == 'email') {
            $field_data['email_confirmation'] = $field->emailConfirmEnabled ? $field->emailConfirmEnabled : false;
            $field_data['email_empty'] = $field->errorMessage ? $field->errorMessage : esc_html__('This field is required', 'gfrtv');
            $field_data['not_match'] = esc_html__('Your emails do not match.', 'gfrtv');
        }

        if ($field->type == 'website') {
            $field_data['not_match'] = esc_html__('Please enter a valid Website URL (e.g. https://pluginscafe.com).', 'gfrtv');
        }

        if ($field->type == 'multi_choice' || $field->type == 'image_choice') {
            $field_data['limit_type'] = $field->choiceLimit;

            if ($field->choiceLimit == 'exactly') {
                $field_data['limit'] = $field->choiceLimitNumber;
            }

            if ($field->choiceLimit == 'range') {
                $field_data['min'] = $field->choiceLimitMin;
                $field_data['max'] = $field->choiceLimitMax;
            }
        }

        if (class_exists('ALPHAGF_Limit_Checkbox')) {
            if ($field->type == 'checkbox' && !empty($field->alphagf_limit_number)) {
                $field_data['limit'] = $field->alphagf_limit_number;
                $field_data['limit_type'] = 'exactly';
            }
        }


        return (object) $field_data;
    }

    public function filterd_data_object($data, $type) {
        $filtered_arr = [];
        foreach ($data as $k => $v) {
            if (!in_array('isHidden', $v)) {
                $getID = explode(".", $v['id']);
                $sub_id = "_" . $getID[1];
                if (($sub_id == '_2' && $type == 'address') || ($sub_id == '_3' && $type == 'time') || ($sub_id == '_2' && $type == 'name') || ($sub_id == '_4' && $type == 'name') || ($sub_id == '_8' && $type == 'name')) {
                    continue;
                }
                $filtered_arr[$sub_id] = array_key_exists('customLabel', $v) ? $v['customLabel'] : $v['label'];
            }
        }
        return $filtered_arr;
    }

    public function gfrtv_frontend_enqueue_scripts($form, $is_ajax) {

        $form_id = $form['id'];
        $fields_data = [];

        foreach ($form['fields'] as $field) {
            if (in_array($field->type, $this->_supported_field_types) && ($field->isRequired || ($field->enableGFRTV && $field->regexpValue))) {
                $fields_data[] = json_encode($this->genarate_js_data($field));
            } else if ($field->type == 'checkbox' && $field->alphagf_limit_number) {
                $fields_data[] = json_encode($this->genarate_js_data($field));
            }
        }

        if (!array_key_exists("enable_gfrtv", $form) or empty($form['enable_gfrtv'])) {
            return;
        }

        wp_enqueue_script('gfrtv_script', $this->get_base_url() . '/js/gfrtv_script.js', array('jquery'), $this->_version, true);
        wp_localize_script(
            'gfrtv_script',
            'gfrtv_' . $form_id,
            array(
                'elements' => $fields_data
            )
        );
    }

    public function gfrtv_custom_validation($result, $value, $form, $field) {

        if ($field->enableGFRTV && ($field->type == 'text' || $field->type == 'number')) {

            if (empty($value) || empty($field->regexpValue)) {
                return $result;
            }

            $pattern =  '/' . $field->regexpValue . '/';

            $is_valid = preg_match($pattern, $value);

            if ($result['is_valid'] && !$is_valid) {
                $result['is_valid'] = false;
                $result['message']  = $field->regexVFMessage ? $field->regexVFMessage : esc_html__('Validation Failed.', 'gfrtv');
            }
        }
        return $result;
    }

    function gfrtv_form_settings($fields, $form) {

        $fields['form_options']['fields'][] = array(
            'name'      => 'enable_gfrtv',
            'label'     => esc_html__('Enable Real Time Validation', 'gfrtv'),
            'type'      => 'toggle',
            'class'     => 'medium',
            'tooltip'   => esc_html__('Click on this button to enable real time validation.', 'gfrtv'),
        );

        return $fields;
    }

    public function gfrtv_add_tooltips() {
        $tooltips['gfrtv_enable_regex']    = esc_html__("Check this box to enable regular expression validation.", "gfrtv");
        $tooltips['gfrtv_pattern']    = esc_html__("Type your regular expression here.", "gfrtv");
        $tooltips['gfrtv_error_message']    = esc_html__("Type error message if validation fail.", "gfrtv");

        return $tooltips;
    }
}
