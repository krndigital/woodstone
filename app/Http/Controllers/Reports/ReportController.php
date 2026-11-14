<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Reports\Period;
use App\Reports\ReportBuilder;
use App\Reports\ReportCatalog;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\XlsxWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reports with Excel and PDF export (step 11, §6, D-08). Base route needs `reports.view`;
 * owner-only reports (P&L, audit) are checked per report.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $list = [];
        foreach (ReportCatalog::all() as $key => $r) {
            if ($user->hasPermission($r['permission'])) {
                $list[] = ['key' => $key, 'title' => $r['title'], 'description' => $r['description'], 'group' => $r['group']];
            }
        }

        return Inertia::render('reports/Index', ['reports' => $list]);
    }

    public function show(Request $request, string $report): Response
    {
        $this->authorizeReport($request, $report);
        $period = Period::make($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);
        $meta = ReportCatalog::all()[$report];

        return Inertia::render('reports/Show', [
            'report' => ['key' => $report, 'title' => $meta['title'], 'description' => $meta['description']],
            'filters' => ['from' => $period->fromDate(), 'to' => $period->toDate(), 'bs' => $period->bsLabel()],
            'data' => ReportBuilder::build($report, $period),
        ]);
    }

    public function excel(Request $request, string $report): BinaryFileResponse
    {
        [$period, $meta, $data] = $this->load($request, $report);
        $headers = array_map(fn (array $c): string => $c['label'], $data['columns']);
        $rows = [];
        foreach ([...$data['rows'], ...($data['totals'] !== null ? [$data['totals']] : [])] as $row) {
            $line = [];
            foreach ($data['columns'] as $c) {
                $line[] = self::excelValue($row[$c['key']] ?? null, $c['type']);
            }
            $rows[] = $line;
        }

        $path = sys_get_temp_dir().'/report-'.bin2hex(random_bytes(8)).'.xlsx';
        XlsxWriter::write($path, $meta['title'], $headers, $rows, Settings::string('restaurant_name').' · '.$meta['title'].' · '.$period->label());
        Audit::record('report.exported', null, ['report' => $report, 'format' => 'xlsx', 'from' => $period->fromDate(), 'to' => $period->toDate()]);

        return response()->download($path, $report.'-'.$period->fromDate().'-'.$period->toDate().'.xlsx')->deleteFileAfterSend();
    }

    /**
     * Printable A4 page; the browser's "Save as PDF" makes the PDF.
     */
    public function pdf(Request $request, string $report): View
    {
        [$period, $meta, $data] = $this->load($request, $report);
        Audit::record('report.exported', null, ['report' => $report, 'format' => 'pdf', 'from' => $period->fromDate(), 'to' => $period->toDate()]);

        return view('reports.print', ['meta' => $meta, 'period' => $period, 'data' => $data, 'restaurant' => Settings::string('restaurant_name')]);
    }

    /**
     * @return array{0: Period, 1: array{title: string, description: string, group: string, permission: Permission}, 2: array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null, notes: list<string>}}
     */
    private function load(Request $request, string $report): array
    {
        $this->authorizeReport($request, $report);
        $period = Period::make($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);

        return [$period, ReportCatalog::all()[$report], ReportBuilder::build($report, $period)];
    }

    private function authorizeReport(Request $request, string $report): void
    {
        abort_unless(array_key_exists($report, ReportCatalog::all()), 404);
        /** @var User $user */
        $user = $request->user();
        abort_unless(ReportCatalog::allows($user, $report), 403);
    }

    private static function excelValue(mixed $v, string $type): string|int|float|null
    {
        return match ($type) {
            'money' => is_int($v) ? round($v / 100, 2) : null,
            'percent' => is_int($v) ? round($v / 100, 2) : null,
            'qty' => is_int($v) ? round($v / 1000, 3) : null,
            'int' => is_int($v) ? $v : null,
            default => is_scalar($v) ? self::safeText((string) $v) : null,
        };
    }

    /**
     * Prevent spreadsheet formula injection from user-entered text.
     */
    private static function safeText(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
    }
}
