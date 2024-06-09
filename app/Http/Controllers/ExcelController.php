<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Response;

class ExcelController extends Controller
{

    public function exportCsv(Request $request)
    {
        // Get the keyword from the request
        $keyword = $request->query('keyword', 'default');

        // Fetch the data based on the keyword
        $data = $this->getDataToExport($keyword);

        // Create a filename
        $filename = "export_" . date('Ymd') . "_$keyword.csv";

        // Set headers for the CSV download
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

            // Write the BOM to the file
            fwrite($file, "\xEF\xBB\xBF");

            if (!empty($data)) {
                // Add the header of the CSV file
                fputcsv($file, array_keys($data[0]));

                // Add the data rows
                foreach ($data as $row) {
                    // Ensure that each value is properly encoded as UTF-8
                    fputcsv($file, $row);
                }
            }

            fclose($file);
        };

        // Return a streaming response with the CSV content
        return Response::stream($callback, 200, $headers);
    }

    private function getDataToExport($keyword)
    {
        switch ($keyword) {
            case 'schedule':
                try {
                    $results = DB::select('SELECT * FROM [OMAN_CSI_AUG].[dbo].[Schedule]');
                } catch (\Exception $e) {
                    $results = [];
                }
                break;

            case 'progress':
                try {
                    $today = date("Y-m-d");
                    //$today = '2024-06-23';
                    $startTime = date("Y-m-d", strtotime($today)) . ' 00:00:01.000';
                    $endTime = date("Y-m-d", strtotime($today)) . ' 23:59:59.999';

                    $results = DB::select("WITH bT AS
                    (
                        SELECT
                            BookletName AS SubjectCode,
                            FormNo
                        FROM
                            BookletType WITH (NOLOCK)
                    ),
                    bCSI AS
                    (
                        SELECT
                            subjectcode,
                            SUM(CASE WHEN scanned = 1 AND CONVERT(DATETIME, ScanDateTime, 21) BETWEEN CONVERT(DATETIME, ?, 21) AND CONVERT(DATETIME, ?, 21) THEN 1 ELSE 0 END) AS ScannedTodayCSI,
                            SUM(CASE WHEN scanned = 1 THEN 1 ELSE 0 END) AS CSINormalScanned
                        FROM
                            OMAN_CSI_AUG.dbo.booklet WITH (NOLOCK)
                        GROUP BY
                            SubjectCode
                    ),
                    bRanCSI AS
                    (
                        SELECT
                            subjectcode,
                            SUM(CASE WHEN scanned = 1 AND CONVERT(DATETIME, ScanDateTime, 21) BETWEEN CONVERT(DATETIME, ?, 21) AND CONVERT(DATETIME, ?, 21) THEN 1 ELSE 0 END) AS ScannedTodayRandomCSI,
                            SUM(CASE WHEN scanned = 1 THEN 1 ELSE 0 END) AS CSIRandomScanned
                        FROM
                            OMAN_CSI_RANDOM.dbo.booklet WITH (NOLOCK)
                        GROUP BY
                            SubjectCode
                    ),
                    track AS
                    (
                        SELECT
                            bt.BookletName,
                            SUM(CASE WHEN f.Received = 1 AND ReceiptStatus = 'Blank' THEN 1 ELSE 0 END) AS TotalBlank,
                            SUM(CASE WHEN f.Received = 0 AND ReceiptStatus = '' THEN 1 ELSE 0 END) AS ToLog,
                            SUM(CASE WHEN f.Received = 1 AND ReceiptStatus = 'Scannable' AND f.scanned = 0 THEN 1 ELSE 0 END) AS ToActualScan,
                            SUM(CASE WHEN f.Received = 1 THEN 1 ELSE 0 END) AS TotalLogged,
                            SUM(CASE WHEN f.Scanned = 1 THEN 1 ELSE 0 END) AS TrackerTotalScanned,
                            SUM(CASE WHEN f.Received = 1 AND ReceiptStatus = 'Scannable' AND CONVERT(DATETIME, ReceiptDateTime, 21) BETWEEN CONVERT(DATETIME, ?, 21) AND CONVERT(DATETIME, ?, 21) THEN 1 ELSE 0 END) AS TotalLoggedToday,
                            COUNT(f.Barcode) AS Total
                        FROM
                            tracker.dbo.form f WITH (NOLOCK)
                        LEFT JOIN
                            tracker.dbo.FormType ft WITH (NOLOCK) ON f.FormTypeID = ft.FormTypeID
                        LEFT JOIN
                            OMAN_CSI_AUG.dbo.BookletType bt WITH (NOLOCK) ON bt.TrackerFormTypeId = ft.FormTypeID
                        GROUP BY
                            BookletName
                    ),
                    display AS
                    (
                        SELECT
                            FormNo,
                            ab.SubjectCode,
                            ISNULL(a.ScannedTodayCSI, 0) AS ScannedTodayCSI,
                            ISNULL(b.ScannedTodayRandomCSI, 0) AS ScannedTodayRandomCSI,
                            ISNULL(c.TotalBlank, 0) AS TotalBlank,
                            ISNULL(c.ToLog, 0) AS ToLog,
                            ISNULL(c.ToActualScan, 0) AS ToActualScan,
                            ISNULL(c.TrackerTotalScanned, 0) AS TrackerTotalScanned,
                            ISNULL(CASE WHEN c.TrackerTotalScanned = a.CSINormalScanned THEN a.CSINormalScanned ELSE a.CSINormalScanned + b.CSIRandomScanned END, 0) AS CSITotalScanned,
                            ISNULL(a.CSINormalScanned, 0) AS CSINormalScanned,
                            ISNULL(b.CSIRandomScanned, 0) AS CSIRandomScanned,
                            ISNULL(c.TotalLogged, 0) AS TotalLogged,
                            ISNULL(c.TotalLoggedToday, 0) AS TotalLoggedToday,
                            ISNULL(c.Total, 0) AS Total
                        FROM
                            bT AS ab
                        LEFT JOIN
                            track AS c ON ab.SubjectCode = c.BookletName
                        LEFT JOIN
                            bCSI AS a ON ab.SubjectCode = a.SubjectCode
                        LEFT JOIN
                            bRanCSI AS b ON ab.SubjectCode = b.SubjectCode
                    )

                    SELECT * FROM display

                    UNION ALL

                    SELECT 'Total', '<----->',
                        SUM(ScannedTodayCSI),
                        SUM(ScannedTodayRandomCSI),
                        SUM(TotalBlank),
                        SUM(ToLog),
                        SUM(ToActualScan),
                        SUM(TrackerTotalScanned),
                        SUM(CSITotalScanned),
                        SUM(CSINormalScanned),
                        SUM(CSIRandomScanned),
                        SUM(TotalLogged),
                        SUM(TotalLoggedToday),
                        SUM(Total)
                    FROM display

                    ORDER BY FormNo
                   ", [$startTime, $endTime, $startTime, $endTime, $startTime, $endTime]);
                } catch (\Exception $e) {
                    $results = [];
                }
                break;
            case 'lookup':
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
                break;
            case 'lookup_blank':
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

                into OMAN_CSI_AUG.dbo.BookletLookup_Blank
                --into BookletLookup

                FROM oman_op.dbo.OPMaster op WITH (NOLOCK) LEFT JOIN TRACKER.DBO.FORM f WITH (NOLOCK) ON op.BookletBarcode = f.barcode
                left join tracker.dbo.bundle b on f.bundleid = b.bundleid
                left join tracker.dbo.storagebox s on b.storageboxid = s.storageboxid
                --WHERE f.Received = 1 and f.receiptstatus = 'Scannable'
                WHERE f.Received = 1 and f.receiptstatus = 'Blank'
                ");
                        $results = DB::select("SELECT * FROM OMAN_CSI_AUG.dbo.BookletLookup_Blank");
                    } catch (\Exception $e) {
                        $results = DB::select("SELECT * FROM OMAN_CSI_AUG.dbo.BookletLookup_Blank");
                    }
                } catch (\Exception $e) {
                    $results = [];
                }
                break;
            case 'missingBooklet':
                try {
                    $results = DB::select("USE Tracker
                select
                op.region,
                op.schoolcode,
                op.school_name,
                op.serialno,
                op.studentname,
                op.seatno,
                op.[CENTER_EXAM],
                op.[EXAMCENTRENAME],
                ft.[description],
                f.barcode,
                op.PRASubjectCode
                from
                oman_op.dbo.opmaster op with (nolock)
                left join oman_op.dbo.opsubject opsub with (nolock) on op.prasubjectcode = opsub.prasubjectcode
                left join Form f with (nolock) on op.bookletbarcode = f.Barcode
                left join FormType ft with (nolock) on f.FormTypeID = ft.FormTypeID
                left join bundle b with (nolock) on f.bundleid = b.bundleid
                left join (select right(b.NEISTFormNumber,2) as 'PraSubjectCode', count(*) as 'NotRecevied' from form a left join formtype b on a.FormTypeID = b.FormTypeID where a.Received = 0 group by b.NEISTFormNumber) nr
                on op.PRASubjectCode = nr.PraSubjectCode
                left join (select right(b.NEISTFormNumber,2) as 'PraSubjectCode', count(*) as 'Received' from form a left join formtype b on a.FormTypeID = b.FormTypeID where a.Received = 1 group by b.NEISTFormNumber) rec
                on op.PRASubjectCode = rec.PraSubjectCode
                where opsub.[subject] like '%%' -- and b.neistbatchid >= 500000
                and NotRecevied < 35 and rec.Received > 1
                and op.PRASubjectcode not in ('')
                and f.Received = 0
                order by ft.[description]");
                } catch (\Exception $e) {
                    $results = [];
                }
                break;
            default:
                try {
                    $results = DB::select('SELECT 1+1 FROM DUAL');
                } catch (\Exception $e) {
                    // Handle query execution errors
                    $results = [];
                }
                break;
        }

        // Convert results to array format
        $data = [];
        foreach ($results as $row) {
            $data[] = (array) $row;
        }

        return $data;
    }
}
