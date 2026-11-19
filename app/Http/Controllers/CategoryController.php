<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'active' => ['nullable', 'in:active,inactive'],
        ]);

        $categories = Category::query()
            ->with('parent')
            ->withCount([
                'children',
                'assets' => fn (Builder $query) => $query->withTrashed(),
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['parent_id'] ?? null, fn (Builder $query, string $id) => $query->where('parent_id', $id))
            ->when($filters['active'] ?? null, fn (Builder $query, string $active) => $query->where('is_active', $active === 'active'))
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.references.categories.index', [
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.references.categories.create', [
            'category' => null,
            'parents' => $this->activeParents(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('references.categories.show', $category)
            ->with('status', 'La catégorie a été créée et activée.');
    }

    public function show(Category $category): View
    {
        $category->load('parent')->loadCount([
            'children',
            'assets' => fn (Builder $query) => $query->withTrashed(),
        ]);

        return view('admin.references.categories.show', ['category' => $category]);
    }

    public function edit(Category $category): View
    {
        return view('admin.references.categories.edit', [
            'category' => $category,
            'parents' => $this->activeParents($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($category, $data): void {
            $target = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $newParentId = $data['parent_id'] ?? null;

            if (
                (string) $target->parent_id !== (string) $newParentId
                && ($target->children()->exists() || $target->assets()->withTrashed()->exists())
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'La hiérarchie d’une catégorie utilisée ou ayant des sous-catégories ne peut pas être modifiée.',
                ]);
            }

            $target->fill($data)->save();
        });

        return redirect()
            ->route('references.categories.show', $category)
            ->with('status', 'La catégorie a été mise à jour.');
    }

    public function toggleStatus(Category $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $target = Category::query()->lockForUpdate()->findOrFail($category->getKey());

            if ($target->is_active) {
                if ($target->children()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'category' => 'Désactivez d’abord les sous-catégories actives.',
                    ]);
                }

                if ($target->assets()->withTrashed()->exists()) {
                    throw ValidationException::withMessages([
                        'category' => 'Cette catégorie est référencée par des biens. Elle ne peut pas être désactivée.',
                    ]);
                }
            }

            $target->is_active = ! $target->is_active;
            $target->save();
        });

        return redirect()
            ->route('references.categories.show', $category)
            ->with('status', $category->fresh()->is_active ? 'La catégorie a été réactivée.' : 'La catégorie a été désactivée.');
    }

    private function activeParents(?Category $category = null): \Illuminate\Database\Eloquent\Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->when($category, fn (Builder $query) => $query->whereKeyNot($category->getKey()))
            ->orderBy('name')
            ->get();
    }
}
