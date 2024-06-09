<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectsController extends Controller
{
    public function index() {
        $subjects = $this->getSubjectsData();
        return view('subjects', compact('subjects'));
    }

    private function getSubjectsData() {
        $subjects_query = "SELECT
        s.MOECode,
        s.PRASubjectCode,
        s.ExamGroup,
        s.Page,
        s.Short,
        s.Subject AS Subject_name,
        COUNT(m.CIVILREC) AS student_count,
        COALESCE(clean_skins.CleanSkins, 0) AS CleanSkins,
        COALESCE(booklet_count.BookletCount, 0) AS BookletCount
    FROM
        OMAN_OP.dbo.OPMaster m
    JOIN
        OMAN_OP.dbo.OPSubject s ON m.PRASubjectCode = s.PRASubjectCode
    LEFT JOIN (
        SELECT
            s.PRASubjectCode,
            COUNT(m.BookletBarcode) AS CleanSkins
        FROM
            OMAN_OP.dbo.OPMaster m
        JOIN
            OMAN_OP.dbo.OPSubject s ON m.PRASubjectCode = s.PRASubjectCode
        WHERE
            m.CIVILREC IS NULL
        GROUP BY
            s.PRASubjectCode
    ) AS clean_skins ON s.PRASubjectCode = clean_skins.PRASubjectCode
    LEFT JOIN (
        SELECT
            s.PRASubjectCode,
            COUNT(m.BookletBarcode) AS BookletCount
        FROM
            OMAN_OP.dbo.OPMaster m
        JOIN
            OMAN_OP.dbo.OPSubject s ON m.PRASubjectCode = s.PRASubjectCode
        GROUP BY
            s.PRASubjectCode
    ) AS booklet_count ON s.PRASubjectCode = booklet_count.PRASubjectCode
    GROUP BY
        s.MOECode,
        s.PRASubjectCode,
        s.ExamGroup,
        s.Page,
        s.Short,
        s.Subject,
        clean_skins.CleanSkins,
        booklet_count.BookletCount
    ORDER BY
        s.Short";

           $subjects_data = DB::select($subjects_query);



        return $subjects_data;
    }
    // public function index(Request $request)
    // {
    //     $subjects = $this->getSubjectsData($request->input('ExamGroup'));
    //     dd($subjects);
    //     return view('subjects', compact('subjects'));
    // }
    // public function getSubjectsData($examGroup)
    // {
    //     $query = "SELECT
    //     s.MOECode,
    //     s.PRASubjectCode,
    //     s.ExamGroup,
    //     s.Page,
    //     s.Short,
    //     s.Subject AS Subject_name,
    //     COUNT(m.CIVILREC) AS student_count
    // FROM
    //     OMAN_OP.dbo.OPMaster m
    // JOIN
    //     OMAN_OP.dbo.OPSubject s
    // ON
    //     m.PRASubjectCode = s.PRASubjectCode
    // GROUP BY
    //     s.MOECode,
    //     s.PRASubjectCode,
    //     s.ExamGroup,
    //     s.Page,
    //     s.Short,
    //     s.Subject";
    //     if ($examGroup) {
    //         $query .= " HAVING s.ExamGroup = ?";
    //         $subjects_data = DB::select($query, [$examGroup]);
    //     }else{
    //         $subjects_data = DB::select($query);
    //     }


    //     return $subjects_data;
    // }
}
