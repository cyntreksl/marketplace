<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class AdminPromotionController extends Controller
{
    public function store(StorePromotionRequest $request, PromotionService $promotions): RedirectResponse
    {
        $data = $request->validated();
        $promotions->create($request->user(), Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.homepage.index')->with('status', 'Promotion created.');
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion, PromotionService $promotions): RedirectResponse
    {
        $data = $request->validated();
        $promotions->update($request->user(), $promotion, Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.homepage.index')->with('status', 'Promotion updated.');
    }
}
