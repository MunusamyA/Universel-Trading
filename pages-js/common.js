(function () {

    function createToastContainer() {
        if ($('#globalToastContainer').length === 0) {
            $('body').append(`
                <div id="globalToastContainer"
                     style="
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 99999;
                        display: flex;
                        flex-direction: column;
                        gap: 10px;
                     ">
                </div>
            `);
        }
    }

    function getToastStyle(type) {
        switch (type) {
            case 'success':
                return {
                    bg: '#198754',
                    color: '#ffffff',
                    icon: 'mdi-check-circle-outline'
                };

            case 'error':
                return {
                    bg: '#dc3545',
                    color: '#ffffff',
                    icon: 'mdi-close-circle-outline'
                };

            case 'warning':
                return {
                    bg: '#ffc107',
                    color: '#212529',
                    icon: 'mdi-alert-circle-outline'
                };

            case 'info':
                return {
                    bg: '#0dcaf0',
                    color: '#212529',
                    icon: 'mdi-information-outline'
                };

            default:
                return {
                    bg: '#0d6efd',
                    color: '#ffffff',
                    icon: 'mdi-bell-outline'
                };
        }
    }

    window.showToast = function (type, message, duration = 5000) {
        createToastContainer();

        let style = getToastStyle(type);
        let toastId = 'toast_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

        let toastHtml = `
            <div id="${toastId}"
                 class="custom-global-toast"
                 style="
                    min-width: 280px;
                    max-width: 380px;
                    background: ${style.bg};
                    color: ${style.color};
                    border-radius: 8px;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.18);
                    overflow: hidden;
                    opacity: 0;
                    transform: translateX(30px);
                    transition: all 0.25s ease;
                 ">

                <div style="
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    padding: 12px 14px;
                ">
                    <div style="font-size: 20px; line-height: 1;">
                        <i class="mdi ${style.icon}"></i>
                    </div>

                    <div style="flex: 1; font-size: 14px; line-height: 1.4;">
                        ${message}
                    </div>

                    <button type="button"
                            class="toast-close-btn"
                            style="
                                border: none;
                                background: transparent;
                                color: ${style.color};
                                font-size: 20px;
                                line-height: 1;
                                padding: 0;
                                cursor: pointer;
                            ">
                        &times;
                    </button>
                </div>

                <div class="toast-progress"
                     style="
                        height: 3px;
                        width: 100%;
                        background: rgba(255,255,255,0.7);
                     ">
                </div>
            </div>
        `;

        $('#globalToastContainer').append(toastHtml);

        let toast = $('#' + toastId);
        let progress = toast.find('.toast-progress');

        setTimeout(function () {
            toast.css({
                opacity: '1',
                transform: 'translateX(0)'
            });
        }, 20);

        let remainingTime = duration;
        let startTime = Date.now();
        let timer = null;

        function startTimer() {
            startTime = Date.now();

            timer = setTimeout(function () {
                removeToast();
            }, remainingTime);

            progress.css({
                transition: `width ${remainingTime}ms linear`,
                width: '0%'
            });
        }

        function pauseTimer() {
            clearTimeout(timer);

            let elapsed = Date.now() - startTime;
            remainingTime = remainingTime - elapsed;

            if (remainingTime < 0) {
                remainingTime = 0;
            }

            let currentWidth = progress.width();
            let parentWidth = progress.parent().width();
            let percentage = parentWidth > 0 ? (currentWidth / parentWidth) * 100 : 0;

            progress.css({
                transition: 'none',
                width: percentage + '%'
            });
        }

        function removeToast() {
            clearTimeout(timer);

            toast.css({
                opacity: '0',
                transform: 'translateX(30px)'
            });

            setTimeout(function () {
                toast.remove();
            }, 250);
        }

        toast.on('mouseenter', function () {
            pauseTimer();
        });

        toast.on('mouseleave', function () {
            if (remainingTime > 0) {
                startTimer();
            } else {
                removeToast();
            }
        });

        toast.find('.toast-close-btn').on('click', function () {
            removeToast();
        });

        startTimer();
    };

    window.setButtonLoading = function (buttonId, loadingText = 'Please wait...') {
        let btn = $('#' + buttonId);

        if (!btn.data('original-text')) {
            btn.data('original-text', btn.html());
        }

        btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> ' + loadingText
        );
    };

    window.resetButtonLoading = function (buttonId) {
        let btn = $('#' + buttonId);
        let originalText = btn.data('original-text') || 'Submit';

        btn.prop('disabled', false).html(originalText);
    };

    window.handleApiError = function (response) {
        if (response && response.redirect) {
            showToast('error', response.message || 'Session expired', 5000);

            setTimeout(function () {
                window.location.href = response.redirect;
            }, 1000);

            return;
        }

        showToast('error', response.message || 'Something went wrong', 5000);
    };

})();