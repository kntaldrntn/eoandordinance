<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Models\Ordinance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('reports/Index', [
            'records' => ['data' => $this->getReportData($request), 'total' => count($this->getReportData($request))],
            'statuses' => DB::table('statuses')->orderBy('name')->get(),
            'filters' => $request->only(['type', 'status_id', 'date_from', 'date_to', 'search', 'has_irr', 'structure_type']), // 🚀 Added structure_type
        ]);
    }

    public function generate(Request $request)
    {
        $records = $this->getReportData($request);
        
        $pdf = Pdf::setOption(['isPhpEnabled' => true])
            ->loadView('reports.pdf', [
                'records' => $records,
                'filters' => $request->all(),
                'name' => auth()->user()->name ?? 'System Admin'
            ])->setPaper('a4', 'landscape');

        return $pdf->stream('Legislative_Report_' . date('Y-m-d') . '.pdf');
    }

    private function getReportData(Request $request)
    {
        $type = $request->input('type', 'all');
        $search = $request->input('search');
        $statusId = $request->input('status_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $hasIrr = $request->input('has_irr'); 
        $structureType = $request->input('structure_type'); // 🚀 Capture new filter

        $results = collect();

        // --- FETCH EXECUTIVE ORDERS ---
        if ($type === 'all' || $type === 'eo') {
            $eoQuery = ExecutiveOrder::with(['status', 'committees.members']);

            if ($statusId) $eoQuery->where('status_id', $statusId);
            if ($dateFrom) $eoQuery->whereDate('date_issued', '>=', $dateFrom);
            if ($dateTo) $eoQuery->whereDate('date_issued', '<=', $dateTo);
            
            if ($hasIrr === 'yes') {
                $eoQuery->has('implementingRules');
            } elseif ($hasIrr === 'no') {
                $eoQuery->doesntHave('implementingRules');
            }

            // 🚀 FIXED: Structure Type Filter Logic
            if ($structureType) {
                if ($structureType === 'none') {
                    // Standard EOs do not have a council or program committee attached
                    $eoQuery->whereDoesntHave('committees', function($q) {
                        $q->whereIn('type', ['council', 'program']);
                    });
                } else {
                    // Query the related 'committees' table for the specific type
                    $eoQuery->whereHas('committees', function($q) use ($structureType) {
                        $q->where('type', $structureType);
                    });
                }
            }
            
            if ($search) {
                $eoQuery->where(function($q) use ($search) {
                    $q->where('eo_number', 'LIKE', "%{$search}%")
                    ->orWhere('title', 'LIKE', "%{$search}%")
                    ->orWhere('subject_matter', 'LIKE', "%{$search}%")
                    ->orWhereHas('committees.members', function($mq) use ($search) {
                        $mq->where('name', 'LIKE', "%{$search}%");
                    });
                });
            }

            $eos = $eoQuery->get()->map(function($eo) {
                $committee = 'N/A';
                if (!empty($eo->committee_details['council']['chairman'])) {
                    $committee = 'Chairman: ' . $eo->committee_details['council']['chairman'];
                }

                return [
                    'doc_type' => 'EO',
                    'tracking_number' => $eo->eo_number,
                    'title' => $eo->title,
                    'subject_matter' => $eo->subject_matter ?? 'N/A',
                    'involved_parties' => $committee,
                    'date' => $eo->date_issued,
                    'status_name' => $eo->status->name ?? 'Unknown',
                    'timestamp' => current(explode(' ', $eo->date_issued)) 
                ];
            });
            $results = $results->concat($eos);
        }

        // --- FETCH ORDINANCES ---
        if ($type === 'all' || $type === 'ordinance') {
            $ordQuery = Ordinance::with(['status', 'committees.members']);

            if ($statusId) $ordQuery->where('status_id', $statusId);
            if ($dateFrom) $ordQuery->whereDate('date_enacted', '>=', $dateFrom);
            if ($dateTo) $ordQuery->whereDate('date_enacted', '<=', $dateTo);
            
            if ($hasIrr === 'yes') {
                $ordQuery->has('implementingRules');
            } elseif ($hasIrr === 'no') {
                $ordQuery->doesntHave('implementingRules');
            }
            
            if ($search) {
                $ordQuery->where(function($q) use ($search) {
                    $q->where('ordinance_number', 'LIKE', "%{$search}%")
                    ->orWhere('title', 'LIKE', "%{$search}%")
                    ->orWhere('subject_matter', 'LIKE', "%{$search}%")
                    ->orWhereHas('committees.members', function($mq) use ($search) {
                        $mq->where('name', 'LIKE', "%{$search}%");
                    });
                });
            }
            $ords = $ordQuery->get()->map(function($ord) {
                $author = $ord->author_details['primary_author'] ?? 'City Council';

                return [
                    'doc_type' => 'Ordinance',
                    'tracking_number' => $ord->ordinance_number,
                    'title' => $ord->title,
                    'subject_matter' => $ord->subject_matter ?? 'N/A',
                    'involved_parties' => 'Author: ' . $author,
                    'date' => $ord->date_enacted,
                    'status_name' => $ord->status->name ?? 'Unknown',
                    'timestamp' => current(explode(' ', $ord->date_enacted)) 
                ];
            });
            $results = $results->concat($ords);
        }

        return $results->sortByDesc('timestamp')->values()->all();
    }
}