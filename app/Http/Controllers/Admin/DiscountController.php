<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    /**
     * Store a newly created discount.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:discounts,code',
            'description' => 'nullable|string',
            'percentage' => 'required|numeric|min:0|max:100',
            'min_purchase' => 'required|numeric|min:0',
            'active' => 'boolean',
            'requires_code' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'time_from' => 'nullable|date_format:H:i',
            'time_until' => 'nullable|date_format:H:i',
        ]);
        
        \App\Models\Discount::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description'),
            'percentage' => $request->input('percentage'),
            'min_purchase' => $request->input('min_purchase'),
            'active' => $request->boolean('active', true),
            'requires_code' => $request->boolean('requires_code', true),
            'valid_from' => $request->input('valid_from'),
            'valid_until' => $request->input('valid_until'),
            'time_from' => $request->input('time_from'),
            'time_until' => $request->input('time_until'),
        ]);
        
        return redirect()->back()->with('success', 'Diskon berhasil ditambahkan');
    }
    
    /**
     * Update the specified discount.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $discount = \App\Models\Discount::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:discounts,code,' . $discount->id,
            'description' => 'nullable|string',
            'percentage' => 'required|numeric|min:0|max:100',
            'min_purchase' => 'required|numeric|min:0',
            'active' => 'boolean',
            'requires_code' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'time_from' => 'nullable|date_format:H:i',
            'time_until' => 'nullable|date_format:H:i',
        ]);
        
        $discount->update([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description'),
            'percentage' => $request->input('percentage'),
            'min_purchase' => $request->input('min_purchase'),
            'active' => $request->boolean('active', true),
            'requires_code' => $request->boolean('requires_code', true),
            'valid_from' => $request->input('valid_from'),
            'valid_until' => $request->input('valid_until'),
            'time_from' => $request->input('time_from'),
            'time_until' => $request->input('time_until'),
        ]);
        
        return redirect()->back()->with('success', 'Diskon berhasil diperbarui');
    }
    
    /**
     * Remove the specified discount.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $discount = \App\Models\Discount::findOrFail($id);
        $discount->delete();
        
        return redirect()->back()->with('success', 'Diskon berhasil dihapus');
    }
    
    /**
     * Toggle the active status of a discount.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleActive($id)
    {
        $discount = \App\Models\Discount::findOrFail($id);
        $discount->active = !$discount->active;
        $discount->save();
        
        $status = $discount->active ? 'diaktifkan' : 'dinonaktifkan';
        
        return redirect()->back()->with('success', "Diskon berhasil {$status}");
    }
}
