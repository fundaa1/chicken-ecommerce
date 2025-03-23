jQuery(document).ready(function ($) {
    // Start launch process
    $('#start-launch-process').on('click', function () {
        var $button = $(this);
        var $progress = $('.launch-progress');
        var $warning = $('.launch-warning');

        if (!confirm(chicken_ecommerce_launch.i18n.confirm_launch)) {
            return;
        }

        $button.prop('disabled', true).text('Starting launch process...');
        $warning.fadeOut();
        $progress.fadeIn();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'start_launch_process',
                nonce: chicken_ecommerce_launch.nonce
            },
            success: function (response) {
                if (response.success) {
                    startProgressCheck();
                } else {
                    showError('Failed to start launch process');
                    $button.prop('disabled', false).text('Start Launch Process');
                }
            },
            error: function () {
                showError('Failed to start launch process');
                $button.prop('disabled', false).text('Start Launch Process');
            }
        });
    });

    // Check launch progress
    function startProgressCheck() {
        var progressInterval = setInterval(function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_launch_status',
                    nonce: chicken_ecommerce_launch.nonce
                },
                success: function (response) {
                    if (response.success) {
                        updateProgress(response.data);

                        if (response.data.completed) {
                            clearInterval(progressInterval);
                            showCompletion();
                        } else if (response.data.error) {
                            clearInterval(progressInterval);
                            showError(response.data.error);
                        }
                    }
                },
                error: function () {
                    clearInterval(progressInterval);
                    showError('Failed to check launch status');
                }
            });
        }, 2000);
    }

    // Update progress display
    function updateProgress(status) {
        var $progressBar = $('.progress-bar .progress');
        var $progressText = $('.progress-text');
        var $launchLog = $('.launch-log');

        $progressBar.css('width', status.progress + '%');
        $progressText.text(status.current_step);

        // Update log entries
        if (status.log && status.log.length > 0) {
            $launchLog.empty();
            status.log.forEach(function (entry) {
                var $entry = $('<div>')
                    .addClass('log-entry')
                    .addClass(entry.status);

                $entry.append(
                    $('<span>')
                        .addClass('timestamp')
                        .text(entry.timestamp)
                );

                $entry.append(
                    $('<span>')
                        .addClass('message')
                        .text(entry.message)
                );

                $launchLog.append($entry);
            });

            // Scroll to bottom of log
            $launchLog.scrollTop($launchLog[0].scrollHeight);
        }
    }

    // Show completion message
    function showCompletion() {
        var $complete = $('.launch-complete');
        var $progress = $('.launch-progress');

        $progress.fadeOut(function () {
            $complete.fadeIn();
        });
    }

    // Show error message
    function showError(message) {
        var $error = $('<div>')
            .addClass('notice notice-error')
            .append(
                $('<p>').text(message)
            );

        $('.launch-progress').prepend($error);

        // Scroll to top to show error
        $('html, body').animate({
            scrollTop: $error.offset().top - 50
        }, 500);
    }

    // Handle post-launch checklist
    $('.post-launch-checklist li').on('click', function () {
        $(this).toggleClass('completed');
    });
}); 