<?php
require_once __DIR__ . '/config.php';
?>

<div class="right-bar">
    <div data-simplebar class="h-100">

        <div class="rightbar-title px-3 py-4">
            <a href="javascript:void(0);" class="right-bar-toggle float-end">
                <i class="mdi mdi-close noti-icon"></i>
            </a>
            <h5 class="m-0">Settings</h5>
        </div>

        <hr class="mt-0" />

        <h6 class="text-center mb-0">Choose Demo</h6>

        <div class="p-4">

            <div class="mb-2">
                <img src="<?= BASE_URL; ?>assets/images/layouts/layout-1.jpg"
                     class="img-fluid img-thumbnail"
                     alt="Light Layout">
            </div>

            <div class="form-check form-switch mb-3">
                <input type="checkbox"
                       class="form-check-input theme-choice"
                       id="light-mode-switch"
                       data-bsStyle="<?= BASE_URL; ?>assets/css/bootstrap.min.css"
                       data-appStyle="<?= BASE_URL; ?>assets/css/app.min.css"
                       checked>
                <label class="form-check-label" for="light-mode-switch">
                    Light Mode
                </label>
            </div>

            <div class="mb-2">
                <img src="<?= BASE_URL; ?>assets/images/layouts/layout-2.jpg"
                     class="img-fluid img-thumbnail"
                     alt="Dark Layout">
            </div>

            <div class="form-check form-switch mb-3">
                <input type="checkbox"
                       class="form-check-input theme-choice"
                       id="dark-mode-switch"
                       data-bsStyle="<?= BASE_URL; ?>assets/css/bootstrap-dark.min.css"
                       data-appStyle="<?= BASE_URL; ?>assets/css/app-dark.min.css">
                <label class="form-check-label" for="dark-mode-switch">
                    Dark Mode
                </label>
            </div>

            <div class="mb-2">
                <img src="<?= BASE_URL; ?>assets/images/layouts/layout-3.jpg"
                     class="img-fluid img-thumbnail"
                     alt="RTL Layout">
            </div>

            <div class="form-check form-switch">
                <input type="checkbox"
                       class="form-check-input theme-choice"
                       id="rtl-mode-switch"
                       data-bsStyle="<?= BASE_URL; ?>assets/css/bootstrap.min.css"
                       data-appStyle="<?= BASE_URL; ?>assets/css/app-rtl.min.css">
                <label class="form-check-label" for="rtl-mode-switch">
                    RTL Mode
                </label>
            </div>

            <h6 class="mt-4">Select Custom Colors</h6>

            <div class="d-flex">

                <ul class="list-unstyled mb-0">
                    <li class="form-check">
                        <input class="form-check-input theme-color"
                               type="radio"
                               name="theme-mode"
                               id="theme-default"
                               value="default"
                               onchange="document.documentElement.setAttribute('data-theme-mode', 'default')"
                               checked>
                        <label class="form-check-label" for="theme-default">Default</label>
                    </li>

                    <li class="form-check">
                        <input class="form-check-input theme-color"
                               type="radio"
                               name="theme-mode"
                               id="theme-orange"
                               value="orange"
                               onchange="document.documentElement.setAttribute('data-theme-mode', 'orange')">
                        <label class="form-check-label" for="theme-orange">Orange</label>
                    </li>

                    <li class="form-check">
                        <input class="form-check-input theme-color"
                               type="radio"
                               name="theme-mode"
                               id="theme-teal"
                               value="teal"
                               onchange="document.documentElement.setAttribute('data-theme-mode', 'teal')">
                        <label class="form-check-label" for="theme-teal">Teal</label>
                    </li>
                </ul>

                <ul class="list-unstyled mb-0 ms-4">
                    <li class="form-check">
                        <input class="form-check-input theme-color"
                               type="radio"
                               name="theme-mode"
                               id="theme-purple"
                               value="purple"
                               onchange="document.documentElement.setAttribute('data-theme-mode', 'purple')">
                        <label class="form-check-label" for="theme-purple">Purple</label>
                    </li>

                    <li class="form-check">
                        <input class="form-check-input theme-color"
                               type="radio"
                               name="theme-mode"
                               id="theme-green"
                               value="green"
                               onchange="document.documentElement.setAttribute('data-theme-mode', 'green')">
                        <label class="form-check-label" for="theme-green">Green</label>
                    </li>

                    <li class="form-check">
                        <input class="form-check-input theme-color"
                               type="radio"
                               name="theme-mode"
                               id="theme-red"
                               value="red"
                               onchange="document.documentElement.setAttribute('data-theme-mode', 'red')">
                        <label class="form-check-label" for="theme-red">Red</label>
                    </li>
                </ul>

            </div>

        </div>

    </div>
</div>