/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/*
 * This JS file is loaded for WordPress admin.
 */

/* global ajaxurl */
/* global readableCheck */

(function($, readableCheck) {

    $(document).ready(function() {
        init();
    });

    function init() {

        // Dismiss the admin notice that shows up when the bash script folded isn't readable by outside http calls.
        $('body').on('click', '.sstk-readability-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Recheck to see if the bash scripts folded can be read by outside http calls.
        // This is triggered when the user clicks a button on the notice that shows up when the bash script folded isn't readable by outside http calls.
        $('body').on('click', '.sstk-readability-check #sstk-check-again', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.check_again_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    alert(data.data.message);
                    location.reload();
                }
            });
        });

        // Dismiss the admin notice that shows up when the crons aren't scheduled and loaded.
        $('body').on('click', '.sstk-cron-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.cron_check_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Dismiss the admin notice that shows up when the PHP version does not match the condition.
        $('body').on('click', '.sstk-php-version-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.php_version_check_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Dismiss the admin notice that shows up when the plugin is on a localhost server.
        $('body').on('click', '.sstk-localhost-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.localhost_check_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Use Copy Clipboard when connection unsecure.
        function unsecuredCopyToClipboard(text) {
            const input = document.createElement("input");
            input.setAttribute("type", "text");
            input.value = text;
            document.body.appendChild(input);
            // input.focus();
            input.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Unable to copy to clipboard', err);
            }
            document.body.removeChild(input);
        }

        // Click to copy functionality.
        $('body').on('click', '.sstk-click-to-copy', function(e) {
            e.preventDefault();
            /* Get parent link & target */
            var has_parent_link = $(this).find('a').attr('href');
            var has_link_target = $(this).find('a').attr('target');
            /* Get the IP field */
            var copyText = $(this).find('.sstk-click-to-copy-text');
            /* Copy the IP inside the IP field */
            var IP = copyText.text().trim();

            if (window.isSecureContext && navigator.clipboard) {
                navigator.clipboard.writeText(IP);
            } else {
                unsecuredCopyToClipboard(IP);
            }
            /* Alert the copied IP */
            var $copiedElement = $("<span>");
            $copiedElement.addClass('sstk-copied').text($(this).find('.sstk-click-to-copy-label').data('label'));
            $(this).append($copiedElement);
            $(this).find('.sstk-click-to-copy-label').addClass('sstk-copy-hidden');
            $copiedElement.fadeIn(100);
            $copiedElement.fadeOut(2000);
            setTimeout(function() { 
                $($copiedElement).remove()
                if (typeof has_parent_link !== 'undefined' && has_parent_link !== false) {
                    if (typeof has_link_target !== 'undefined' && has_link_target !== false) {
                        window.open(has_parent_link);
                    }else{
                        window.location.href = has_parent_link;
                    }
                }
            }, 2000);
        });

        // Show copy text on mouse hover.
        $(".sstk-click-to-copy").hover(
            function() {
                $(this).children(".sstk-click-to-copy-label").removeClass("sstk-copy-hidden");
            },
            function() {
                $(this).children(".sstk-click-to-copy-label").addClass('sstk-copy-hidden');
            }
        );
    }

})(jQuery, readableCheck);