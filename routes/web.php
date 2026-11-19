<?php

use App\Http\Controllers\AdministrativeDocumentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PublicRegistrationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/inscription', [PublicRegistrationController::class, 'create'])->name('register');
    Route::post('/inscription', [PublicRegistrationController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/mot-de-passe/changer', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/mot-de-passe', [PasswordChangeController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard')->middleware('can:view-assets');

        Route::resource('biens', AssetController::class)
            ->parameters(['biens' => 'asset'])
            ->names('assets')
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('can:manage-assets');
        Route::resource('biens', AssetController::class)
            ->parameters(['biens' => 'asset'])
            ->names('assets')
            ->only(['index', 'show'])
            ->middleware('can:view-assets');
        Route::middleware('can:view-assets')->group(function () {
            Route::get('/rapports', [AdministrativeDocumentController::class, 'assetsIndex'])->name('reports.index');
            Route::get('/rapports/patrimoine.pdf', [AdministrativeDocumentController::class, 'assetsPdf'])->name('reports.assets.pdf');
            Route::get('/rapports/patrimoine.xls', [AdministrativeDocumentController::class, 'assetsExcel'])->name('reports.assets.excel');
            Route::get('/biens/{asset}/fiche-administrative', [AdministrativeDocumentController::class, 'assetSheet'])->name('documents.assets.show');
            Route::get('/biens/{asset}/fiche-administrative.pdf', [AdministrativeDocumentController::class, 'assetSheetPdf'])->name('documents.assets.pdf');
        });
        Route::post('/biens/{asset}/documents', [AssetController::class, 'uploadDocument'])
            ->middleware('can:manage-assets')->name('assets.documents.store');
        Route::get('/biens/{asset}/documents/{document}', [AssetController::class, 'downloadDocument'])
            ->middleware('can:view-assets')->name('assets.documents.download');
        Route::delete('/biens/{asset}/documents/{document}', [AssetController::class, 'deleteDocument'])
            ->middleware('can:manage-assets')->name('assets.documents.destroy');

        Route::middleware('can:view-assignments')->group(function () {
            Route::get('/affectations/operations/{assignment}/fiche', [AdministrativeDocumentController::class, 'assignmentSheet'])->name('documents.assignments.show');
            Route::get('/affectations/operations/{assignment}/fiche.pdf', [AdministrativeDocumentController::class, 'assignmentSheetPdf'])->name('documents.assignments.pdf');
            Route::get('/affectations/operations/{assignment}/transfert', [AdministrativeDocumentController::class, 'transferReport'])->name('documents.transfers.show');
            Route::get('/affectations/operations/{assignment}/transfert.pdf', [AdministrativeDocumentController::class, 'transferReportPdf'])->name('documents.transfers.pdf');
            Route::get('/affectations/operations/{assignment}/restitution', [AdministrativeDocumentController::class, 'restitutionReport'])->name('documents.restitutions.show');
            Route::get('/affectations/operations/{assignment}/restitution.pdf', [AdministrativeDocumentController::class, 'restitutionReportPdf'])->name('documents.restitutions.pdf');
            Route::get('/affectations', [AssetAssignmentController::class, 'index'])->name('assignments.index');
            Route::get('/affectations/create', [AssetAssignmentController::class, 'create'])
                ->middleware('can:manage-assignments')
                ->name('assignments.create');
            Route::get('/affectations/agents/{agent}', [AssetAssignmentController::class, 'assignedToAgent'])->name('assignments.agent');
            Route::get('/affectations/services/{service}', [AssetAssignmentController::class, 'assignedToService'])->name('assignments.service');
            Route::get('/affectations/non-affectes', [AssetAssignmentController::class, 'unassigned'])->name('assignments.unassigned');
            Route::get('/affectations/{asset}', [AssetAssignmentController::class, 'show'])->name('assignments.show');
            Route::post('/affectations', [AssetAssignmentController::class, 'store'])
                ->middleware('can:manage-assignments')
                ->name('assignments.store');
            Route::post('/affectations/{asset}/transfert', [AssetAssignmentController::class, 'transfer'])
                ->middleware('can:manage-assignments')
                ->name('assignments.transfer');
            Route::post('/affectations/{asset}/restitution', [AssetAssignmentController::class, 'restitute'])
                ->middleware('can:manage-assignments')
                ->name('assignments.return');
            Route::post('/affectations/{asset}/deplacement', [AssetAssignmentController::class, 'movePhysical'])
                ->middleware('can:manage-assignments')
                ->name('assignments.move');
        });

        Route::middleware('can:manage-users')->group(function () {
            Route::resource('admin/utilisateurs', AdminUserController::class)
                ->parameters(['utilisateurs' => 'user'])
                ->names('users')
                ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
            Route::put('/admin/utilisateurs/{user}/mot-de-passe', [AdminUserController::class, 'resetPassword'])
                ->name('users.password');
        });

        Route::prefix('admin/referentiels')
            ->name('references.')
            ->middleware('can:manage-assets')
            ->group(function () {
                Route::resource('categories', CategoryController::class)
                    ->names('categories')
                    ->except(['destroy']);
                Route::patch('categories/{category}/status', [CategoryController::class, 'toggleStatus'])
                    ->name('categories.status');

                Route::resource('localisations', LocationController::class)
                    ->parameters(['localisations' => 'location'])
                    ->names('locations')
                    ->except(['destroy']);
                Route::patch('localisations/{location}/status', [LocationController::class, 'toggleStatus'])
                    ->name('locations.status');
            });

        Route::middleware('can:view-inventories')->group(function () {
            Route::get('/inventaires/constats/{item}/document', [AdministrativeDocumentController::class, 'inventoryFinding'])->name('documents.inventory.findings.show');
            Route::get('/inventaires/constats/{item}/document.pdf', [AdministrativeDocumentController::class, 'inventoryFindingPdf'])->name('documents.inventory.findings.pdf');
            Route::get('/inventaires/{campaign}/proces-verbal-cloture', [AdministrativeDocumentController::class, 'inventoryClosure'])->name('documents.inventory.closure.show');
            Route::get('/inventaires/{campaign}/proces-verbal-cloture.pdf', [AdministrativeDocumentController::class, 'inventoryClosurePdf'])->name('documents.inventory.closure.pdf');
            Route::get('/inventaires', [InventoryController::class, 'index'])->name('inventories.index');
            Route::get('/inventaires/create', [InventoryController::class, 'create'])->middleware('can:manage-inventories')->name('inventories.create');
            Route::get('/inventaires/{campaign}/edit', [InventoryController::class, 'edit'])->middleware('can:manage-inventories')->name('inventories.edit');
            Route::post('/inventaires/preview', [InventoryController::class, 'preview'])->middleware('can:manage-inventories')->name('inventories.preview');
            Route::post('/inventaires', [InventoryController::class, 'store'])->middleware('can:manage-inventories')->name('inventories.store');
            Route::put('/inventaires/{campaign}', [InventoryController::class, 'update'])->middleware('can:manage-inventories')->name('inventories.update');
            Route::get('/inventaires/preuves/{evidence}', [InventoryController::class, 'downloadEvidence'])->name('inventories.evidence.download');
            Route::get('/inventaires/{campaign}/constats', [InventoryController::class, 'items'])->name('inventories.items');
            Route::post('/inventaires/{campaign}/operations', [InventoryController::class, 'assignTask'])->middleware('can:manage-inventories')->name('inventories.tasks.store');
            Route::post('/inventaires/{campaign}/lancer', [InventoryController::class, 'launch'])->middleware('can:manage-inventories')->name('inventories.launch');
            Route::post('/inventaires/operations/{task}/decouverte', [InventoryController::class, 'discover'])->name('inventories.discover');
            Route::post('/inventaires/operations/{task}/soumettre', [InventoryController::class, 'submitTask'])->name('inventories.tasks.submit');
            Route::put('/inventaires/constats/{item}', [InventoryController::class, 'capture'])->name('inventories.capture');
            Route::post('/inventaires/constats/{item}/preuves', [InventoryController::class, 'uploadEvidence'])->name('inventories.evidence.store');
            Route::post('/inventaires/{campaign}/verification', [InventoryController::class, 'verify'])->middleware('can:verify-inventories')->name('inventories.verify');
            Route::post('/inventaires/{campaign}/validation', [InventoryController::class, 'validateCampaign'])->middleware('can:validate-inventories')->name('inventories.validate');
            Route::post('/inventaires/{campaign}/cloture', [InventoryController::class, 'close'])->middleware('can:close-inventories')->name('inventories.close');
            Route::get('/inventaires/{campaign}/rapport/excel', [InventoryController::class, 'spreadsheet'])->name('inventories.report.excel');
            Route::get('/inventaires/{campaign}/rapport/pdf', [InventoryController::class, 'pdf'])->name('inventories.report.pdf');
            Route::get('/inventaires/{campaign}', [InventoryController::class, 'show'])->name('inventories.show');
        });

        Route::middleware('can:view-maintenance')->prefix('maintenance')->name('maintenance.')->group(function () {
            Route::get('/', [MaintenanceController::class, 'index'])->name('index');
            Route::get('/demandes/nouvelle', [MaintenanceController::class, 'create'])->middleware('can:report-maintenance')->name('requests.create');
            Route::post('/demandes', [MaintenanceController::class, 'store'])->middleware('can:report-maintenance')->name('requests.store');
            Route::get('/demandes/{maintenanceRequest}', [MaintenanceController::class, 'show'])->name('requests.show');
            Route::get('/demandes/{maintenanceRequest}/fiche.pdf', [MaintenanceController::class, 'requestPdf'])->name('requests.pdf');
            Route::get('/demandes/{maintenanceRequest}/ordre-travail.pdf', [MaintenanceController::class, 'workOrderPdf'])->name('requests.work-order.pdf');
            Route::post('/demandes/{maintenanceRequest}/validation', [MaintenanceController::class, 'validateRequest'])->middleware('can:validate-maintenance')->name('requests.validate');
            Route::post('/demandes/{maintenanceRequest}/affectation', [MaintenanceController::class, 'assign'])->middleware('can:manage-maintenance')->name('requests.assign');
            Route::post('/demandes/{maintenanceRequest}/cloture', [MaintenanceController::class, 'close'])->middleware('can:manage-maintenance')->name('requests.close');
            Route::post('/demandes/{maintenanceRequest}/annulation', [MaintenanceController::class, 'cancel'])->middleware('can:manage-maintenance')->name('requests.cancel');
            Route::post('/demandes/{maintenanceRequest}/justificatifs', [MaintenanceController::class, 'uploadDocument'])->name('documents.store');
            Route::get('/justificatifs/{document}', [MaintenanceController::class, 'downloadDocument'])->name('documents.download');
            Route::get('/interventions/{intervention}/fiche.pdf', [MaintenanceController::class, 'interventionPdf'])->name('interventions.pdf');
            Route::get('/interventions/{intervention}/rapport.pdf', [MaintenanceController::class, 'maintenanceReportPdf'])->name('interventions.report.pdf');
            Route::post('/interventions/{intervention}/demarrer', [MaintenanceController::class, 'start'])->name('interventions.start');
            Route::post('/interventions/{intervention}/suspendre', [MaintenanceController::class, 'suspend'])->name('interventions.suspend');
            Route::post('/interventions/{intervention}/terminer', [MaintenanceController::class, 'finish'])->name('interventions.finish');
            Route::get('/programmes', [MaintenanceController::class, 'schedules'])->middleware('can:manage-maintenance-schedules')->name('schedules');
            Route::get('/programmes/nouveau', [MaintenanceController::class, 'createSchedule'])->middleware('can:manage-maintenance-schedules')->name('schedules.create');
            Route::post('/programmes', [MaintenanceController::class, 'storeSchedule'])->middleware('can:manage-maintenance-schedules')->name('schedules.store');
            Route::post('/programmes/{schedule}/echeance', [MaintenanceController::class, 'createDueRequest'])->middleware('can:manage-maintenance-schedules')->name('schedules.due');
            Route::patch('/programmes/{schedule}/statut', [MaintenanceController::class, 'toggleSchedule'])->middleware('can:manage-maintenance-schedules')->name('schedules.toggle');
            Route::get('/biens/{asset}/historique.pdf', [MaintenanceController::class, 'assetHistoryPdf'])->name('assets.history.pdf');
            Route::get('/rapports/couts', [MaintenanceController::class, 'costs'])->middleware('can:view-maintenance-costs')->name('costs');
            Route::get('/rapports/couts.pdf', [MaintenanceController::class, 'costsPdf'])->middleware('can:view-maintenance-costs')->name('costs.pdf');
            Route::get('/rapports/couts.xls', [MaintenanceController::class, 'costsExcel'])->middleware('can:view-maintenance-costs')->name('costs.excel');
        });

        Route::middleware('can:view-stock')->prefix('stock')->name('stock.')->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::get('/articles', [StockController::class, 'articles'])->name('articles.index');
            Route::get('/articles/nouveau', [StockController::class, 'createArticle'])->middleware('can:manage-stock')->name('articles.create');
            Route::post('/articles', [StockController::class, 'storeArticle'])->middleware('can:manage-stock')->name('articles.store');
            Route::get('/articles/{article}', [StockController::class, 'showArticle'])->name('articles.show');
            Route::post('/articles/{article}/entrees', [StockController::class, 'receive'])->middleware('can:manage-stock')->name('articles.receive');
            Route::post('/articles/{article}/sorties', [StockController::class, 'issue'])->middleware('can:manage-stock')->name('articles.issue');
            Route::post('/articles/{article}/transferts', [StockController::class, 'transfer'])->middleware('can:manage-stock')->name('articles.transfer');
            Route::post('/articles/{article}/rapprochement', [StockController::class, 'reconcile'])->middleware('can:manage-stock')->name('articles.reconcile');
            Route::patch('/biens/{asset}/etat-stock', [StockController::class, 'verifyAsset'])->middleware('can:manage-stock')->name('assets.state');
            Route::get('/mouvements/{movement}/bon.pdf', [StockController::class, 'movementPdf'])->name('movements.pdf');
            Route::get('/journal.xls', [StockController::class, 'journalExcel'])->name('journal.excel');
        });

    });
});
