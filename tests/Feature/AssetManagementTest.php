<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AssetAssignmentService;
use App\Services\AssetLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_routes_require_authentication(): void
    {
        $this->get(route('assets.index'))->assertRedirect(route('login'));
        $this->post(route('assets.store'))->assertRedirect(route('login'));
    }

    public function test_user_can_sign_in_and_sign_out(): void
    {
        $password = Str::random(40);
        $user = User::factory()->create(['password' => $password]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_can_filter_assets_by_code_and_category(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $matchingAsset = Asset::factory()->create([
            'asset_code' => 'FILTER-001',
            'category_id' => $category->getKey(),
            'location_id' => $location->getKey(),
            'serial_number' => 'SERIAL-FILTER-001',
            'designation' => 'Ordinateur portable de test',
            'condition' => 'good',
            'status' => 'in_service',
        ]);
        Asset::factory()->create([
            'asset_code' => 'FILTER-002',
            'category_id' => $category->getKey(),
            'condition' => 'poor',
        ]);

        $filters = [
            'q' => 'FILTER',
            'category_id' => $category->getKey(),
            'location_id' => $location->getKey(),
            'condition' => 'good',
            'status' => 'in_service',
        ];
        ksort($filters);

        $this->get(route('assets.index', $filters))
            ->assertOk()
            ->assertSee($matchingAsset->asset_code)
            ->assertDontSee('FILTER-002')
            ->assertSee('Filtrer')
            ->assertSee('Réinitialiser')
            ->assertSee('Actualiser')
            ->assertSee('href="'.e(route('assets.index')).'"', false)
            ->assertSee('href="'.e(route('assets.index', $filters)).'"', false);

        $this->get(route('assets.index', ['q' => 'SERIAL-FILTER']))
            ->assertOk()
            ->assertSee($matchingAsset->asset_code)
            ->assertDontSee('FILTER-002');

        $this->get(route('assets.index', ['q' => 'portable de test']))
            ->assertOk()
            ->assertSee($matchingAsset->asset_code)
            ->assertDontSee('FILTER-002');
    }

    public function test_asset_listing_is_paginated(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 16) as $number) {
            Asset::factory()->create([
                'asset_code' => sprintf('PAGE-%02d', $number),
            ]);
        }

        $this->get(route('assets.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('PAGE-16')
            ->assertDontSee('PAGE-01');
    }

    public function test_creating_asset_with_location_records_initial_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($user);
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee('href="'.e(route('assets.create')).'"', false);

        $this->post(route('assets.store'), [
            'asset_code' => 'NEW-ASSET-001',
            'designation' => 'Bien de démonstration',
            'category_id' => $category->getKey(),
            'location_id' => $location->getKey(),
            'acquisition_value' => '12500.50',
            'condition' => 'good',
            'status' => 'in_service',
            'characteristics' => '{"type":"exemple"}',
        ])
            ->assertRedirect();

        $asset = Asset::query()->where('asset_code', 'NEW-ASSET-001')->firstOrFail();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->getKey(),
            'location_id' => $location->getKey(),
            'created_by' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->getKey(),
            'to_location_id' => $location->getKey(),
            'recorded_by' => $user->getKey(),
        ]);
        $this->get(route('assets.show', $asset))
            ->assertSee('href="'.e(route('assets.edit', $asset)).'"', false)
            ->assertSee('action="'.e(route('assets.destroy', $asset)).'"', false)
            ->assertSee('href="'.e(route('assignments.show', $asset)).'"', false);

        $this->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Bien de démonstration')
            ->assertSee('Historique de localisation')
            ->assertSee($location->name);
    }

    public function test_updating_asset_location_preserves_movement_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($user);
        $asset = Asset::factory()->create();
        $oldLocation = $asset->location;
        $newLocation = Location::factory()->create();

        $this->put(route('assets.update', $asset), [
            'asset_code' => $asset->asset_code,
            'designation' => 'Désignation modifiée',
            'category_id' => $asset->category_id,
            'location_id' => $newLocation->getKey(),
            'status' => 'in_service',
        ])->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->getKey(),
            'from_location_id' => $oldLocation->getKey(),
            'to_location_id' => $newLocation->getKey(),
            'recorded_by' => $user->getKey(),
        ]);
        $this->assertSame($newLocation->getKey(), $asset->fresh()->location_id);
        $this->assertSame($user->getKey(), $asset->fresh()->updated_by);
    }

    public function test_duplicate_codes_and_non_object_characteristics_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));
        $existing = Asset::factory()->create(['asset_code' => 'DUPLICATE-001']);
        $category = Category::factory()->create();

        $this->get(route('assets.create'))
            ->assertOk()
            ->assertSee('Créer le bien');
        $this->get(route('assets.edit', $existing))
            ->assertOk()
            ->assertSee('Enregistrer les modifications');

        $this->from(route('assets.create'))
            ->post(route('assets.store'), [
                'asset_code' => $existing->asset_code,
                'designation' => 'Autre bien',
                'category_id' => $category->getKey(),
                'status' => 'in_service',
                'characteristics' => '[]',
            ])
            ->assertRedirect(route('assets.create'))
            ->assertSessionHasErrors(['asset_code', 'characteristics']);

        $this->assertDatabaseCount('assets', 1);
    }

    public function test_viewers_can_consult_but_cannot_create_edit_or_archive_assets(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee($asset->asset_code)
            ->assertSee('Nouveau bien')
            ->assertSee('Création réservée aux gestionnaires habilités.')
            ->assertDontSee('href="'.e(route('assets.create')).'"', false);
        $this->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Historique des affectations')
            ->assertDontSee('Modifier')
            ->assertDontSee('Archiver');
        $this->get(route('assets.create'))->assertForbidden();
        $this->get(route('assets.edit', $asset))->assertForbidden();
        $this->post(route('assets.store'))->assertForbidden();
        $this->put(route('assets.update', $asset))->assertForbidden();
        $this->delete(route('assets.destroy', $asset))->assertForbidden();

        $this->assertNotSoftDeleted($asset);
    }

    public function test_asset_creation_form_requires_a_real_active_category(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AssetManager]));

        $this->get(route('assets.create'))
            ->assertOk()
            ->assertSee('Aucune catégorie active')
            ->assertSee('type="submit" disabled', false);

        $this->post(route('assets.store'), [
            'asset_code' => 'NO-CATEGORY-001',
            'designation' => 'Bien sans catégorie',
            'category_id' => '',
            'status' => 'in_service',
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_archiving_preserves_location_and_assignment_histories(): void
    {
        $actor = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $originalLocation = $asset->location;
        $newLocation = Location::factory()->create();
        $agent = Agent::factory()->create();

        app(AssetLocationService::class)->move($asset, $newLocation, $actor, 'Déplacement avant archivage');

        $assignmentService = app(AssetAssignmentService::class);
        $assignmentService->assign(
            $asset,
            $agent->getKey(),
            null,
            $actor,
            now()->subMinute()->toDateTimeString(),
            'Affectation historique',
            null,
        );
        $assignmentService->restitute(
            $asset,
            $actor,
            now()->toDateTimeString(),
            'Restitution avant archivage',
        );

        $this->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Modifier')
            ->assertSee('Archiver')
            ->assertSee('action="'.e(route('assets.destroy', $asset)).'"', false);

        $this->from(route('assets.show', $asset))
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.index'))
            ->assertSessionHas('status', 'Le bien a été archivé. Son historique est conservé.');

        $this->assertSoftDeleted($asset);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->getKey(),
            'updated_by' => $actor->getKey(),
        ]);
        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->getKey(),
            'from_location_id' => $originalLocation->getKey(),
            'to_location_id' => $newLocation->getKey(),
            'reason' => 'Déplacement avant archivage',
        ]);
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $asset->getKey(),
            'end_reason' => 'Restitution avant archivage',
        ]);
        $this->get(route('assets.show', $asset))->assertNotFound();
    }

    public function test_a_bien_with_an_active_assignment_cannot_be_archived(): void
    {
        $actor = User::factory()->create(['role' => UserRole::AssetManager]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $agent = Agent::factory()->create();

        app(AssetAssignmentService::class)->assign(
            $asset,
            $agent->getKey(),
            null,
            $actor,
            now()->toDateTimeString(),
            'Affectation active',
            null,
        );

        $this->from(route('assets.show', $asset))
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHasErrors('asset');

        $this->assertNotSoftDeleted($asset);
    }

    public function test_asset_details_and_multicriteria_search_include_new_patrimonial_fields(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $service = Service::factory()->create(['name' => 'Service Patrimoine']);
        $asset = Asset::factory()->create([
            'asset_code' => 'PATRIMOINE-DETAIL-01',
            'manufacturer' => 'Constructeur Exemple',
            'brand' => 'Marque Exemple',
            'model' => 'Modele X',
            'manufacturer_reference' => 'REF-42',
            'commissioned_on' => '2025-02-01',
            'acquisition_mode' => 'Marché public',
            'supplier' => 'Fournisseur Exemple',
            'invoice_reference' => 'FACT-2025-10',
            'usage_status' => 'under_repair',
        ]);
        app(AssetAssignmentService::class)->assign($asset, null, $service->getKey(), User::factory()->create(), now()->toDateTimeString(), 'Affectation', null);

        $this->actingAs($viewer)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Constructeur Exemple')
            ->assertSee('FACT-2025-10')
            ->assertSee('Service Patrimoine')
            ->assertSee('En réparation');

        $this->get(route('assets.index', ['q' => 'Modele X', 'service_id' => $service->getKey(), 'usage_status' => 'under_repair']))
            ->assertOk()->assertSee('PATRIMOINE-DETAIL-01');
    }

    public function test_asset_document_upload_download_and_removal_are_permission_checked_and_private(): void
    {
        Storage::fake('private');
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('assets.documents.store', $asset), [
            'type' => 'invoice',
            'documents' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $document = $asset->documents()->firstOrFail();
        $this->assertSame('facture.pdf', $document->original_name);
        Storage::disk('private')->assertExists($document->path);
        $this->get(route('assets.documents.download', [$asset, $document]))->assertOk();

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer)->get(route('assets.documents.download', [$asset, $document]))->assertOk();
        $this->delete(route('assets.documents.destroy', [$asset, $document]))->assertForbidden();
        $this->assertDatabaseHas('asset_documents', ['id' => $document->getKey(), 'deleted_at' => null]);

        $this->actingAs($manager)->delete(route('assets.documents.destroy', [$asset, $document]))->assertRedirect();
        $this->assertSoftDeleted($document);
        Storage::disk('private')->assertExists($document->path);
    }

    public function test_asset_document_rejects_disallowed_file_types(): void
    {
        Storage::fake('private');
        $manager = User::factory()->create(['role' => UserRole::AssetManager]);
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('assets.documents.store', $asset), [
            'type' => 'other',
            'documents' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('documents.0');

        $this->assertDatabaseCount('asset_documents', 0);
    }
}
