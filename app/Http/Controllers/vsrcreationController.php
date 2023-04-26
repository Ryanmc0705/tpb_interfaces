<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class vsrcreationController extends Controller
{
    public function index(Request $request)
    {

        $tmp = DB::table('sm_seqno')
            ->where('seqtype', 'API_SAP')
            ->update([
                'seqno' => DB::raw('seqno + 1')
            ]);


        $tmp_vsr_no = DB::table('sm_seqno')->select('seqno')->where('seqtype', 'API_SAP')->first();
        $tmp_vsr_no = $tmp_vsr_no->seqno;
        $arr1 = array();
        $filename = "";
        $attributes = $request->data;
        foreach ($request->data as $row) {

            $company_code = $row["BUCode"];
            $env = DB::table('sm_lookup_company_env')->where('company_code', $company_code)->value('env');


            $row["filename"] = $tmp_vsr_no . "." . $env;
            $filename = $tmp_vsr_no . "." . $env;
            $row["VsrNo"] = str_pad($tmp_vsr_no, 8, "0", STR_PAD_LEFT);
            $row["CreatedDate"] = date('Y-m-d');
            array_push($arr1, $row);
        }


        if (DB::table('sm_staging_mms_vsr_api')->insert($arr1)) {
            if (DB::update('exec SP_PROCESS_VSR_API ?', array($tmp_vsr_no . "." . $env))) {
                return response([
                    'status' => 'success',
                    'data' =>   $this->getResponse($filename)
                ], 200);
            } else {
                return response([
                    'message' => 'Error DB'
                ], 400);
            }
        } else {
            return response([
                'message' => 'Failed',
                'data' =>  $arr1
            ], 400);
        }
    }
    public function vsrInterfaceWithChild(Request $request)
    {
        $start_time = microtime(true);
        $vsr_no2 = "";
        // $tmp_vsr_no = DB::table('sm_seqno')->select('seqno')->where('seqtype', 'API_SAP')->first();
        // $tmp_vsr_no = $tmp_vsr_no->seqno;
        $arr1 = array();
        $filename = "";
        $attributes = $request->data;
        $user_id = "";
        $status = "success";
        $existing = false;
        foreach ($request->data as $row) {

            $company_code = $row["BUCode"];
            $env = DB::table('sm_lookup_company_env')->where('company_code', $company_code)->value('env');
            $vsr_no2 = substr($row["CVSRNo"], 0, 8);
            $env = $row["ENV"];
            $filename = $row["CVSRNo"] . "." . $env;
            $row["VsrNo"] = $row["CVSRNo"];
            $row["CreatedDate"] = date('Y-m-d');
            array_push($arr1, [
                "filename"  => $filename,
                "VsrNo" => $row["CVSRNo"],
                "OriginCode"    => $row["OriginCode"],
                "DestinationCode"   => $row["DestinationCode"],
                "DepartureDate" => date('Y-m-d'),
                "CarrierName"   => $row["CarrierName"],
                "VanNo" => $row["VanNo"],
                "SealNo"    => $row["SealNo"],
                "PadlockNo" => $row["PadlockNo"],
                "BUCode"    => $row["BUCode"],
                "DeptCode"  => $row["DeptCode"],
                "PONo"  => $row["PO_DR"],
                "TrfNo" => $row["TrfNo"],
                "Box"   => $row["Box"],
                "UserID"    => $row["UserID"],
                "CreatedDate"   => date('Y-m-d'),
                "VsrType"   => $row["VsrType"],
                "DRNumber"  => $row["DRNumber"],
                "cbm"   => $row["cbm"],
                "conveyance_type" => $row["conveyance_type"]

            ]);
            $user_id = $row["UserID"];

           
        }
        
        foreach (array_chunk($arr1, 101) as $chunks) {
            DB::table('sm_staging_mms_vsr_api')->insert($chunks);
        }
        $vsr_list = DB::table("sm_staging_mms_vsr_api")
            ->select("filename")
            ->whereRaw("SUBSTRING(VsrNo,1,8) = $vsr_no2")
            ->groupBy("filename")
            ->get();

        foreach ($vsr_list as $row) {
            $count = DB::table("sm_vsr_hdr")->where("filename",$row->filename)->count();
            if($count == 0){
                $existing = false;
                DB::update('exec SP_PROCESS_VSR_API ?', array($row->filename));
            }else{
                $existing = true;
            }
            
        }
        $end_time = microtime(true);
        $time_elapsed_secs = round(($end_time - $start_time), 4);
        DB::table("sm_execution_logs_vsr")->insert(["vsr_no"=>$vsr_no2,"execution_time"=>$time_elapsed_secs,"user_id" => $user_id]);
        
        if(!$existing){
            return response([
                "Status" => "Success",
                "VSR_Number"=> $vsr_no2,
                "Created_DT"=> date('Y-m-d'),
                "Message"=> "VSR Number successfully added"
            ], 200);
        }else{
            return response([
                "Status" => "Failed",
                "VSR_Number"=> $vsr_no2,
                "Created_DT"=> date('Y-m-d'),
                "Message"=> "VSR Number is already existing"
            ], 409);
        }
        

    }

    public function getResponse($filename)
    {
        $response = [];
        $header = DB::select("SELECT a.vsr_no
        ,a.origin_code
        ,a.destination_code
        ,a.departure_date
        ,b.carrier_name
        ,c.conveyance_type
        ,a.van_no
        ,a.VsrType
        FROM sm_vsr_hdr a
        LEFT JOIN sm_carrier b
        ON a.carrier_code = b.carrier_code
        LEFT JOIN sm_conveyance_type c
        ON a.conveyance_type_id = c.conveyance_type_id
        WHERE a.filename = '$filename'");

        foreach ($header as $row) {

            $vsr_no = $row->vsr_no;
            $details = DB::select("SELECT A.vsr_no,
            A.po_no,
            A.trf_no,
            A.dept_code,
            A.bu_code,
            A.cbm,
            A.bu_code
            FROM sm_vsr_dtl A
            LEFT JOIN sm_vsr_hdr B
            ON A.vsr_no = B.vsr_no
            WHERE B.vsr_no = '$vsr_no'");

            $row->details = $details;
            array_push($response, $row);
        }

        return $response;
    }
}
