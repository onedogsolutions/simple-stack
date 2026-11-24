/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/*
* This JS file is loaded for server and apps details screen only.
* It handles functions related to setting up email addresses and
* sending bulk emails from those screens.
*/

/* global emailaddressesparams */
(function ($, emailaddressesparams) {

    $(document).ready(function () {
        init();
    });

    // For hide or show schedule datetime option.
    function initScheduleOptionToggle() {

        // Show or hide schedule datetime option when page load.
        if (jQuery('#sstk_compose_email_schedule_email_enable').is(":checked")) {
            jQuery('#sstk_compose_email_schedule_email_datetime').parent().parent().show();
            jQuery('#sstk-compose-email-save-draft').text(emailaddressesparams.i10n.schedule_later_btn);
        } else {
            jQuery('#sstk_compose_email_schedule_email_datetime').parent().parent().hide();
            jQuery('#sstk-compose-email-save-draft').text(emailaddressesparams.i10n.save_draft_btn);
        }

        // Show or hide schedule datetime option on enable or disable.
        jQuery('#sstk_compose_email_schedule_email_enable').on('click', function (e) {
            if (jQuery(this).is(":checked")) {
                jQuery('#sstk_compose_email_schedule_email_datetime').parent().parent().show();
                jQuery('#sstk-compose-email-save-draft').text(emailaddressesparams.i10n.schedule_later_btn);
            } else {
                jQuery('#sstk_compose_email_schedule_email_datetime').parent().parent().hide();
                jQuery('#sstk-compose-email-save-draft').text(emailaddressesparams.i10n.save_draft_btn);
            }
        });
    }

    // Add & save fields of email addresses tab.
    function initAddEmailAddressOptions() {
        // for add/save the data of email addresses tab in custom post type.
        $('body').on('click', '#sstk-email-address-fields-save', function (e) {
            e.preventDefault();

            var validated = initEmailFieldsValidation();

            if (!validated) {
                // do nothing
            } else {

                var action = $(this).data('action');
                var nonce = $(this).data('nonce');
                var post_id = $(this).data('post_id');

                var sstk_email_addresses_first_name = $('#sstk_email_addresses_first_name').val();
                var sstk_email_addresses_last_name = $('#sstk_email_addresses_last_name').val();
                var sstk_email_addresses_company = $('#sstk_email_addresses_company').val();
                var sstk_email_addresses_email_id = $('#sstk_email_addresses_email_id').val();
                var sstk_email_addresses_notes = $('#sstk_email_addresses_notes').val();

                jQuery("<span class='display_waiting_message'> " + emailaddressesparams.i10n.add_wait_msg + "</span>").insertAfter('#sstk-email-address-fields-save');
                jQuery('#sstk-email-address-fields-save').attr('disabled', 'disabled');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        post_id: post_id,
                        sstk_email_addresses_first_name: sstk_email_addresses_first_name,
                        sstk_email_addresses_last_name: sstk_email_addresses_last_name,
                        sstk_email_addresses_company: sstk_email_addresses_company,
                        sstk_email_addresses_email_id: sstk_email_addresses_email_id,
                        sstk_email_addresses_notes: sstk_email_addresses_notes,
                    },
                    success: function (data) {
                        jQuery('.display_waiting_message').remove();
                        alert(data.data.msg);
                        location.reload();
                    },
                    error: function (error) {
                        jQuery('.display_waiting_message').remove();
                        jQuery('#sstk-email-address-fields-save').removeAttr('disabled');
                        alert(error);
                        location.reload();
                    }
                });

            }
        });

        // For delete email address entry
        $('body').on('click', '.sstk_delete_entry', function (e) {
            e.preventDefault();

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var entry_id = $(this).data('entry_id');
            var parent_id = $(this).data('parent_id');

            jQuery('.sstk_delete_wait_msg').remove();

            // Confirmation for deleting the entry
            var confirm_delete = confirm(emailaddressesparams.i10n.confirm_entry_delete);

            if (confirm_delete) {

                jQuery('.sstk_email_list_table').addClass('sstk_disable_email_table');
                jQuery("<span class='sstk_delete_wait_msg'> " + emailaddressesparams.i10n.delete_wait_msg + "</span>").insertAfter(jQuery(this));

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        entry_id: entry_id,
                        parent_id: parent_id,
                    },
                    success: function (data) {
                        alert(data.data.msg);
                        location.reload();
                    },
                    error: function (error) {
                        alert(error);
                        location.reload();
                    }
                });
            }

        });
    }

    // Compose email tab - actions.
    function initComposeEmailOptions() {
        // for save the fields of compose email tab.
        $('body').on('click', '#sstk-compose-email-save-draft', function (e) {
            e.preventDefault();

            var validated = initComposeEmailFieldsValidation();

            if (!validated) {
                // do nothing
            } else {

                var action = $(this).data('action');
                var nonce = $(this).data('nonce');
                var post_id = $(this).data('post_id');

                var sstk_compose_email_send_to_server_emails = $('#sstk_compose_email_send_to_server_emails').val();
                var sstk_compose_email_other_emails = $('#sstk_compose_email_other_emails').val();
                var sstk_compose_email_from_name = $('#sstk_compose_email_from_name').val();
                var sstk_compose_email_reply_to = $('#sstk_compose_email_reply_to').val();
                var sstk_compose_email_subject = $('#sstk_compose_email_subject').val();
                var sstk_compose_email_body = $('#sstk_compose_email_body').val();
                var sstk_compose_email_schedule_datetime = $('#sstk_compose_email_schedule_email_datetime').val();

                var sstk_compose_email_schedule;
                if (jQuery('#sstk_compose_email_schedule_email_enable').is(":checked")) {
                    sstk_compose_email_schedule = 1;
                    if (sstk_compose_email_schedule_datetime == '') {
                        alert(emailaddressesparams.i10n.schedule_datetime);
                        return false;
                    }
                } else {
                    sstk_compose_email_schedule = 0;
                }

                jQuery("<span class='display_waiting_message'> " + emailaddressesparams.i10n.save_wait_msg + "</span>").insertAfter('#sstk-compose-email-save-draft');
                initDisableButtonsComposeEmail();

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        post_id: post_id,
                        sstk_compose_email_schedule: sstk_compose_email_schedule,
                        sstk_compose_email_schedule_datetime: sstk_compose_email_schedule_datetime,
                        sstk_compose_email_send_to_server_emails: sstk_compose_email_send_to_server_emails,
                        sstk_compose_email_other_emails: sstk_compose_email_other_emails,
                        sstk_compose_email_from_name: sstk_compose_email_from_name,
                        sstk_compose_email_reply_to: sstk_compose_email_reply_to,
                        sstk_compose_email_subject: sstk_compose_email_subject,
                        sstk_compose_email_body: sstk_compose_email_body,
                    },
                    success: function (data) {
                        jQuery('.display_waiting_message').remove();
                        // Show confirmation message.
                        if (sstk_compose_email_schedule == 1) {
                            var scheduled_confim_msg = emailaddressesparams.i10n.schedule_confirm_msg + sstk_compose_email_schedule_datetime;
                            alert(scheduled_confim_msg);
                        } else {
                            alert(data.data.msg);
                        }
                        location.reload();
                    },
                    error: function (error) {
                        jQuery('.display_waiting_message').remove();
                        alert(error);
                        location.reload();
                    }
                });
            }
        });

        // For send the email now.
        $('body').on('click', '#sstk-compose-email-send-now', function (e) {
            e.preventDefault();

            var validated = initComposeEmailFieldsValidation();

            if (!validated) {
                // do nothing
            } else {

                var action = $(this).data('action');
                var nonce = $(this).data('nonce');
                var post_id = $(this).data('post_id');

                var sstk_compose_email_send_to_server_emails = $('#sstk_compose_email_send_to_server_emails').val();
                var sstk_compose_email_other_emails = $('#sstk_compose_email_other_emails').val();
                var sstk_compose_email_from_name = $('#sstk_compose_email_from_name').val();
                var sstk_compose_email_reply_to = $('#sstk_compose_email_reply_to').val();
                var sstk_compose_email_subject = $('#sstk_compose_email_subject').val();
                var sstk_compose_email_body = $('#sstk_compose_email_body').val();

                jQuery("<span class='display_waiting_message'> " + emailaddressesparams.i10n.send_wait_msg + "</span>").insertAfter('#sstk-compose-email-send-now');
                initDisableButtonsComposeEmail();

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        post_id: post_id,
                        sstk_compose_email_send_to_server_emails: sstk_compose_email_send_to_server_emails,
                        sstk_compose_email_other_emails: sstk_compose_email_other_emails,
                        sstk_compose_email_from_name: sstk_compose_email_from_name,
                        sstk_compose_email_reply_to: sstk_compose_email_reply_to,
                        sstk_compose_email_subject: sstk_compose_email_subject,
                        sstk_compose_email_body: sstk_compose_email_body,
                    },
                    success: function (data) {
                        jQuery('.display_waiting_message').remove();
                        alert(data.data.msg);
                        location.reload();
                    },
                    error: function (error) {
                        jQuery('.display_waiting_message').remove();
                        alert(error);
                        location.reload();
                    }
                });

            }
        });
    }

    // Sent email tab - actions.
    function initSentEmailDetails() {

        // To close the sent email details popup
        jQuery('body').on('click', '.sstk_sent_email_details_close', function (e) {
            e.preventDefault();
            jQuery('.sstk_sent_email_list_sec').removeClass('sstk_sent_email_list_disable');
            jQuery('#sstk_sent_email_details_popup_sec').hide();
            jQuery('#sstk_sent_email_details_popup_sec').remove();
        });

        // For view the sent email details.
        $('body').on('click', '.sstk_view_email_details', function (e) {
            e.preventDefault();
            var enable_btn = $(this);
            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var post_id = $(this).data('post_id');

            // Disable the button
            jQuery(this).attr('disabled', 'disabled');
            jQuery('.sstk_page_loading').addClass('sstk_sent_email_list_disable');
            jQuery('#sstk_sent_email_details_popup_sec').remove();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce,
                    post_id: post_id
                },
                success: function (html) {
                    enable_btn.removeAttr('disabled');
                    jQuery('.sstk_page_loading').removeClass('sstk_sent_email_list_disable');
                    jQuery('body').append(html);
                },
                error: function (error) {
                    enable_btn.removeAttr('disabled');
                    jQuery('.sstk_page_loading').removeClass('sstk_sent_email_list_disable');
                }
            });

        });

        // For delete sent emails entries
        $('body').on('click', '.sstk_delete_sent_entry,#sstk-sent-email-delete-all', function (e) {
            e.preventDefault();
            var enable_btn = $(this);
            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var entry_id = $(this).data('entry_id');
            var parent_id = $(this).data('parent_id');

            jQuery('.sstk_delete_wait_msg').remove();

            // Confirmation for deleting the entry
            var confirm_delete = confirm(emailaddressesparams.i10n.confirm_entry_delete);

            if (confirm_delete) {

                // Disable the button
                jQuery(this).attr('disabled', 'disabled');
                jQuery('.sstk_sent_email_list_sec').addClass('sstk_sent_email_list_disable');
                jQuery("<span class='sstk_delete_sent_entry'> " + emailaddressesparams.i10n.delete_wait_msg + "</span>").insertAfter(enable_btn);

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        entry_id: entry_id,
                        parent_id: parent_id,
                    },
                    success: function (data) {
                        alert(data.data.msg);
                        location.reload();
                    },
                    error: function (error) {
                        alert(error);
                        location.reload();
                    }
                });
            }

        });

        var page = 1;
        var parent_id = jQuery('.sstk_sent_emails_list_main_sec').attr('data-parent_id');
        var nonce = jQuery('.sstk_sent_emails_list_main_sec').attr('data-nonce');
        var action = jQuery('.sstk_sent_emails_list_main_sec').attr('data-action');

        // Load page 1 as the default
        sstk_load_all_emails_entries(page, parent_id, nonce, action);

        // Handle the pagination clicks.
        $('body').on('click', '.sstk_sent_emails_list_main_sec .sstk-universal-pagination li.active', function (e) {
            var page = $(this).attr('p');
            sstk_load_all_emails_entries(page, parent_id, nonce, action);
        });
    }

    // Load all sent emails entries.
    function sstk_load_all_emails_entries(page, parent_id, nonce, action) {
        // Start the transition
        jQuery(".sstk_page_loading").fadeIn().css('background', '#ffffff');
        jQuery('.sstk_page_loading').addClass('sstk_sent_email_list_disable');

        if (action != undefined) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce,
                    page: page,
                    parent_id: parent_id
                },
                success: function (response) {
                    jQuery('.sstk_page_loading').removeClass('sstk_sent_email_list_disable');
                    // If successful Append the data into our html container
                    jQuery(".sstk_sent_emails_list_main_sec").html(response);
                    // End the transition
                    jQuery(".sstk_page_loading").css({ 'background': 'none', 'transition': 'all 1s ease-out' });
                },
                error: function (error) {
                    jQuery('.sstk_page_loading').removeClass('sstk_sent_email_list_disable');
                }
            });
        }
    }

    // Function for validate the email addresses tab fields.
    function initEmailFieldsValidation() {
        // Get fields to check for validation.
        var sstk_email_addresses_first_name = $('#sstk_email_addresses_first_name').val();
        var sstk_email_addresses_last_name = $('#sstk_email_addresses_last_name').val();
        var sstk_email_addresses_email_id = $('#sstk_email_addresses_email_id').val();

        if (sstk_email_addresses_first_name == '') {
            alert(emailaddressesparams.i10n.empty_firstname)
            return false;
        } else if (sstk_email_addresses_last_name == '') {
            alert(emailaddressesparams.i10n.empty_lastname)
            return false;
        } else if (sstk_email_addresses_email_id == '') {
            alert(emailaddressesparams.i10n.empty_email)
            return false;
        } else {
            if ($('#sstk_email_addresses_email_id').attr('aria-invalid') == 'true') {
                return false;
            } else {
                return true;
            }
        }
    }

    // Function for validate the compose email tab fields.
    function initComposeEmailFieldsValidation() {
        // Get fields to check for validation
        var sstk_compose_email_other_emails = $('#sstk_compose_email_other_emails').val();
        var sstk_compose_email_from_name = $('#sstk_compose_email_from_name').val();
        var sstk_compose_email_reply_to = $('#sstk_compose_email_reply_to').val();
        var sstk_compose_email_subject = $('#sstk_compose_email_subject').val();
        var sstk_compose_email_body = $('#sstk_compose_email_body').val();

        // Remove extra spaces from the email string
        var all_emails = sstk_compose_email_other_emails.replace(/ /g, '');
        if (sstk_compose_email_other_emails != '') {
            var valid_email = true;

            /*
            * Regex for email validation
            * Email should be something like : example@gmail.com
            */
            var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

            // Split the string and create an emails array
            var emails_result = all_emails.split(",");
            // Filter the emails array if empty values
            var filter_emails = emails_result.filter(function (el) {
                return el != null && el != "";
            });

            // Check filtered array is empty or not
            if (filter_emails == '' || filter_emails == undefined) {
                jQuery('#sstk_compose_email_other_emails').val('');
            } else {
                // Check validation for the each email address
                jQuery('#sstk_compose_email_other_emails').val(filter_emails.join(","));
                $.each(filter_emails, function (index, value) {
                    if (!regex.test(value)) {
                        valid_email = false;
                    }
                });
                if (valid_email == false) {
                    alert(emailaddressesparams.i10n.invalid_other_emails);
                    return valid_email;
                }
            }
        }

        // Remove extra spaces from the reply to email string
        var reply_to_email = sstk_compose_email_reply_to.replace(/ /g, '');
        if (sstk_compose_email_reply_to != '') {
            /*
            * Regex for email validation
            * Email should be something like : example@gmail.com
            */
            var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

            if (!regex.test(reply_to_email)) {
                alert(emailaddressesparams.i10n.invalid_reply_to);
                return false;
            }
        }

        if (sstk_compose_email_subject == '') {
            alert(emailaddressesparams.i10n.empty_subject);
            return false;
        } else if (sstk_compose_email_body == '') {
            alert(emailaddressesparams.i10n.empty_body);
            return false;
        } else if (sstk_compose_email_from_name == '') {
            alert(emailaddressesparams.i10n.empty_from_name);
            return false;
        } else if (sstk_compose_email_reply_to == '') {
            alert(emailaddressesparams.i10n.empty_reply_to);
            return false;
        } else {
            return true;
        }
    }

    // Disable buttons on compose email tab.
    function initDisableButtonsComposeEmail() {
        jQuery('#sstk-compose-email-save-draft').attr('disabled', 'disabled');
        jQuery('#sstk-compose-email-send-now').attr('disabled', 'disabled');
        jQuery('#sstk-compose-email-schedule-email').attr('disabled', 'disabled');
    }

    function init() {
        initAddEmailAddressOptions();
        initComposeEmailOptions();
        initScheduleOptionToggle();
        initSentEmailDetails();
    }

})(jQuery, emailaddressesparams);