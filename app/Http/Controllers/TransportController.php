<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\Flysystem\Exception;
use Route;
use DB;
use App\Interfaces\ICrud;
use App\Interfaces\IValidate;
use App\Traits\UserHelper;
use App\Traits\DBHelper;
use App\Traits\Domain\FormulaHelper;
use App\Traits\Domain\PostageHelper;
use App\Transport;

class TransportController extends Controller implements ICrud, IValidate
{
    use UserHelper, DBHelper, FormulaHelper, PostageHelper;

    private $first_day, $last_day, $today;
    private $user;
    private $format_date, $format_time;
    private $table_name;
    private $skeleton;

    public function __construct()
    {
        $format_date_time  = $this->getFormatDateTime();
        $this->format_date = $format_date_time['date'];
        $this->format_time = $format_date_time['time'];

        $current_month   = $this->getCurrentMonth();
        $this->first_day = $current_month['first_day'];
        $this->last_day  = $current_month['last_day'];
        $this->today     = $current_month['today'];

        $jwt_data = $this->getCurrentUser();
        if ($jwt_data['status']) {
            $user_data = $this->getInfoCurrentUser($jwt_data['user']);
            if ($user_data['status'])
                $this->user = $user_data['user'];
        }

        $this->table_name = 'transport';
        $this->skeleton   = Transport::where('transports.active', true)
            ->leftJoin('products', 'products.id', '=', 'transports.product_id')
            ->leftJoin('customers', 'customers.id', '=', 'transports.customer_id')
            ->leftJoin('trucks', 'trucks.id', '=', 'transports.truck_id')
            ->leftJoin('truck_types', 'truck_types.id', '=', 'trucks.truck_type_id')
            ->leftJoin('postages', 'postages.id', '=', 'transports.postage_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'transports.created_by')
            ->leftJoin('users as updators', 'updators.id', '=', 'transports.updated_by')
            ->orderBy('transports.transport_date', 'desc')
            ->select('transports.*',
                'products.name as product_name',
                'customers.fullname as customer_fullname',
                'trucks.area_code as truck_area_code',
                'trucks.number_plate as truck_number_plate',
                'creators.fullname as creator_fullname',
                'updators.fullname as updator_fullname',
                'truck_types.name as truck_type_name',
                'postages.unit_price as postage_unit_price'
            );
    }

    /** API METHOD */
    public function getReadAll()
    {
        $arr_datas = $this->readAll();
        return response()->json($arr_datas, 200);
    }

    public function getReadOne()
    {
        $id  = Route::current()->parameter('id');
        $one = $this->readOne($id);
        return response()->json($one, 200);
    }

    public function postCreateOne(Request $request)
    {
        $data      = $request->input($this->table_name);
        $validates = $this->validateInput($data);
        if (!$validates['status'])
            return response()->json(['msg' => $validates['errors']], 404);

        if (!$this->createOne($data))
            return response()->json(['msg' => ['Create failed!']], 404);
        $arr_datas = $this->readAll();
        return response()->json($arr_datas, 201);
    }

    public function putUpdateOne(Request $request)
    {
        $data      = $request->input($this->table_name);
        $validates = $this->validateInput($data);
        if (!$validates['status'])
            return response()->json(['msg' => $validates['errors']], 404);

        if (!$this->updateOne($data))
            return response()->json(['msg' => ['Update failed!']], 404);
        $arr_datas = $this->readAll();
        return response()->json($arr_datas, 200);
    }

    public function patchDeactivateOne(Request $request)
    {
        $id = $request->input('id');
        if (!$this->deactivateOne($id))
            return response()->json(['msg' => 'Deactivate failed!'], 404);
        $arr_datas = $this->readAll();
        return response()->json($arr_datas, 200);
    }

    public function deleteDeleteOne(Request $request)
    {
        $id = Route::current()->parameter('id');
        if (!$this->deleteOne($id))
            return response()->json(['msg' => 'Delete failed!'], 404);
        $arr_datas = $this->readAll();
        return response()->json($arr_datas, 200);
    }

    public function getSearchOne()
    {
        $filter    = (array)json_decode($_GET['query']);
        $arr_datas = $this->searchOne($filter);
        return response()->json($arr_datas, 200);
    }

    /** LOGIC METHOD */
    public function readAll()
    {
        $transports = $this->skeleton->get();

        return [
            'transports' => $transports,
            'first_day'  => $this->first_day,
            'last_day'   => $this->last_day,
            'today'      => $this->today
        ];
    }

    public function readOne($id)
    {
        $one = Transport::find($id);
        return [$this->table_name => $one];
    }

    public function createOne($data)
    {
        try {
            DB::beginTransaction();
            $one       = new Transport();
            $one->code = $this->generateCode(Transport::class, 'TRANSPORT');

            $one->created_date = date('Y-m-d H:i:s');
            $one->updated_date = null;
            $one->active       = true;
            if (!$one->save()) {
                DB::rollback();
                return false;
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            return false;
        }
    }

    public function updateOne($data)
    {
        try {
            DB::beginTransaction();
            $one       = Transport::find($data['id']);
            $one->code = $data['code'];

            $one->updated_date = date('Y-m-d H:i:s');
            $one->active       = true;
            if (!$one->update()) {
                DB::rollBack();
                return false;
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            return false;
        }
    }

    public function deactivateOne($id)
    {
        try {
            DB::beginTransaction();
            $one         = Transport::find($id);
            $one->active = false;
            if (!$one->update()) {
                DB::rollBack();
                return false;
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            return false;
        }
    }

    public function deleteOne($id)
    {
        try {
            DB::beginTransaction();
            $one = Transport::find($id);
            if (!$one->delete()) {
                DB::rollBack();
                return false;
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            return false;
        }
    }

    public function searchOne($filter)
    {
        $from_date = $filter['from_date'];
        $to_date   = $filter['to_date'];
        $range     = $filter['range'];

        $transports = $this->skeleton;

        $transports = $this->searchFromDateToDate($transports, 'transports.created_date', $from_date, $to_date);

        $transports = $this->searchRangeDate($transports, 'transports.created_date', $range);

        return [
            'transports' => $transports->get()
        ];
    }

    /** VALIDATION */
    public function validateInput($data)
    {
        if (!$this->validateEmpty($data))
            return ['status' => false, 'errors' => 'Dữ liệu không hợp lệ.'];

        $msgs = $this->validateLogic($data);
        return $msgs;
    }

    public function validateEmpty($data)
    {
        return true;
    }

    public function validateLogic($data)
    {
        $msg_error = [];

        $skip_id = isset($data['id']) ? [$data['id']] : [];

        if ($this->checkExistData(Transport::class, 'code', $data['code'], $skip_id))
            array_push($msg_error, 'Mã bộ trung tâm đã tồn tại.');

        return [
            'status' => count($msg_error) > 0 ? false : true,
            'errors' => $msg_error
        ];
    }

    /** MY FUNCTION */
    public function getReadFormulas()
    {
        $filter    = (array)json_decode($_GET['query']);
        $arr_datas = $this->readFormulas($filter);
        return response()->json($arr_datas, 200);
    }

    public function readFormulas($data)
    {
        $customer_id    = $data['customer_id'];
        $transport_date = $data['transport_date'];

        $formulas = $this->findFormulas($customer_id, $transport_date);

        return $formulas;
    }

    public function getReadPostage()
    {
        $filter    = (array)json_decode($_GET['query']);
        $arr_datas = $this->readPostage($filter);
        return response()->json($arr_datas, 200);
    }

    public function readPostage($data)
    {
        $i_customer_id    = $data['customer_id'];
        $i_transport_date = $data['transport_date'];
        $i_formulas       = $data['formulas'];

        $postage = $this->findPostage($i_formulas, $i_customer_id, $i_transport_date);

        return $postage;
    }
}