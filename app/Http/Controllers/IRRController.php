<?php

namespace App\Http\Controllers;

use App\Models\ImplementingRule;
use App\Models\ImplementingRuleandRegulation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IRRController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'executive_order_id' => 'required|exists:executive_orders,id',
            'lead_office_id' => 'required|exists:departments,id',
            'status' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $path = $request->file('file')->store('irrs', 'public');

            ImplementingRuleandRegulation::create([
                'executive_order_id' => $validated['executive_order_id'],
                'lead_office_id' => $validated['lead_office_id'],
                'status' => $validated['status'],
                'file_path' => $path,
            ]);
        });

        return redirect()->back()->with('success', 'IRR uploaded successfully.');
    }
}