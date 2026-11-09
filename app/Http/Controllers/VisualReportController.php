<?php

namespace App\Http\Controllers;

use App\Models\VisualReport;
use Inertia\Inertia;
use Inertia\Response;

class VisualReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('visual-reports', [
            'reports' => VisualReport::published()
                ->get(['id', 'title', 'description', 'image_path', 'file_path'])
                ->map(fn (VisualReport $report) => [
                    'id' => $report->id,
                    'title' => $report->title,
                    'description' => $report->description,
                    'image' => $report->image_path ? asset('storage/'.$report->image_path) : null,
                    'file' => $report->file_path ? asset('storage/'.$report->file_path) : null,
                ])
                ->all(),
        ]);
    }
}
