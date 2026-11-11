<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MedicineRequest;
use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        return view('admin.medicines.index', [
            'medicines' => Medicine::with('category')
                ->search($search)
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.medicines.create', [
            'categories' => Category::orderBy('name')->get(),
            'medicine' => new Medicine,
        ]);
    }

    public function store(MedicineRequest $request): RedirectResponse
    {
        Medicine::create($request->validated());

        return to_route('admin.medicines.index')->with('success', 'Medicine added to the inventory.');
    }

    public function show(Medicine $medicine): RedirectResponse
    {
        return to_route('admin.medicines.edit', $medicine);
    }

    public function edit(Medicine $medicine): View
    {
        return view('admin.medicines.edit', [
            'categories' => Category::orderBy('name')->get(),
            'medicine' => $medicine,
        ]);
    }

    public function update(MedicineRequest $request, Medicine $medicine): RedirectResponse
    {
        $medicine->update($request->validated());

        return to_route('admin.medicines.index')->with('success', "{$medicine->fullName()} inventory updated.");
    }

    public function destroy(Medicine $medicine): RedirectResponse
    {
        if ($medicine->orderItems()->exists()) {
            return back()->with('error', "{$medicine->fullName()} cannot be deleted because it is referenced by order history.");
        }

        $medicine->delete();

        return to_route('admin.medicines.index')->with('success', "{$medicine->fullName()} removed from the inventory.");
    }
}
