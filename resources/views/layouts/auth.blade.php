<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Iniciar sesión - Glamora')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Paleta tomada del logo de marca Glamora */
        :root {
            --brand: #cf7f92;
            --brand-dark: #a35570;
            --brand-glow: rgba(224, 170, 185, 0.6);
            --brand-soft: rgba(207, 127, 146, 0.28);
        }

        /* Logo completo en la cabecera del login */
        .brand-logo-full {
            display: block;
            width: 100%;
            max-width: 270px;
            height: auto;
            margin: 0 auto 6px;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background:
                radial-gradient(820px 520px at 50% 14%, rgba(255, 255, 255, 0.38), transparent 60%),
                linear-gradient(160deg, #f6cfe0 0%, #dd8fb4 48%, #a85585 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 16px;
            border-top: 4px solid var(--brand);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.35), 0 0 45px var(--brand-soft);
            padding: 24px 30px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .logo {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.7rem;
            margin: 0 auto 10px;
            box-shadow: 0 0 24px var(--brand-glow), inset 0 1px 4px rgba(255, 255, 255, 0.45);
        }

        .auth-header h1 {
            font-size: 1.45rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .auth-header p {
            color: #999;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .form-control {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 0.2rem var(--brand-soft);
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-label {
            color: #555;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            border: none;
            color: white;
            padding: 11px 20px;
            border-radius: 6px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 6px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px var(--brand-soft);
            color: white;
        }

        .alert {
            border: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .input-group-text {
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.88rem;
        }

        .remember-me input {
            margin-right: 8px;
            cursor: pointer;
        }

        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: var(--brand);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .auth-signature {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #f0e2e9;
            text-align: center;
        }

        .auth-signature span {
            display: block;
            font-size: 0.62rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #b98ca6;
            margin-bottom: 5px;
        }

        .auth-signature img {
            width: 96px;
            max-width: 55%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s ease;
        }

        .auth-signature img:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 25px;
            }

            .auth-header h1 {
                font-size: 1.5rem;
            }

            .logo {
                width: 60px;
                height: 60px;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
