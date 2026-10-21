<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\DatabaseService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    public function __construct(protected DatabaseService $database)
    {
    }

    public function index()
    {
        $tables = $this->database->dataTables();
        $counts = $this->database->counts();
        $info = $this->database->connectionInfo();
        $totalRows = array_sum($counts);

        $history = ActivityLog::where('model_type', 'database')->latest('id')->take(5)->get();

        return view('database.index', compact('tables', 'counts', 'info', 'totalRows', 'history'));
    }

    public function export(string $format)
    {
        if (! in_array($format, ['json', 'sql'], true)) {
            abort(404);
        }

        $stamp = now()->format('Ymd-His');

        if ($format === 'json') {
            $content = json_encode($this->database->exportData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $filename = 'supplirank-backup-'.$stamp.'.json';
            $mime = 'application/json';
        } else {
            $content = $this->database->sqlDump();
            $filename = 'supplirank-backup-'.$stamp.'.sql';
            $mime = 'application/sql';
        }

        ActivityLogger::log('exported', 'database', null, $filename, null, null, 'Backup database diunduh ('.$format.')');

        return response($content ?: '', 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportTable(string $table)
    {
        if (! in_array($table, $this->database->dataTables(), true)) {
            abort(404);
        }

        ActivityLogger::log('exported', 'database', null, $table.'.csv', null, null, 'Ekspor tabel '.$table.' ke CSV');

        return response($this->database->csv($table), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$table.'-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    public function showTable(string $table)
    {
        if (! in_array($table, $this->database->dataTables(), true)) {
            abort(404);
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        $rows = \Illuminate\Support\Facades\DB::table($table)
            ->orderBy($columns[0] ?? 'id')
            ->paginate(25)
            ->withQueryString();

        return view('database.table', compact('table', 'columns', 'rows'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:json,txt'],
            'mode' => ['required', 'in:replace,merge'],
        ], [
            'file.mimes' => 'Berkas harus berformat JSON (hasil ekspor sistem).',
            'file.max' => 'Ukuran berkas maksimal 20 MB.',
        ]);

        $decoded = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($decoded)) {
            return back()->with('error', 'Berkas JSON tidak valid atau rusak.');
        }

        try {
            $summary = $this->database->import($decoded, $request->string('mode')->toString());
        } catch (\Throwable $e) {
            return back()->with('error', 'Impor gagal: '.$e->getMessage());
        }

        $totalRows = array_sum($summary);
        $detail = collect($summary)->map(fn ($count, $table) => $table.': '.$count)->implode(', ');

        ActivityLogger::log(
            'imported',
            'database',
            null,
            $request->file('file')->getClientOriginalName(),
            null,
            $summary,
            'Impor database ('.$request->string('mode').') sebanyak '.$totalRows.' baris'
        );

        return redirect()->route('database.index')
            ->with('success', 'Impor berhasil: '.$totalRows.' baris dari '.count($summary).' tabel.')
            ->with('import_detail', $detail);
    }
}
