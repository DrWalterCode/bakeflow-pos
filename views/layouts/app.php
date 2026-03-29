<?php
use App\Core\Auth;
use App\Core\Session;
use App\Core\Env;

$appName      = Env::get('APP_NAME', 'BakeFlow POS');
$flashMessage = Session::getFlash('message');
$flashType    = Session::getFlash('message_type', 'success');
$currentPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$authUser     = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin', ENT_QUOTES, 'UTF-8') ?> &mdash; <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>

    <script src="/assets/js/color-modes.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/vendors/core/core.css">
    <link rel="stylesheet" href="/assets/vendors/datatables.net-bs5/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="/assets/css/demo1/style.css">

    <style>
        /* ── Zimbocrumb Brand Colour System ── */
        :root {
            --bs-primary:        #C4748E;
            --bs-primary-rgb:    196, 116, 142;
            --zc-peach:          #FCE9DD;
            --zc-pink:           #F4C5D8;
            --zc-teal:           #B2DAD8;
            --zc-text:           #4A2A35;
            --zc-text-light:     #7A5968;
            --zc-accent:         #9E5A72;
            --zc-border:         rgba(158, 90, 114, 0.12);
        }

        /* ── Sidebar: Zimbocrumb gradient ── */
        .sidebar {
            background: linear-gradient(180deg, #FCE9DD 0%, #F4C5D8 60%, #B2DAD8 100%) !important;
            border-right: 1px solid rgba(74,42,53,0.08) !important;
        }
        .sidebar .sidebar-header {
            background: transparent !important;
            border-bottom: 1px solid rgba(74,42,53,0.1) !important;
        }
        .sidebar .sidebar-brand {
            color: #4A2A35 !important;
            font-weight: 800;
            font-size: 1.25rem;
        }
        .sidebar .sidebar-brand .text-primary,
        .sidebar .sidebar-brand span {
            color: #5A9E9A !important;
        }
        .sidebar .sidebar-body {
            background: transparent !important;
        }

        /* Remove ALL borders/lines from sidebar nav */
        .sidebar .sidebar-body .nav,
        .sidebar .sidebar-body .nav .nav-item,
        .sidebar .nav,
        .sidebar .nav-item {
            border: none !important;
        }
        .sidebar .sidebar-body .nav .nav-item::after,
        .sidebar .sidebar-body .nav .nav-item::before,
        .sidebar .nav-item::after,
        .sidebar .nav-item::before {
            display: none !important;
            content: none !important;
        }

        /* Nav category labels */
        .sidebar .sidebar-body .nav .nav-item.nav-category {
            color: #7A4A5C !important;
            font-size: 0.68rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            padding: 20px 15px 8px !important;
        }

        /* Nav links — default: NO borders at all */
        .sidebar .sidebar-body .nav .nav-item .nav-link {
            color: #5A3040 !important;
            background: none !important;
            border: none !important;
            border-radius: 0 !important;
            padding: 10px 15px !important;
            margin: 0 !important;
            transition: color 0.15s !important;
        }
        /* Kill NobleUI's ::before accent bar on ALL items */
        .sidebar .sidebar-body .nav .nav-item .nav-link::before,
        .sidebar .sidebar-body .nav .nav-item .nav-link::after,
        .sidebar .sidebar-body .nav .nav-item.active .nav-link::before,
        .sidebar .sidebar-body .nav .nav-item.active .nav-link::after {
            display: none !important;
            content: none !important;
            width: 0 !important;
            height: 0 !important;
            background: none !important;
            border: none !important;
        }
        .sidebar .sidebar-body .nav .nav-item .nav-link .link-icon {
            color: #7A5060 !important;
            background: none !important;
            border: none !important;
            transition: color 0.15s !important;
        }

        /* Hover — color shift only */
        .sidebar .sidebar-body .nav .nav-item .nav-link:hover {
            color: #3D1F14 !important;
            background: none !important;
            border: none !important;
        }
        .sidebar .sidebar-body .nav .nav-item .nav-link:hover .link-icon {
            color: #C4748E !important;
        }

        /* Active — left accent bar only */
        .sidebar .sidebar-body .nav .nav-item .nav-link.active {
            color: #3D1F14 !important;
            background: none !important;
            border: none !important;
            border-left: 3px solid #C4748E !important;
            font-weight: 600 !important;
        }
        .sidebar .sidebar-body .nav .nav-item .nav-link.active .link-icon {
            color: #C4748E !important;
        }

        /* ── Collapsible nav sections ── */
        .sidebar .nav-category-toggle {
            cursor: pointer;
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }
        .sidebar .nav-category-toggle .collapse-chevron {
            width: 14px;
            height: 14px;
            color: #7A4A5C;
            transition: transform 0.2s ease;
        }
        .sidebar .nav-category-toggle[aria-expanded="false"] .collapse-chevron {
            transform: rotate(-90deg);
        }
        .sidebar .nav-category-toggle[aria-expanded="true"] .collapse-chevron {
            transform: rotate(0deg);
        }
        /* Collapsed section items */
        .sidebar .nav-section-item {
            overflow: hidden;
            max-height: 50px;
            transition: max-height 0.2s ease, opacity 0.2s ease;
            opacity: 1;
        }
        .sidebar .nav-section-item.section-collapsed {
            max-height: 0 !important;
            opacity: 0;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        /* Sidebar toggler */
        .sidebar .sidebar-toggler span {
            background: #7A5060 !important;
        }

        /* ── Top Navbar ── */
        .page-wrapper .page-header .navbar {
            background: #fff !important;
            border-bottom: 2px solid var(--zc-peach) !important;
        }

        /* ── Page background ── */
        .page-wrapper {
            background: #FAF5F2 !important;
        }
        .page-content { background: transparent; }

        /* ── Primary buttons ── */
        .btn-primary {
            background-color: #C4748E !important;
            border-color: #C4748E !important;
        }
        .btn-primary:hover {
            background-color: #A85A74 !important;
            border-color: #A85A74 !important;
        }
        .btn-outline-primary {
            color: #C4748E !important;
            border-color: #C4748E !important;
        }
        .btn-outline-primary:hover {
            background-color: #C4748E !important;
            color: #fff !important;
        }

        /* ── Links & badges ── */
        a.text-primary, .text-primary { color: #C4748E !important; }
        .badge.bg-primary { background-color: #C4748E !important; }

        /* ── Forms focus ── */
        .form-control:focus, .form-select:focus {
            border-color: #C4748E;
            box-shadow: 0 0 0 0.2rem rgba(196,116,142,0.15);
        }

        /* ── Cards ── */
        .card {
            border-color: rgba(196,116,142,0.1);
            box-shadow: 0 1px 3px rgba(74,42,53,0.04);
        }
        .card:hover {
            box-shadow: 0 2px 8px rgba(196,116,142,0.08);
        }

        /* ── Dashboard stat cards accent ── */
        .card .card-body .text-primary,
        .card .card-body a { color: #C4748E !important; }

        /* ── DataTables ── */

        .bf-dt-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .bf-dt-top .dt-length {
            font-size: 0.85rem;
            color: #666;
        }
        .bf-dt-top .dt-length select {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.85rem;
            min-width: 70px;
        }
        .bf-dt-top .dt-search input {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.85rem;
            min-width: 200px;
        }
        .bf-dt-top .dt-search input:focus,
        .bf-dt-top .dt-length select:focus {
            outline: none;
            border-color: #C4748E;
        }

        .bf-table thead tr:first-child th {
            background: #f8f9fa !important;
            color: #333 !important;
            font-weight: 600 !important;
            font-size: 0.8rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.3px !important;
            padding: 10px 16px !important;
            border-bottom: 2px solid #dee2e6 !important;
            white-space: nowrap;
        }

        .bf-search-row th {
            background: #fff !important;
            padding: 6px 16px 10px !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        .bf-col-search {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #333;
        }
        .bf-col-search::placeholder { color: #aaa; }
        .bf-col-search:focus {
            outline: none;
            border-color: #C4748E;
        }

        .bf-table tbody td {
            padding: 10px 16px !important;
            font-size: 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0 !important;
        }
        .bf-table tbody tr:hover td {
            background: #f8f9fa !important;
        }

        .bf-dt-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .bf-dt-bottom .dt-info {
            font-size: 0.8rem;
            color: #666;
        }
        .bf-dt-bottom .dt-paging .pagination { margin: 0; gap: 2px; }
        .bf-dt-bottom .dt-paging .page-item .page-link {
            border: 1px solid #ddd;
            color: #333;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 4px !important;
        }
        .bf-dt-bottom .dt-paging .page-item .page-link:hover {
            background: #f8f9fa;
            color: #C4748E;
        }
        .bf-dt-bottom .dt-paging .page-item.active .page-link {
            background: #C4748E;
            border-color: #C4748E;
            color: #fff;
        }
        .bf-dt-bottom .dt-paging .page-item.disabled .page-link {
            color: #ccc;
        }
    </style>

    <?= $pageStyles ?? '' ?>
</head>
<body>
    <div class="main-wrapper">

        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <a href="/admin" class="sidebar-brand">
                    Bake<span class="text-primary">Flow</span>
                </a>
                <div class="sidebar-toggler">
                    <span></span><span></span><span></span>
                </div>
            </div>
            <div class="sidebar-body">
                <?php
                // Determine which collapsible sections have an active link
                $secOperations = str_starts_with($currentPath, '/admin/cake-orders')
                              || str_starts_with($currentPath, '/admin/production')
                              || str_starts_with($currentPath, '/admin/expenses');
                $secCatalogue  = str_starts_with($currentPath, '/admin/products')
                              || str_starts_with($currentPath, '/admin/categories');
                $secReports    = str_starts_with($currentPath, '/admin/reports');
                $secSystem     = str_starts_with($currentPath, '/admin/users')
                              || str_starts_with($currentPath, '/admin/settings');
                ?>
                <ul class="nav" id="sidebarNav">

                    <!-- ── MAIN (always visible) ── -->
                    <li class="nav-item nav-category">Main</li>
                    <li class="nav-item">
                        <a href="/admin" class="nav-link <?= in_array($currentPath, ['/admin', '/admin/']) ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="layout-dashboard"></i>
                            <span class="link-title">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/pos" class="nav-link" target="_blank">
                            <i class="link-icon" data-lucide="shopping-cart"></i>
                            <span class="link-title">Go to POS</span>
                        </a>
                    </li>

                    <!-- ── OPERATIONS (expanded by default) ── -->
                    <li class="nav-item nav-category nav-category-toggle" data-section="operations" aria-expanded="true">
                        <span>Operations</span>
                        <i class="link-icon collapse-chevron" data-lucide="chevron-down"></i>
                    </li>
                    <li class="nav-item nav-section-item" data-section="operations">
                        <a href="/admin/cake-orders" class="nav-link <?= str_starts_with($currentPath, '/admin/cake-orders') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="cake"></i>
                            <span class="link-title">Cake Orders</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item" data-section="operations">
                        <a href="/admin/production" class="nav-link <?= str_starts_with($currentPath, '/admin/production') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="factory"></i>
                            <span class="link-title">Production & Stock</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item" data-section="operations">
                        <a href="/admin/expenses" class="nav-link <?= str_starts_with($currentPath, '/admin/expenses') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="receipt"></i>
                            <span class="link-title">Expenses</span>
                        </a>
                    </li>

                    <!-- ── CATALOGUE (collapsed unless active) ── -->
                    <li class="nav-item nav-category nav-category-toggle" data-section="catalogue" aria-expanded="<?= $secCatalogue ? 'true' : 'false' ?>">
                        <span>Catalogue</span>
                        <i class="link-icon collapse-chevron" data-lucide="chevron-down"></i>
                    </li>
                    <li class="nav-item nav-section-item <?= $secCatalogue ? '' : 'section-collapsed' ?>" data-section="catalogue">
                        <a href="/admin/products" class="nav-link <?= str_starts_with($currentPath, '/admin/products') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="package"></i>
                            <span class="link-title">Products</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item <?= $secCatalogue ? '' : 'section-collapsed' ?>" data-section="catalogue">
                        <a href="/admin/categories" class="nav-link <?= str_starts_with($currentPath, '/admin/categories') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="tag"></i>
                            <span class="link-title">Categories</span>
                        </a>
                    </li>

                    <!-- ── REPORTS (collapsed unless active) ── -->
                    <li class="nav-item nav-category nav-category-toggle" data-section="reports" aria-expanded="<?= $secReports ? 'true' : 'false' ?>">
                        <span>Reports</span>
                        <i class="link-icon collapse-chevron" data-lucide="chevron-down"></i>
                    </li>
                    <li class="nav-item nav-section-item <?= $secReports ? '' : 'section-collapsed' ?>" data-section="reports">
                        <a href="/admin/reports" class="nav-link <?= $currentPath === '/admin/reports' ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="bar-chart-2"></i>
                            <span class="link-title">Sales Summary</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item <?= $secReports ? '' : 'section-collapsed' ?>" data-section="reports">
                        <a href="/admin/reports/daily" class="nav-link <?= $currentPath === '/admin/reports/daily' ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="calendar"></i>
                            <span class="link-title">Daily Report</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item <?= $secReports ? '' : 'section-collapsed' ?>" data-section="reports">
                        <a href="/admin/reports/products" class="nav-link <?= $currentPath === '/admin/reports/products' ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="trending-up"></i>
                            <span class="link-title">Product Sales</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item <?= $secReports ? '' : 'section-collapsed' ?>" data-section="reports">
                        <a href="/admin/reports/cashiers" class="nav-link <?= $currentPath === '/admin/reports/cashiers' ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="users"></i>
                            <span class="link-title">Cashier Performance</span>
                        </a>
                    </li>

                    <!-- ── SYSTEM (collapsed unless active) ── -->
                    <li class="nav-item nav-category nav-category-toggle" data-section="system" aria-expanded="<?= $secSystem ? 'true' : 'false' ?>">
                        <span>System</span>
                        <i class="link-icon collapse-chevron" data-lucide="chevron-down"></i>
                    </li>
                    <li class="nav-item nav-section-item <?= $secSystem ? '' : 'section-collapsed' ?>" data-section="system">
                        <a href="/admin/users" class="nav-link <?= str_starts_with($currentPath, '/admin/users') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="user"></i>
                            <span class="link-title">Users</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item <?= $secSystem ? '' : 'section-collapsed' ?>" data-section="system">
                        <a href="/admin/settings" class="nav-link <?= str_starts_with($currentPath, '/admin/settings') ? 'active' : '' ?>">
                            <i class="link-icon" data-lucide="settings"></i>
                            <span class="link-title">Settings</span>
                        </a>
                    </li>
                    <li class="nav-item nav-section-item <?= $secSystem ? '' : 'section-collapsed' ?>" data-section="system">
                        <a href="/logout" class="nav-link">
                            <i class="link-icon" data-lucide="log-out"></i>
                            <span class="link-title">Logout</span>
                        </a>
                    </li>

                </ul>
            </div>
        </nav>

        <!-- Page content -->
        <div class="page-wrapper">

            <!-- Top bar -->
            <div class="page-header">
                <nav class="navbar">
                    <a href="#" class="sidebar-toggler">
                        <i data-lucide="menu"></i>
                    </a>
                    <div class="navbar-content">
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i data-lucide="user" class="icon-md"></i>
                                    <span class="ms-1"><?= htmlspecialchars($authUser['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/pos">POS Screen</a></li>
                                    <li><a class="dropdown-item" href="/admin/settings">Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="/logout">Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>

            <!-- Page body -->
            <div class="page-content">
                <div class="container-xxl">
                    <!-- Flash messages -->
                    <?php if ($flashMessage): ?>
                    <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show mb-3" role="alert">
                        <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    <?= $content ?>
                </div>
            </div>

            <footer class="footer d-flex flex-column flex-md-row align-items-center justify-content-between">
                <p class="text-muted mb-1 mb-md-0">BakeFlow POS &copy; <?= date('Y') ?></p>
            </footer>
        </div>

    </div>

    <!-- Confirm / Alert Modal -->
    <div class="modal fade" id="bfConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title" id="bfConfirmTitle">Confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2" id="bfConfirmBody"></div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="bfConfirmOk">OK</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="bfAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title" id="bfAlertTitle">Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2" id="bfAlertBody"></div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/vendors/jquery/jquery.min.js"></script>
    <script src="/assets/vendors/core/core.js"></script>
    <script src="/assets/vendors/datatables.net/dataTables.js"></script>
    <script src="/assets/vendors/datatables.net-bs5/dataTables.bootstrap5.js"></script>
    <script src="/assets/js/app.js"></script>

    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();

        /* ── Fix: strip NobleUI's incorrect .active classes from nav-items ── */
        /* NobleUI app.js uses substring URL matching ("admin" matches ALL hrefs), */
        /* so it marks every nav-item as active. We handle active via PHP instead. */
        document.querySelectorAll('.sidebar .nav-item.active').forEach(function(item) {
            item.classList.remove('active');
        });

        /* ── Sidebar collapsible sections (toggle via data-section) ── */
        document.querySelectorAll('.nav-category-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                var section = this.getAttribute('data-section');
                var items = document.querySelectorAll('.nav-section-item[data-section="' + section + '"]');
                var isExpanded = this.getAttribute('aria-expanded') === 'true';

                if (isExpanded) {
                    items.forEach(function(item) { item.classList.add('section-collapsed'); });
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    items.forEach(function(item) { item.classList.remove('section-collapsed'); });
                    this.setAttribute('aria-expanded', 'true');
                }
            });
        });

        /* ── BakeFlow DataTables: auto-init any table with .bf-table ── */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('table.bf-table').forEach(function(table) {
                var thead = table.querySelector('thead');
                var headerRow = thead.querySelector('tr');
                var headers = headerRow.querySelectorAll('th');

                // ── Build a second header row with per-column search inputs ──
                var searchRow = document.createElement('tr');
                searchRow.className = 'bf-search-row';
                headers.forEach(function(th, i) {
                    var cell = document.createElement('th');
                    var title = th.textContent.trim();
                    if (title && !th.classList.contains('no-search')) {
                        cell.innerHTML = '<input type="text" class="bf-col-search" data-col="' + i + '" placeholder="' + title + '…">';
                    }
                    searchRow.appendChild(cell);
                });
                thead.appendChild(searchRow);

                // ── Determine non-sortable / non-searchable columns ──
                var noSortCols = [];
                headers.forEach(function(th, i) {
                    if (th.classList.contains('no-sort')) noSortCols.push(i);
                });
                var colDefs = noSortCols.map(function(i) {
                    return { orderable: false, searchable: false, targets: i };
                });

                // ── Initialise DataTable ──
                var dt = new DataTable(table, {
                    orderCellsTop: true,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    order: [[0, 'asc']],
                    columnDefs: colDefs,
                    language: {
                        search: '',
                        searchPlaceholder: 'Search all columns…',
                        lengthMenu: '_MENU_ &nbsp; entries per page',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        infoEmpty: 'No entries found',
                        infoFiltered: '(filtered from _MAX_ total)',
                        emptyTable: 'No data available',
                        zeroRecords: 'No matching records found',
                        paginate: { first: '«', previous: '‹', next: '›', last: '»' }
                    },
                    dom: '<"bf-dt-top"lf>rt<"bf-dt-bottom"ip>',
                    drawCallback: function() {
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }
                });

                // ── Wire up per-column search inputs ──
                table.querySelectorAll('.bf-col-search').forEach(function(input) {
                    var colIdx = parseInt(input.getAttribute('data-col'), 10);
                    input.addEventListener('input', function() {
                        var col = dt.column(colIdx);
                        if (col.search() !== this.value) {
                            col.search(this.value).draw();
                        }
                    });
                    // Stop click on search input from triggering sort
                    input.addEventListener('click', function(e) { e.stopPropagation(); });
                });
            });
        });
    </script>

    <script>
    /* ── BakeFlow themed confirm / alert helpers ── */
    function bfConfirm(message, callback) {
        var body = document.getElementById('bfConfirmBody');
        body.textContent = message;
        var modal = new bootstrap.Modal(document.getElementById('bfConfirmModal'));
        var okBtn = document.getElementById('bfConfirmOk');
        var handler = function() {
            okBtn.removeEventListener('click', handler);
            modal.hide();
            callback();
        };
        okBtn.addEventListener('click', handler);
        document.getElementById('bfConfirmModal').addEventListener('hidden.bs.modal', function cleanup() {
            okBtn.removeEventListener('click', handler);
            this.removeEventListener('hidden.bs.modal', cleanup);
        });
        modal.show();
    }
    function bfAlert(message) {
        document.getElementById('bfAlertBody').textContent = message;
        new bootstrap.Modal(document.getElementById('bfAlertModal')).show();
    }
    </script>

    <?= $pageScripts ?? '' ?>
</body>
</html>
