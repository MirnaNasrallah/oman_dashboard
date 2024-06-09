<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SubjectsController;
use App\Http\Controllers\UtilitiesController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('dashboard');
});
Route::get('/sign-in', function () {
    return view('sign-in');
})->name('sign-in');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');
Route::get('/subjects', [SubjectsController::class, 'index'])->name('subjects');
Route::get('/utilities', [UtilitiesController::class, 'index'])->name('utilities');
Route::get('/export-csv', [ExcelController::class, 'exportCsv'])->name('export.csv');


Route::get('/Dashboard/data', function () {
    // $today = date("Y-m-d");
    $today = '2024-06-23';
    $schedule = DB::select('SELECT * FROM [OMAN_CSI_AUG].[dbo].[Schedule]');
    $schedule_data = [];
    foreach ($schedule as $row) {
        $array = get_object_vars($row);
        array_push($schedule_data, $array);
    }
    $TodaySsubjects = []; // Array to store subjects
    for ($i = 0; $i < count($schedule_data); $i++) {
        if ($schedule_data[$i]['Exam Date'] == $today) {
            $TodaySsubjects[] = $schedule_data[$i]['Subject']; // Collect subjects
        }
    }
    if (count($TodaySsubjects) > 1) {
        // If there are multiple subjects, join them with "&" separator
        $TodaySsubjects = implode(' & ', $TodaySsubjects);
    } else {
        $TodaySsubjects = array_shift($TodaySsubjects);
    }

    $TodaySRandom = []; // Array to store subjects
    for ($i = 0; $i < count($schedule_data); $i++) {
        if ($schedule_data[$i]['Random'] == $today) {
            $TodaySRandom[] = $schedule_data[$i]['Subject']; // Collect subjects
        }
    }
    if (count($TodaySRandom) > 1) {
        // If there are multiple subjects, join them with "&" separator
        $TodaySRandom = implode(' & ', $TodaySRandom);
    } else {
        $TodaySRandom = array_shift($TodaySRandom);
    }
    $actualMarking = $schedule_data[0]['Actual Marking'];
    list($startDate, $endDate) = explode(", ", $actualMarking);



    return $startDate;
});
Route::get('/test', function () {
    try {

        try {
            DB::statement("SELECT
    op.BookletBarcode as 'Barcode',
    op.REGION as 'Region Code',
    op.REGION_NAME as 'Region Name',
    op.CENTER_EXAM as 'Centre No',
    op.EXAMCENTRENAME as 'Centre Name',
    op.SCHOOLCODE as 'School Code',
    op.SCHOOL_NAME as 'School Name',
    op.STUDENTCODE as 'Student Code',
    op.STUDENTNAME as 'Student Name',
    op.CIVILREC as 'Civil Rec',
    op.SEATNO as 'Seat No',
    op.SUBJECT_CODE as 'MOE Code',
    op.PRAsubjectCode as 'Subject Code',
    op.SUBJECT_NAME as 'Subject Name',
    b.barcode as 'BatchBarcode',
    b.neistbatchid as 'Batch',
    f.serialnumber,
    s.[number] as 'BoxNumber'

    --into BookletLookup_Blank
    into OMAN_CSI_AUG.dbo.BookletLookup

    FROM oman_op.dbo.OPMaster op WITH (NOLOCK) LEFT JOIN TRACKER.DBO.FORM f WITH (NOLOCK) ON op.BookletBarcode = f.barcode
    left join tracker.dbo.bundle b on f.bundleid = b.bundleid
    left join tracker.dbo.storagebox s on b.storageboxid = s.storageboxid
    WHERE f.Received = 1 and f.receiptstatus = 'Scannable'
    --WHERE f.Received = 1 and f.receiptstatus = 'Blank'
    ");
            $results = DB::select("SELECT * FROM OMAN_CSI_AUG.dbo.BookletLookup");
        } catch (\Exception $e) {
            $results = DB::select("SELECT * FROM OMAN_CSI_AUG.dbo.BookletLookup");
        }
    } catch (\Exception $e) {
        $results = [];
    }
    $results = DB::select('SELECT * FROM [OMAN_CSI_AUG].[dbo].[Schedule]');
    $data = $results;
    //dd($results[0]);
    $filename = "export_" . date('Ymd') . "_test.csv";

    $headers = [
        'Content-Type'        => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename=' . $filename,
        'Pragma'              => 'no-cache',
        'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        'Expires'             => '0',
    ];

    // Callback function to output the CSV content
    $callback = function () use ($data) {
        $file = fopen('php://output', 'w');

        if (!empty($data[0])) {
            // Add the header of the CSV file

            fputcsv($file, array_keys($data[0]));

            // Add the data rows
            foreach ($data[0] as $row) {
                // Ensure that each value is properly encoded as UTF-8
                $row = array_map('utf8_encode', $row);
                //dd($row);
                fputcsv($file, $row);
            }
        }

        fclose($file);
    };

    // Return a streaming response with the CSV content
    return Response::stream($callback, 200, $headers);
});
