<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Glamora')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
        }

        /* Escala base del sistema: 14px en lugar de los 16px del navegador.
           Todo lo que Bootstrap define en rem se reduce proporcionalmente. */
        html {
            font-size: 14px;
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
            font-size: 1.75rem;
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
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        /* ───────────────────────────────────────────────────────────────
           Escala compacta
           Ajusta lo que no se reduce solo al bajar la base a 14px: valores
           en px fijos y espaciados escritos en el HTML de las vistas.
           ─────────────────────────────────────────────────────────────── */

        /* Títulos de página (los usan ~69 vistas con <h1>) */
        .app-main h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.15rem;
        }

        .app-main h1 .bi {
            font-size: 1.25rem;
        }

        .app-main h1 + p {
            font-size: 0.85rem;
        }

        .app-main h2 { font-size: 1.3rem; }
        .app-main h3 { font-size: 1.15rem; }

        /* Tablas: filas más bajas y encabezado discreto.
           Se ajustan las variables de Bootstrap para no romper .table-sm. */
        .table {
            --bs-table-cell-padding-y: 0.4rem;
            --bs-table-cell-padding-x: 0.6rem;
            font-size: 0.875rem;
        }

        .table-sm {
            --bs-table-cell-padding-y: 0.25rem;
            --bs-table-cell-padding-x: 0.4rem;
        }

        .table thead th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        /* Botones */
        .btn {
            font-size: 0.875rem;
            padding: 0.35rem 0.7rem;
            border-radius: 6px;
        }

        .btn-sm {
            font-size: 0.78rem;
            padding: 0.2rem 0.45rem;
        }

        .btn-lg {
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
        }

        /* Formularios */
        .form-control,
        .form-select {
            font-size: 0.875rem;
            padding: 0.35rem 0.6rem;
        }

        .form-control-sm,
        .form-select-sm {
            font-size: 0.8rem;
            padding: 0.2rem 0.45rem;
        }

        .form-label {
            font-size: 0.82rem;
            margin-bottom: 0.25rem;
        }

        /* Tarjetas: varias vistas traen p-4 escrito en el HTML */
        .card {
            margin-bottom: 1rem;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .card-body {
            padding: 1rem;
        }

        .card-body.p-4 {
            padding: 1rem !important;
        }

        .card-header {
            padding: 0.75rem 1rem;
        }

        .alert {
            padding: 0.6rem 0.9rem;
            font-size: 0.875rem;
        }

        .nav-tabs .nav-link {
            font-size: 0.875rem;
            padding: 0.4rem 0.8rem;
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
