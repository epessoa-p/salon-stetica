<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistema de Préstamos')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #0a58ca);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }

        .sidebar {
            background: #fff;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            min-height: 100vh;
            padding-top: 20px;
        }

        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #f0f0f0;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #0a58ca);
            color: white;
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #0a58ca);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .alert {
            border: none;
            border-radius: 6px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
        }

        table {
            border-collapse: collapse;
        }

        table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                padding: 15px;
            }
        }

        /* Entradas de texto en mayúsculas (excepto correos, contraseñas, etc.) */
        input[type="text"]:not(.no-uppercase),
        input[type="search"]:not(.no-uppercase),
        textarea:not(.no-uppercase) {
            text-transform: uppercase;
        }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Convierte a MAYÚSCULAS los campos de texto al escribir.
        // Se excluyen: correos, contraseñas, URLs y cualquier campo con la clase .no-uppercase.
        (function () {
            var SELECTOR = 'input[type="text"]:not(.no-uppercase), input[type="search"]:not(.no-uppercase), textarea:not(.no-uppercase)';
            var EXCLUDE_NAME = /mail|pass|url|slug|token|link|http/i;

            document.addEventListener('input', function (e) {
                var el = e.target;
                if (!el.matches || !el.matches(SELECTOR)) return;
                if (EXCLUDE_NAME.test(el.name || '') || EXCLUDE_NAME.test(el.id || '')) return;

                var upper = el.value.toLocaleUpperCase();
                if (upper === el.value) return;

                var start = el.selectionStart;
                var end = el.selectionEnd;
                el.value = upper;
                try { el.setSelectionRange(start, end); } catch (err) {}
            }, true);
        })();
    </script>

    @stack('scripts')
</body>
</html>
