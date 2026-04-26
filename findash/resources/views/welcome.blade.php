<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login &mdash; Stisla</title>
    <!-- General CSS Files -->
    <link rel="stylesheet" href="{{ asset('backend/assets/modules/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/modules/fontawesome/css/all.min.css') }}">

    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('backend/assets/modules/bootstrap-social/bootstrap-social.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('backend/assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/components.css') }}">
    <!-- Start GA -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-94034622-3"></script>

    <!-- /END GA -->
</head>

<body>
    <div id="app">
        <section class="section">
            <div class="container mt-5">
                <div class="row">
                    <div
                        class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4">
                        <div class="login-brand">
                            <img src="{{ asset('backend/assets/img/findash.png') }}" alt="logo" width="100"
                                class="shadow-light rounded-circle">
                        </div>

                        <div class="card card-primary">
                            <div class="card-body">
                                <div class="text-center mt-4 mb-3">
                                    <div class="text-job text-muted">Login With Social</div>
                                </div>
                                <div class="row sm-gutters">
                                    <div class="col-12">
                                        <button type="button" id="telegram-login-btn"
                                            class="d-flex justify-content-center align-items-center btn btn-block btn-social px-3 btn-twitter">
                                            Log in with Telegram
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- General JS Scripts -->
    <script src="{{ asset('backend/assets/modules/jquery.min.js') }}"></script>
    <script src="{{ asset('backend/assets/modules/popper.js') }}"></script>
    <script src="{{ asset('backend/assets/modules/tooltip.js') }}"></script>
    <script src="{{ asset('backend/assets/modules/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('backend/assets/modules/nicescroll/jquery.nicescroll.min.js') }}"></script>
    <script src="{{ asset('backend/assets/modules/moment.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/stisla.js') }}"></script>

    <!-- JS Libraies -->

    <!-- Page Specific JS File -->

    <!-- Template JS File -->
    <script src={{ asset('backend/assets/js/scripts.js') }}"></script>
    <script src="{{ asset('backend/assets/js/custom.js') }}"></script>

    <!-- Your main content here -->
    <div
        class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
        <!-- ... rest of your content ... -->
    </div>

    <!-- Telegram Login Script - POPUP VERSION -->
    <script src="https://telegram.org/js/telegram-login.js?22"></script>
    <script>
        console.log("Telegram script loaded");

        // Initialize Telegram Login
        try {
            Telegram.Login.init({
                client_id: {{ config('services.telegram.client_id') }},
                request_access: ['phone']

                // NO redirect_uri for popup mode!
            }, function(data) {
                console.log("Telegram callback received:", data);

                if (data.error) {
                    console.error("Telegram error:", data.error);
                    alert("Login failed: " + data.error);
                    return;
                }

                if (data && data.id_token) {
                    console.log("ID token received, sending to backend...");

                    // Send to backend
                    fetch('/auth/telegram/popup-callback', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                id_token: data.id_token
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            console.log("Backend response:", result);
                            if (result.status === 'success') {
                                window.location.href = result.redirect;
                            } else {
                                alert("Login failed: " + (result.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error("Backend error:", error);
                            alert("Connection error. Please try again.");
                        });
                }
            });

            console.log("Telegram init successful");
        } catch (e) {
            console.error("Telegram init failed:", e);
        }

        // Button click handler
        document.getElementById('telegram-login-btn').addEventListener('click', function() {
            console.log("Login button clicked");
            try {
                Telegram.Login.open();
            } catch (e) {
                console.error("Failed to open Telegram login:", e);
                alert("Failed to open login popup: " + e.message);
            }
        });
    </script>
</body>

</html>
