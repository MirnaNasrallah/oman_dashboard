<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    //This function gets today's exam, random and overall progress
    public function index()
    {
        $date = Date('Y-m-d');
        $todayData = $this->GetTodayData();
        $progressData = $this->GetTotalScannedToday($date);
        $batchCount = $this->batchesWorkFlow();
        $countPerSubject = $this->getStudentCountPerSubject();
        $DataperWeek = $this->GetTotalperDay();
        //return $todayData['TodaySsubjects'];
        return view('Dashboard', compact('todayData', 'progressData', 'batchCount', 'countPerSubject', 'DataperWeek'));
    }
    public function GetTodayData()
    {

        //$today = date("Y-m-d");
        $today = '2024-06-23';
        $schedule = DB::select('SELECT * FROM [OMAN_CSI_AUG].[dbo].[Schedule_updated]');
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


        $MarkingToday = [];
        for ($i = 0; $i < count($schedule_data); $i++) {
            $actualMarking = $schedule_data[$i]['Actual Marking'];
            list($startDate, $endDate) = explode(", ", $actualMarking);
            if ($today >= $startDate && $today <= $endDate) {
                $MarkingToday[] = $schedule_data[$i]['Subject']; // Collect subjects
            }
        }

        $student_counts = [];
        $count_per_subject = [];
        $results = DB::select("SELECT
        s.Subject AS Subject_name,
        COUNT(m.CIVILREC) AS student_count
        FROM
        OMAN_OP.dbo.OPMaster m
        JOIN
        OMAN_OP.dbo.OPSubject s
        ON
        m.PRASubjectCode = s.PRASubjectCode
       GROUP BY
        s.Subject");
        if ($results) {
            foreach ($results as $row) {
                $array = get_object_vars($row);
                array_push($count_per_subject, $array);
            }
        }
        $subjects = explode('&', $TodaySsubjects);
        $subjects = array_map('trim', $subjects);

        foreach ($subjects as $subject) {
            foreach ($count_per_subject as $key => $count) {
                if (stripos($key, $subject) !== false) {
                    $student_counts[] = $count;
                }
            }
        }
        if (count($student_counts) > 1) {
            // If there are multiple subjects, join them with "&" separator
            $student_counts = implode(' & ', $student_counts);
        } else {
            $student_counts = array_shift($student_counts);
        }

        // $batches_per_subject = DB::select("SELECT b.Batchtype, COUNT(b.Batch) AS NumberOfBatches, s.Subject
        // FROM batch b
        // Join OMAN_OP.dbo.OPSubject s
        // on s.Short = b.BatchType
        // WHERE b.BatchStatus = 'Validated'
        // and b.BatchType like 'GE%'
        // GROUP BY b.Batchtype, s.Subject
        // ");
        $batches_per_subject = DB::select("WITH BatchCounts AS (
            SELECT
                b.Batchtype,
                COUNT(b.Batch) AS NumberOfBatches,
                s.Subject
            FROM
                batch b
            JOIN
                OMAN_OP.dbo.OPSubject s ON s.Short = b.BatchType
            WHERE
                b.BatchStatus = 'Validated'
                AND b.BatchType LIKE 'GE%'
            GROUP BY
                b.Batchtype, s.Subject
        ),
        BookletCounts AS (
            SELECT
                SubjectCode,
                SUM(CASE WHEN scanned = 1 THEN 1 ELSE 0 END) AS CSINormalScanned
            FROM
                OMAN_CSI_AUG.dbo.booklet WITH (NOLOCK)
            WHERE
                SubjectCode LIKE 'GE%'
            GROUP BY
                SubjectCode
        )
        SELECT
            bc.Batchtype,
            bc.NumberOfBatches,
            bc.Subject,
            COALESCE(BookletCounts.CSINormalScanned, 0) AS CSINormalScanned
        FROM
            BatchCounts bc
        LEFT JOIN
            BookletCounts ON BookletCounts.SubjectCode = bc.Batchtype
       ");

        $MarkingTodaySHORT = [];
        for ($i = 0; $i < count($schedule_data); $i++) {
            $actualMarking = $schedule_data[$i]['Actual Marking'];
            list($startDate, $endDate) = explode(", ", $actualMarking);
            if ($today >= $startDate && $today <= $endDate) {
                $MarkingTodaySHORT[$schedule_data[$i]['Short']] = $schedule_data[$i]['Subject']; // Collect subjects
            }
        }
        $matchedSubjectsBatches = [];
        $matchedSubjectsBooklets = [];


        foreach ($batches_per_subject as $batch) {
            foreach ($MarkingTodaySHORT as $short => $subject) {
                if (strpos($batch->Batchtype, $short) === 0 || strpos($short, $batch->Batchtype) === 0) {
                    $matchedSubjectsBatches[$subject] = $batch->NumberOfBatches;
                    $matchedSubjectsBooklets[$subject] = $batch->CSINormalScanned;
                    break; // Exit inner loop once a match is found
                }
            }
        }

        return [
            "schedule_data" => $schedule_data,
            "TodaySsubjects" => $TodaySsubjects,
            "TodaySRandom" => $TodaySRandom,
            "MarkingToday" => $MarkingToday,
            "student_counts" => $student_counts,
            "batches_per_subject" => $matchedSubjectsBatches,
            "booklets_per_subject" => $matchedSubjectsBooklets
        ];


        //return view('Dashboard', compact('schedule_data', 'TodaySsubjects', 'TodaySRandom', 'MarkingToday'));
    }





    public function GetTotalScannedToday($date)
    {
        //$today = date("Y-m-d");

        $today = $date ?? new DateTime('2023-09-05');
        //dd($date);
        //$today = '2023-09-05';
        try {
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
            // Log and handle the exception
            Log::error('Error executing SQL query: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()]);
        }

        $Total_scanned_data = [];
        if ($results) {
            foreach ($results as $row) {
                $array = get_object_vars($row);
                array_push($Total_scanned_data, $array);
            }
        }
        //dd($Total_scanned_data);
        return $Total_scanned_data;

        //return view('Dashboard', compact('Total_scanned_data'));

    }
    public function GetTotalperDay()
    {
        // Set the current date for testing
        //$currentDate = new DateTime('today');
        $currentDate = new DateTime('2023-09-05');

        // Generate arrays for normal and random progress data
        $normaldata = [];
        $RandomProgress = [];

        // Iterate over the last 7 days including today
        for ($i = 6; $i >= 0; $i--) {
            $date = clone $currentDate;
            //dd($date);
            $date->modify("-$i days");
            $formattedDate = $date->format('Y-m-d');

            // Retrieve data for the given date
            $daysData = $this->GetTotalScannedToday($formattedDate);
            //dd($daysData);
            // Format the date to a day name
            $dayName = $date->format('D');

            // Extract the total scanned data for normal and random scans
            $total = end($daysData);

            // Assign the data to the respective arrays
            $normaldata[$dayName] = $total['ScannedTodayCSI'];
            $RandomProgress[$dayName] = $total['ScannedTodayRandomCSI'];
        }

        // Debugging output
        //dd($normaldata, $RandomProgress);

        return [
            "normal_data" => $normaldata,
            "random_data" => $RandomProgress
        ];
    }


    // public function GetTotalperDay()
    // {
    //    // $currentDate = new DateTime('today');
    //    $currentDate = new DateTime('2023-09-05');
    //     // Generate an array of the last 7 days including today
    //     $normaldata = [];
    //     $RandomProgress = [];
    //     for ($i = 6; $i >= 0; $i--) {
    //         $date = clone $currentDate;
    //         $date->modify("-$i days");
    //         $formattedDate = $date->format('Y-m-d');
    //         $daysData = $this->GetTotalScannedToday($formattedDate);
    //         //dd($daysData);
    //         $purpleLabels = date('D', strtotime("-$i days", strtotime('2023-09-05')));
    //         $total = end($daysData);
    //        // dd($total['ScannedTodayCSI']);
    //         $normaldata[$purpleLabels] = $total['ScannedTodayCSI'];
    //         $RandomProgress[$purpleLabels] = $total['ScannedTodayRandomCSI'];
    //     }
    //     dd($normaldata);


    //     return [
    //         "normal_data" => $normaldata,
    //         "random_data" => $RandomProgress
    //     ];
    // }
    public function batchesWorkFlow()
    {
        /////////////normal//////////////
        $batch_count = [];
        $batch_count_result = DB::select("SELECT
        st.StepID,
        st.Description,
        COUNT(bt.Batch) AS BatchCount
        FROM
        OMAN_WFS.dbo.Step st
        LEFT JOIN
        OMAN_WFS.dbo.StepWorkItem stw ON st.StepID = stw.StepId
        LEFT JOIN
        OMAN_WFS.dbo.v_WorkItemBatchInfo bt ON stw.WorkItemID = bt.WorkItemID
        GROUP BY
        st.StepID,
        st.Description
        ORDER BY
        st.StepID");
        if ($batch_count_result) {
            foreach ($batch_count_result as $row) {
                $array = get_object_vars($row);
                array_push($batch_count, $array);
            }
        }
        ////////////////////////random///////////////////////
        // $batch_count_rd = [];
        // $batch_count_result_rd = DB::select("SELECT
        // st.StepID,
        // st.Description,
        // COUNT(bt.Batch) AS BatchCount
        // FROM
        // OMAN_WFS_RANDOM.dbo.Step st
        // LEFT JOIN
        // OMAN_WFS_RANDOM.dbo.StepWorkItem stw ON st.StepID = stw.StepId
        // LEFT JOIN
        // OMAN_WFS_RANDOM.dbo.v_WorkItemBatchInfo bt ON stw.WorkItemID = bt.WorkItemID
        // GROUP BY
        // st.StepID,
        // st.Description
        // ORDER BY
        // st.StepID");
        // if ($batch_count_result_rd) {
        //     foreach ($batch_count_result_rd as $row) {
        //         $array = get_object_vars($row);
        //         array_push($batch_count_rd, $array);
        //     }
        // }
        $batch_count_rd = $batch_count;

        return [
            "batch_count" => $batch_count,
            "batch_count_rd" => $batch_count_rd
        ];
    }

    public function getStudentCountPerSubject()
    {
        $count_per_subject = [];
        $results = DB::select("SELECT
        s.Subject AS Subject_name,
        COUNT(m.CIVILREC) AS student_count
        FROM
        OMAN_OP.dbo.OPMaster m
        JOIN
        OMAN_OP.dbo.OPSubject s
        ON
        m.PRASubjectCode = s.PRASubjectCode
       GROUP BY
        s.Subject");
        if ($results) {
            foreach ($results as $row) {
                $array = get_object_vars($row);
                array_push($count_per_subject, $array);
            }
        }
        return $count_per_subject;
    }
}
