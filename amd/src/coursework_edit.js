define('mod_coursework/coursework_edit',
    ['jquery', 'core/notification', 'core/modal', 'core/str'], function($, Notification, Modal, str) {
        return {
            init: function() {
                $(document).ready(function() {
                    // Submission finalisation.
                    $('.coursework-actions').on('click', '.submission-finalisation', function(e) {
                        e.preventDefault();
                        const action = $(this).attr('data-action');
                        const studentname = $(this).attr('data-student');
                        const url = $(this).attr('href');
                        Notification.confirm(
                            str.getString('notification_confirm_label', 'mod_coursework'),
                            str.getString('confirm_finalise', 'mod_coursework', {'action': action, 'studentname': studentname}),
                            str.getString('notification_yes_label', 'mod_coursework'),
                            str.getString('notification_no_label', 'mod_coursework'),
                            function() {
                                // Redirect to URL.
                                window.location.href = url;
                            }
                        );
                    });
                });
            }
        };
    }
);
