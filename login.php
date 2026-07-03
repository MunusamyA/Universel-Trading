<?php
require_once 'includes/security.php';

secureSessionStart();

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit;
}

$page_title = "Login | Universal ERP";
$base_url = "";
?>
<!doctype html>
<html lang="en">

<head>
    <?php include('includes/head.php'); ?>
</head>

<body class="account-bg">

    <!-- Loader -->
    <?php include('includes/pre-loader.php'); ?>

    <div class="account-pages my-5 pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">

                    <div class="card">
                        <div class="card-body">

                            <div class="text-center mt-4">
                                <div class="mb-3">
                                    <a href="javascript:void(0);" class="auth-logo">
                                        <img src="assets/images/logo-dark.png"
                                             height="30"
                                             class="logo-dark mx-auto"
                                             alt="Logo">

                                        <img src="assets/images/logo-light.png"
                                             height="30"
                                             class="logo-light mx-auto"
                                             alt="Logo">
                                    </a>
                                </div>
                            </div>

                            <div class="p-3">
                                <h4 class="font-size-18 text-muted mt-2 text-center">
                                    Welcome Back!
                                </h4>

                                <p class="text-muted text-center mb-4">
                                    Sign in to continue to Universal ERP.
                                </p>

                                <form class="form-horizontal" id="loginForm" autocomplete="off">

                                    <?= csrfTokenInput(); ?>

                                    <div class="mb-3">
                                        <label class="form-label" for="username">
                                            Username / Email / Mobile
                                        </label>

                                        <input type="text"
                                               class="form-control"
                                               id="username"
                                               name="username"
                                               placeholder="Enter username, email or mobile">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="password">
                                            Password
                                        </label>

                                        <div class="input-group">
                                            <input type="password"
                                                   class="form-control"
                                                   id="password"
                                                   name="password"
                                                   placeholder="Enter password">

                                            <button class="btn btn-light"
                                                    type="button"
                                                    id="togglePassword">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3 row mt-4">
                                        <div class="col-sm-6">
                                            <div class="form-checkbox">
                                                <input type="checkbox"
                                                       class="form-check-input me-1"
                                                       id="remember_me"
                                                       name="remember_me"
                                                       value="1">

                                                <label class="form-check-label" for="remember_me">
                                                    Remember me
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-sm-6 text-end">
                                            <button class="btn btn-primary w-md waves-effect waves-light"
                                                    type="submit"
                                                    id="loginBtn">
                                                Log In
                                            </button>
                                        </div>
                                    </div>

                                </form>
                            </div>

                        </div>
                    </div>

                    <div class="mt-5 text-center">
                        <p>
                            Don't have an account?
                            <a href="register.php" class="fw-bold text-primary">
                                Register Business
                            </a>
                        </p>

                        <p>
                            © <script>document.write(new Date().getFullYear())</script>
                            Universal ERP
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php include('includes/scripts.php'); ?>

    <!-- Login Page JS -->
    <script src="pages-js/login.js"></script>

</body>
</html>