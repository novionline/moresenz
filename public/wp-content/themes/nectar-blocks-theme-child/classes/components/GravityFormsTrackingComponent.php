<?php

namespace NoviOnline;

use NoviOnline\Core\Formatting;
use NoviOnline\Core\Singleton;

/**
 * Class GravityFormsTrackingComponent
 * @package NoviOnline
 */
class GravityFormsTrackingComponent extends Singleton {

    /**
     * Name of the tracking event that is pushed to the dataLayer.
     * @var string
     */
    const TRACKING_EVENT_NAME = 'novi_form_submit_enhanced';

    /**
     * Query parameter name that is used to track the form entry ID on redirect.
     * @var string
     */
    const TRACKING_QUERY_PARAM = 'novi-form-entry-id';

    /**
     * GravityFormsTrackingComponent constructor.
     */
    protected function __construct() {
        global $pagenow;
        if (!is_admin()) {

            //add advanced form submit event tracking using dataLayer for submission with confirmation
            add_filter('gform_confirmation', [$this, 'filterGravityFormConfirmation'], PHP_INT_MAX, 4);

            //add advanced form submit event tracking for submission with redirect
            if (isset($_GET[self::TRACKING_QUERY_PARAM]) && is_numeric($_GET[self::TRACKING_QUERY_PARAM])) {
                add_action('wp_footer', [$this, 'handleGravityFormRedirectTracking']);
            }
        }
    }

    /**
     * Add script tag which fires dataLayer event after successful form submission
     * @param string|array $confirmation
     * @param array $form
     * @param array $entry
     * @param bool $ajax
     * @return string|array
     */
    public static function filterGravityFormConfirmation(string|array $confirmation, array $form, array $entry, bool $ajax): string|array {
        //handle redirect confirmation - add the entry to the query parameter string
        if (isset($confirmation['redirect'])) {

            //handle existing query parameters
            if (str_contains($confirmation['redirect'], '?')) {
                $confirmation['redirect'] .= '&' . self::TRACKING_QUERY_PARAM . '=' . $entry['id'];
            } else {
                $confirmation['redirect'] .= '?' . self::TRACKING_QUERY_PARAM . '=' . $entry['id'];
            }

            return $confirmation;
        } elseif (is_string($confirmation)) {

            //check if entry is already tracked
            if (rgar($entry, (self::TRACKING_EVENT_NAME . '_tracked'))) return $confirmation;

            //set up basic data layer variables
            $dataLayerObject = new \stdClass();
            $dataLayerObject->event = self::TRACKING_EVENT_NAME;
            $dataLayerObject->timestamp = time();
            $dataLayerObject->formId = (int)rgar($form, 'id');
            $dataLayerObject->formTitle = (string)rgar($form, 'title');
            $dataLayerObject->entryId = (int)rgar($entry, 'id');
            $dataLayerObject->entryUrl = (string)rgar($entry, 'source_url');
            $dataLayerObject->entryIsSpam = rgar($entry, 'status') === 'spam';;

            //define array of field types which should not be tracked
            $doNotTrackTypes = ['section', 'honeypot', 'captcha', 'html', 'password', 'page', 'post_image', 'post_title', 'post_content', 'post_tags', 'post_custom_field', 'singleproduct', 'singleshipping', 'total', 'row_start', 'row_end'];

            //set up entry values
            $dataLayerObject->entryValuesFlat = [];
            $dataLayerObject->entryValues = [];
            foreach ($form['fields'] as $field) {

                //bail if product or post fields
                if (\GFCommon::is_product_field($field->type) || \GFCommon::is_post_field($field)) continue;

                //ensure the top level repeater has the right nesting level so the label is not duplicated.
                if (is_array($field->fields)) $field->nestingLevel = 0;

                if (!in_array($field->get_input_type(), $doNotTrackTypes)) {
                    $entryValueItem = new \stdClass();
                    $entryValueItem->id = (int)rgar($field, 'id') ?: '';
                    $entryValueItem->type = (string)rgar($field, 'type') ?: '';
                    $entryValueItem->label = (string)rgar($field, 'label') ?: $entryValueItem->id;
                    $entryValueItem->labelSlug = Formatting::slugify($entryValueItem->label);
                    $entryValueItem->value = '';

                    //handle value parsing
                    $parsedValue = \RGFormsModel::get_lead_field_value($entry, $field);
                    if (is_serialized($parsedValue)) $parsedValue = unserialize($parsedValue);
                    if ($entryValueItem->type === 'consent' && is_array($parsedValue)) {
                        $entryValueItem->value = trim(strip_tags($parsedValue[array_key_first($parsedValue)]));
                    } elseif (is_array($parsedValue)) {
                        $values = [];
                        foreach ($parsedValue as $valueItem) {
                            if ($valueItem) $values[] = trim(strip_tags((string)($valueItem)));
                        }
                        $entryValueItem->value = implode(',', $values);
                    } elseif (is_string($parsedValue)) {
                        $entryValueItem->value = trim(strip_tags($parsedValue));
                    }

                    //add flat values as alternative solution for online marketeers
                    $dataLayerObject->entryValuesFlat[$entryValueItem->labelSlug] = $entryValueItem->value;

                    //add entry value as object
                    $dataLayerObject->entryValues[] = $entryValueItem;
                }
            }

            ob_start(); ?>

            <script type="text/javascript">
                (function () {
                    window.dataLayer = window.dataLayer || [];
                    var submitEvent = <?php echo json_encode($dataLayerObject); ?>;
                    var eventExists = window.dataLayer.find((event) => JSON.stringify(event) === JSON.stringify(submitEvent));
                    if (!eventExists) window.dataLayer.push(submitEvent);
                }());
            </script>

            <?php

            $confirmation .= ob_get_clean();

            //set event to tracked to prevent multiple executions
            gform_update_meta($entry['id'], (self::TRACKING_EVENT_NAME . '_tracked'), true);
        }

        return $confirmation;
    }

    /**
     * Add enhanced form tracking event on redirect page
     * @return void
     */
    public static function handleGravityFormRedirectTracking(): void {
        $entryId = $_GET[self::TRACKING_QUERY_PARAM];
        $entry = \GFAPI::get_entry($entryId);
        if (!is_wp_error($entry)) {
            $form = \GFAPI::get_form($entry['form_id']);
            if ($form) {
                $confirmation = self::filterGravityFormConfirmation('', $form, $entry, false);
                if ($confirmation) echo $confirmation;
            }
        }
    }
}