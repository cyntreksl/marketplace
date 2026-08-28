<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\ReturnRequestRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnEvidenceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(int $returnRequest, int $evidence, ReturnRequestRepository $returns): StreamedResponse
    {
        $requestRecord = $returns->findWithContext($returnRequest);
        abort_if($requestRecord === null, 404);
        Gate::authorize('view', $requestRecord);

        $file = ($requestRecord->evidence ?? [])[$evidence] ?? null;
        abort_unless(is_array($file) && isset($file['path'], $file['name']), 404);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }
}
