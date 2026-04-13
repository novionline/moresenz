/**
 * Real Time Validation Script
 */
(function ($) {
    var GF_RTV = {
        gformInputType: {
            standard: ["text", "textarea", "date", "number", "phone"],
            advanced: ["address", "name", "time"],
            pricing: ["product", "shipping", "option", "quantity"],
            choices: ["select", "checkbox", "radio", "consent"],
            number: ["number", "phone", "quantity", "singleproduct"],
            multiChoice: ["multi_choice", "image_choice"],
        },
        init: function (FD) {
            var inputId = "#input_" + FD["formId"] + "_" + FD["id"],
                fieldType = FD["type"],
                errorMessage = FD["errorMessage"],
                inputs = FD["inputs"],
                emailConfirm = FD["email_confirmation"],
                emailEmpty = FD["email_empty"],
                notMatch = FD["not_match"],
                inputType = FD["inputType"],
                multiChoiceType = FD["limit_type"] ? FD["limit_type"] : null,
                limitItems = {
                    'limitExactly': FD["limit"] ? FD["limit"] : null,
                    'limitMin': FD["min"] ? FD["min"] : null,
                    'limitMax': FD["max"] ? FD["max"] : null,
                },
                limitCheckbox = {
                    'limit': FD["limit"] ? FD["limit"] : null,
                    'type': FD['limit_type'] ? FD['limit_type'] : null
                },
                regEx = {
                    'pattern': FD['regex'],
                    'enableRegex': FD['enableRegex'],
                    'errorMessage': FD['regErrorMessage']
                };

            GF_RTV.standardField(inputId, fieldType, errorMessage, regEx);
            GF_RTV.choicesType(inputId, fieldType, errorMessage, limitCheckbox);
            GF_RTV.numberField(inputId, fieldType);
            GF_RTV.urlType(inputId, fieldType, errorMessage, notMatch);
            GF_RTV.emailInputField(
                inputId,
                fieldType,
                errorMessage,
                emailConfirm,
                emailEmpty,
                notMatch,
                inputs
            );
            GF_RTV.inputHasSubTypes(inputId, fieldType, errorMessage, inputs);
            GF_RTV.inputHasMultipleTypes(
                inputId,
                fieldType,
                errorMessage,
                inputType
            );
            GF_RTV.multipleChoices(inputId, fieldType, multiChoiceType, limitItems, errorMessage);
        },
        multipleChoices: function (inputId, fieldType, multiChoiceType, limitItems, errorMessage) {
            if (!GF_RTV.gformInputType["multiChoice"].includes(fieldType)) return;

            switch (multiChoiceType) {
                case 'unlimited':
                    GF_RTV.checkboxType(inputId, errorMessage, fieldType);
                    break;

                case 'exactly':
                    GF_RTV.exactlyChoices(inputId, fieldType, limitItems, errorMessage);
                    break;

                case 'range':
                    GF_RTV.rangeChoices(inputId, fieldType, limitItems, errorMessage);
                    break;

                default:
                    GF_RTV.radioType(inputId, errorMessage);
                    break;
            }
        },
        rangeChoices: function (inputId, fieldType, limitItems, errorMessage) {
            if (fieldType == 'image_choice') {
                GF_RTV.imageChoiceOps(inputId, limitItems.limitMax, errorMessage);
            } else {
                jQuery(inputId).on("change", function () {
                    var checkCount = jQuery(inputId + " input[type=checkbox]:checked").length;
                    if (checkCount <= parseInt(limitItems.limitMax)) {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                });
            }
        },
        exactlyChoices: function (inputId, fieldType, limitItems, errorMessage) {
            if (fieldType == 'image_choice') {
                GF_RTV.imageChoiceOps(inputId, limitItems.limitExactly, errorMessage);
            } else {
                jQuery(inputId).on("change", function () {
                    var checkCount = jQuery(inputId + " input[type=checkbox]:checked").length;

                    if (checkCount <= parseInt(limitItems.limitExactly)) {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                });
            }
        },
        imageChoiceOps: function (inputId, limitItems, errorMessage, type = '') {
            jQuery(inputId + ' .gchoice').on("click", function (e) {
                e.stopPropagation();
                e.preventDefault();

                var checkbox = jQuery(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked'));

                var parentContainer = jQuery(this).closest('.gfield_checkbox');
                var checkCount = parentContainer.find('input[type="checkbox"]:checked').length;

                if (type == 'unlimited') {
                    if (checkCount) {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                } else {
                    if (checkCount <= parseInt(limitItems)) {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                }
            });
        },
        standardField: function (inputId, fieldType, errorMessage, regEx) {
            if (!GF_RTV.gformInputType["standard"].includes(fieldType)) return;

            jQuery(inputId).on("blur", function () {
                var thisValue = jQuery(this).val();

                if (regEx.enableRegex && errorMessage !== undefined) {
                    if (thisValue != '') {
                        GF_RTV.validationSuccess(jQuery(this));
                        if (GF_RTV.regexValidationFail(regEx, thisValue)) {
                            GF_RTV.validationSuccess(jQuery(this));
                        } else {
                            GF_RTV.validationFail(jQuery(this), regEx.errorMessage);
                        }
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                } else if (regEx.enableRegex && errorMessage === undefined) {
                    if (GF_RTV.regexValidationFail(regEx, thisValue) && thisValue != '') {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else if (thisValue == '') {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else {
                        GF_RTV.validationFail(jQuery(this), regEx.errorMessage);
                    }
                } else {
                    if (thisValue != '') {
                        GF_RTV.validationSuccess(jQuery(this));
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                }
            });
        },
        regexValidationFail: function (regex, value) {
            if (regex.pattern === undefined || regex.pattern === '') {
                return false;
            }

            let regCode = new RegExp(regex.pattern);
            return regCode.test(value);
        },
        numberField: function (inputId, fieldType) {
            if (!GF_RTV.gformInputType["number"].includes(fieldType)) return;

            jQuery(inputId).keypress(function (e) {
                var charCode = e.which ? e.which : event.keyCode;

                if (String.fromCharCode(charCode).match(/[^0-9]/g))
                    return false;
            });
        },
        emailValidation: function (mail) {
            return mail.match(
                /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
            );
        },
        urlValidation: function (url) {
            var result = url.match(
                /(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})/gi
            );
            if (result == null) {
                return false;
            } else {
                return true;
            }
        },
        emailMatch: function (mail1, mail2 = "") {
            if (mail1.toLowerCase() == mail2.toLowerCase()) {
                return true;
            } else {
                return false;
            }
        },
        emailInputField: function (
            inputId,
            fieldType,
            errorMessage,
            emailConfirm,
            emailEmpty,
            notMatch
        ) {
            if (fieldType != "email") return;

            jQuery(inputId).on("blur", function () {
                var thisValue = jQuery(this).val(),
                    secValue = jQuery(`${inputId}_2`).val();

                if (thisValue == "") {
                    GF_RTV.emailValidationFail(
                        jQuery(this),
                        emailEmpty,
                        emailConfirm
                    );
                } else if (
                    thisValue != "" &&
                    !GF_RTV.emailValidation(thisValue)
                ) {
                    GF_RTV.emailValidationFail(
                        jQuery(this),
                        errorMessage,
                        emailConfirm
                    );
                } else if (
                    secValue != "" &&
                    !GF_RTV.emailMatch(thisValue, secValue) &&
                    emailConfirm
                ) {
                    GF_RTV.emailValidationFail(
                        jQuery(this),
                        notMatch,
                        emailConfirm
                    );
                } else {
                    GF_RTV.emailValidationSuccess(jQuery(this), emailConfirm);
                }
            });

            if (!emailConfirm) return;

            jQuery(`${inputId}_2`).on("blur", function () {
                var thisValue = jQuery(this).val(),
                    firstValue = jQuery(inputId).val();

                if (thisValue == "" && firstValue == "") {
                    GF_RTV.emailValidationFail(
                        jQuery(this),
                        emailEmpty,
                        emailConfirm
                    );
                } else if (
                    firstValue == "" &&
                    !GF_RTV.emailValidation(thisValue)
                ) {
                    GF_RTV.emailValidationFail(
                        jQuery(this),
                        errorMessage,
                        emailConfirm
                    );
                } else if (
                    firstValue != "" &&
                    !GF_RTV.emailMatch(thisValue, firstValue)
                ) {
                    GF_RTV.emailValidationFail(
                        jQuery(this),
                        notMatch,
                        emailConfirm
                    );
                } else {
                    GF_RTV.emailValidationSuccess(jQuery(this), emailConfirm);
                }
            });
        },
        inputHasSubTypes: function (inputId, fieldType, errorMessage, inputs) {
            if (!GF_RTV.gformInputType["advanced"].includes(fieldType)) return;
            var validInputs = [];

            jQuery.each(inputs, function (index, value) {
                var thisValid = jQuery(`${inputId}${index}`).attr(
                    "aria-invalid"
                );
                if (typeof thisValid !== "undefined" && thisValid != "false") {
                    if (!validInputs.includes(value)) {
                        validInputs.push(value);
                    }
                } else {
                    validInputs = validInputs.filter((item) => item !== value);
                }

                jQuery(`${inputId}${index}`).on("blur", function () {
                    var thisField = jQuery(this).val();

                    if (thisField == "") {
                        if (!validInputs.includes(value)) {
                            validInputs.push(value);
                        }
                    } else {
                        validInputs = validInputs.filter(
                            (item) => item !== value
                        );
                    }

                    if (validInputs.length > 0) {
                        GF_RTV.subInputValidationFail(
                            jQuery(this),
                            validInputs,
                            errorMessage
                        );
                    } else {
                        GF_RTV.subInputValidationSuccess(jQuery(this));
                    }
                });
            });
        },
        inputHasMultipleTypes: function (
            inputId,
            fieldType,
            errorMessage,
            inputType
        ) {
            if (!GF_RTV.gformInputType["pricing"].includes(fieldType)) return;

            switch (inputType) {
                case "select":
                    GF_RTV.selectType(inputId, errorMessage);
                    break;

                case "checkbox":
                    GF_RTV.checkboxType(inputId, errorMessage);
                    break;

                case "radio":
                    GF_RTV.radioType(inputId, errorMessage);
                    break;

                case "number":
                    GF_RTV.standardField(inputId, inputType, errorMessage);
                    break;

                case "singleproduct":
                case "price":
                    GF_RTV.singleProduct(inputId, errorMessage, inputType);
                    break;

                default:
                    break;
            }
        },
        singleProduct: function (inputId, errorMessage, inputType) {
            var input = inputType == "price" ? inputId : `${inputId}_1`;

            jQuery(input).on("blur", function () {
                GF_RTV.numberField(input, inputType);
                var thisValue = jQuery(this).val();
                if (thisValue != "") {
                    GF_RTV.validationSuccess(jQuery(this));
                } else {
                    GF_RTV.validationFail(jQuery(this), errorMessage);
                }
            });
        },
        choicesType: function (inputId, fieldType, errorMessage, limitItems = '') {
            if (!GF_RTV.gformInputType["choices"].includes(fieldType)) return;

            switch (fieldType) {
                case "select":
                    GF_RTV.selectType(inputId, errorMessage);
                    break;

                case "checkbox":
                    GF_RTV.checkboxType(inputId, errorMessage, limitItems);
                    break;

                case "radio":
                    GF_RTV.radioType(inputId, errorMessage);
                    break;

                case "consent":
                    GF_RTV.consentType(inputId, errorMessage);
                    break;

                default:
                    break;
            }
        },
        selectType: function (inputId, errorMessage) {
            jQuery(inputId).on("change", function () {
                var thisValue = jQuery(this).val();
                if (thisValue != "") {
                    GF_RTV.validationSuccess(jQuery(this));
                } else {
                    GF_RTV.validationFail(jQuery(this), errorMessage);
                }
            });
        },
        checkboxType: function (inputId, errorMessage, fieldType = '') {
            if (fieldType == 'image_choice') {
                GF_RTV.imageChoiceOps(inputId, '1', errorMessage, 'unlimited');
            } else {
                jQuery(inputId).on("change", function () {
                    var checkboxLength = jQuery(inputId + " input[type=checkbox]:checked").length;
                    if (checkboxLength) {
                        if (fieldType.limit != null) {
                            var errorText = 'Please select exactly ' + fieldType.limit + ' checkbox.';
                            if (checkboxLength <= fieldType.limit) {
                                GF_RTV.validationSuccess(jQuery(this));
                            } else {
                                GF_RTV.validationFail(jQuery(this), errorText);
                            }
                        } else {
                            GF_RTV.validationSuccess(jQuery(this));
                        }
                    } else {
                        GF_RTV.validationFail(jQuery(this), errorMessage);
                    }
                });
            }
        },
        consentType: function (inputId, errorMessage) {
            jQuery(`${inputId}_1`).on("change", function () {
                if (jQuery(this).prop("checked") == true) {
                    GF_RTV.validationSuccess(jQuery(this));
                } else {
                    GF_RTV.validationFail(jQuery(this), errorMessage);
                }
            });
        },
        radioType: function (inputId, errorMessage) {
            jQuery(inputId).on("change", function () {
                if (jQuery(inputId + " input[type=radio]:checked").length) {
                    GF_RTV.validationSuccess(jQuery(this));
                } else {
                    GF_RTV.validationFail(jQuery(this), errorMessage);
                }
            });
        },
        urlType: function (inputId, fieldType, errorMessage, not_match) {
            if (fieldType != "website") return;

            jQuery(inputId).on("blur", function () {
                var thisValue = jQuery(this).val();
                if (thisValue == "") {
                    GF_RTV.validationFail(jQuery(this), errorMessage);
                } else if (
                    thisValue != "" &&
                    !GF_RTV.urlValidation(thisValue)
                ) {
                    GF_RTV.validationFail(jQuery(this), not_match);
                } else {
                    GF_RTV.validationSuccess(jQuery(this));
                }
            });
        },
        subInputValidationFail: function (
            thisField,
            validInputs,
            errorMessage
        ) {
            thisField.parent().parent().parent().addClass("gfield_error");
            thisField
                .parent()
                .parent()
                .parent()
                .find(".validation_message")
                .remove();
            thisField
                .parent()
                .parent()
                .parent()
                .append(
                    '<div class="gfield_description validation_message gfield_validation_message">' +
                    errorMessage +
                    " " +
                    validInputs.join(", ") +
                    ".</div>"
                );
        },
        emailValidationFail: function (thisField, errorMessage, emailConfirm) {
            if (emailConfirm) {
                thisField.parent().parent().parent().addClass("gfield_error");
                thisField
                    .parent()
                    .parent()
                    .parent()
                    .find(".validation_message")
                    .remove();
                thisField
                    .parent()
                    .parent()
                    .parent()
                    .append(
                        '<div class="gfield_description validation_message gfield_validation_message">' +
                        errorMessage +
                        ".</div>"
                    );
            } else {
                thisField.parent().parent().addClass("gfield_error");
                thisField
                    .parent()
                    .parent()
                    .find(".validation_message")
                    .remove();
                thisField
                    .parent()
                    .parent()
                    .append(
                        '<div class="gfield_description validation_message gfield_validation_message">' +
                        errorMessage +
                        ".</div>"
                    );
            }
        },
        subInputValidationSuccess: function (thisField) {
            thisField.parent().parent().parent().removeClass("gfield_error");
            thisField
                .parent()
                .parent()
                .parent()
                .find(".validation_message")
                .remove();
        },
        validationSuccess: function (thisField, type = '') {
            thisField.parent().parent().removeClass("gfield_error");
            thisField.parent().parent().find(".validation_message").remove();
        },
        emailValidationSuccess: function (thisField, emailConfirm) {
            if (emailConfirm) {
                thisField
                    .parent()
                    .parent()
                    .parent()
                    .removeClass("gfield_error");
                thisField
                    .parent()
                    .parent()
                    .parent()
                    .find(".validation_message")
                    .remove();
            } else {
                thisField.parent().parent().removeClass("gfield_error");
                thisField
                    .parent()
                    .parent()
                    .find(".validation_message")
                    .remove();
            }
        },
        validationFail: function (thisField, errorMessage, type = '') {
            thisField.parent().parent().find(".validation_message").remove();
            thisField.parent().parent().addClass("gfield_error");
            thisField
                .parent()
                .parent()
                .append(
                    '<div class="gfield_description validation_message gfield_validation_message">' +
                    errorMessage +
                    "</div>"
                );
        }
    };

    jQuery(document).on(
        "gform_post_render",
        function (event, form_id, current_page) {
            var GFRTV_Data = window["gfrtv_" + form_id];
            if (!GFRTV_Data) return;
            const fieldsData = GFRTV_Data.elements;

            jQuery.each(fieldsData, function (index, option) {
                var FD = jQuery.parseJSON(option);
                GF_RTV.init(FD);
            });
        }
    );
})(jQuery);
