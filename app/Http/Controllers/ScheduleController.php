<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index()
    {
        $scheduleData = $this->getSchedule();
        return view('schedule',compact('scheduleData'));
    }
    public function getSchedule()
    {
        $schedule_data = [];
        $schedule = DB::select('SELECT * FROM [OMAN_CSI_AUG].[dbo].[Schedule]');
        foreach ($schedule as $row) {
            $array = get_object_vars($row);
            array_push($schedule_data, $array);
        }
        return $schedule_data;
    }


}
