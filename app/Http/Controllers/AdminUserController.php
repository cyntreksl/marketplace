<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserExportRequest;
use App\Http\Requests\AdminUserIndexRequest;
use App\Services\AdminUserExportService;
use App\Services\AdminUserService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminUserController extends Controller
{
    public function index(AdminUserIndexRequest $request, AdminUserService $users): Response
    {
        return Inertia::render('admin/users/index', [
            ...$users->index($request->filters()),
            'exportColumns' => AdminUserExportService::columnOptions(),
        ]);
    }

    public function downloadExport(AdminUserExportRequest $request, AdminUserExportService $export): BinaryFileResponse
    {
        return response()
            ->download(
                $export->createTemporaryFile($request->filters(), $request->columns()),
                'users_'.now()->format('Y-m-d_H-i-s').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }
}
