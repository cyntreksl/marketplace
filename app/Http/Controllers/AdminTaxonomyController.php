<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Http\Requests\ArchiveResourceRequest;
use App\Http\Requests\ImportGoogleProductTaxonomyRequest;
use App\Models\GoogleProductTaxonomyVersion;
use App\Services\AdminCatalogService;
use App\Services\GoogleProductTaxonomyImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminTaxonomyController extends Controller
{
    public function index(Request $request, GoogleProductTaxonomyRepository $taxonomy): Response
    {
        Gate::authorize('viewAny', GoogleProductTaxonomyVersion::class);

        return Inertia::render('admin/taxonomy/index', ['versions' => $taxonomy->versions($request->only('archived'))]);
    }

    public function store(ImportGoogleProductTaxonomyRequest $request, GoogleProductTaxonomyImportService $taxonomy): RedirectResponse
    {
        $taxonomy->import($request->user(), $request->file('taxonomy_file'), $request->validated('version'), $request->validated('locale'));

        return to_route('admin.taxonomy.index')->with('status', 'Taxonomy imported for review.');
    }

    public function activate(ArchiveResourceRequest $request, GoogleProductTaxonomyVersion $taxonomy, GoogleProductTaxonomyImportService $service): RedirectResponse
    {
        Gate::authorize('update', $taxonomy);
        $service->activate($request->user(), $taxonomy, $request->validated('reason'));

        return to_route('admin.taxonomy.index')->with('status', 'Taxonomy version activated.');
    }

    public function destroy(ArchiveResourceRequest $request, GoogleProductTaxonomyVersion $taxonomy, AdminCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('delete', $taxonomy);
        abort_if($taxonomy->is_active, 422, 'An active taxonomy version cannot be archived.');
        $catalog->archive($request->user(), $taxonomy, $request->validated('reason'));

        return to_route('admin.taxonomy.index')->with('status', 'Taxonomy version archived.');
    }
}
