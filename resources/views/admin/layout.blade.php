<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tableau de bord') - ARTF Patrimoine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-dark bg-primary d-lg-none px-3">
        <button class="navbar-toggler border-0 px-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Ouvrir la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand ms-3 me-auto fw-semibold" href="{{ route('dashboard') }}">ARTF Patrimoine</a>
        <span class="small text-white-50">Administration</span>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <aside class="offcanvas-lg offcanvas-start col-lg-2 app-sidebar bg-white border-end p-0" tabindex="-1" id="appSidebar" aria-labelledby="sidebarTitle">
                <div class="offcanvas-header d-lg-none border-bottom">
                    <h2 class="h6 mb-0" id="sidebarTitle">Navigation</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Fermer"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-0">
                    <a class="sidebar-brand d-none d-lg-flex text-decoration-none" href="{{ route('dashboard') }}">
                        <span class="brand-mark"><i class="bi bi-buildings"></i></span>
                        <span>ARTF <span class="text-secondary fw-normal">Patrimoine</span></span>
                    </a>
                    <div class="p-3">
                        <p class="sidebar-label mt-0">Espace de travail</p>
                        @include('admin.partials.navigation')
                    </div>
                    <div class="mt-auto p-3 border-top small text-secondary">
                        @auth
                            <div class="mb-2"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}<br><span class="ms-4">{{ auth()->user()->role->label() }}</span></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Déconnexion</button>
                            </form>
                        @else
                            <a class="btn btn-sm btn-outline-primary w-100" href="{{ route('login') }}">Connexion</a>
                        @endauth
                    </div>
                </div>
            </aside>

            <main class="col-lg-10 px-3 px-md-4">
                <header class="topbar d-none d-lg-flex align-items-center justify-content-between border-bottom">
                    <span class="text-secondary small">Plateforme de gestion du patrimoine</span>
                    @auth
                        <span class="badge rounded-pill text-bg-light border fw-normal px-3 py-2">
                            <i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}
                        </span>
                    @else
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('login') }}">Connexion</a>
                    @endauth
                </header>
                <div class="py-4 py-lg-5">
                    @yield('content')
                </div>
                <footer class="border-top py-3 small text-secondary">
                    ARTF — Gestion du patrimoine
                </footer>
            </main>
        </div>
    </div>
</body>
</html>
