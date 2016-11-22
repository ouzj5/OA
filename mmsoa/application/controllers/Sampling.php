<?php
/**
 * User: alcanderian
 */
header("Content-type: text/html; charset=utf-8");

require_once('PublicMethod.php');

Class Sampling extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Moa_user_model');
        $this->load->model('Moa_worker_model');
        $this->load->model('Moa_sampling_model');
        $this->load->model('Moa_school_term_model');
        $this->load->helper(array('form', 'url'));
        $this->load->library('session');
        $this->load->helper('cookie');
    }

    /**
     * 进入管理页面
     */
    public function index()
    {
        if (isset($_SESSION['user_id'])) {
            if (!isset($_SESSION['level'])) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }
            $this->load->view('view_sampling_list');
        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
        }
    }

    public function newTable()
    {
        if (isset($_SESSION['user_id'])) {
            // 检查权限
            if ($_SESSION['level'] != 1 && $_SESSION['level'] != 6) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }
            $today = date("Y-m-d H:i:s");
            $week = 0;
            if (isset($_POST['week'])) {
                if($_POST['week'] == -1) {
                    $term = $this->Moa_school_term_model->get_term($today);
                    $week = PublicMethod::get_week($term[0]->termbeginstamp, $today);
                    $week -= 1;
                } else {
                    $week = $_POST['week'];
                }
            } else {
                $term = $this->Moa_school_term_model->get_term($today);
                $week = PublicMethod::get_week($term[0]->termbeginstamp, $today);
            }

            $group_a = $this->Moa_worker_model->get_by_group(1);
            $group_b = $this->Moa_worker_model->get_by_group(2);

            $table_list = array();
            if (isset($group_a) && isset($group_b)) {
                $len_a = count($group_a);
                $len_b = count($group_b);

                for ($i = 0; $i < $len_a; $i++) {
                    $table_list[$i] = array(
                        "state" => 0, "timestamp" => $today,
                        "target_uid" => $group_a[$i]->uid,
                        "on_use" => 1, "week" => $week);
                }
                for ($j = 0; $j < $len_b; $j++) {
                    $table_list[$j + $len_a] = array(
                        "state" => 0, "timestamp" => $today,
                        "target_uid" => $group_b[$j]->uid,
                        "on_use" => 1, "week" => $week);
                }

                $res = $this->Moa_sampling_model->add_new_table($table_list);
                if ($res === false) {
                    echo json_encode(array("status" => FALSE, "msg" => "创建表单失败"));
                    return;
                } else {
                    echo json_encode(array("status" => TRUE, "msg" => "创建表单成功"));
                    return;
                }


            } else {
                echo json_encode(array("status" => FALSE, "msg" => "获取用户列表失败"));
                return;
            }

        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
            return;
        }
    }

    /**
     * 獲取抽查表的列表
     */
    public function getTableList()
    {
        if (isset($_SESSION['user_id'])) {
            // 检查权限
            if (!isset($_SESSION['level'])) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }

            $today = date("Y-m-d H:i:s");

            $tmp_stamp = array();
            $index = 0;
            $ret_list = array();

            do {
                $tmp_stamp = $this->Moa_sampling_model->get_by_date($today, 1, 1, 0);
                if ($tmp_stamp != false) {
                    $ret_list[$index]["timestamp"] = $tmp_stamp[0]->timestamp;
                    $term = $this->Moa_school_term_model->get_term($tmp_stamp[0]->timestamp);

                    $school_year = $term[0]->schoolyear;
                    $school_term = $term[0]->schoolterm;
                    $week = $tmp_stamp[0]->week;

                    $ret_list[$index]["title"] = $school_year . $school_term .
                        "第" . $week ."周";


                    $today = $tmp_stamp[0]->timestamp;
                    $index++;
                }
            } while ($tmp_stamp != false && !empty($tmp_stamp));
            if (!empty($ret_list)) {
                echo json_encode(array("status" => TRUE, "msg" => "获取抽查表单列表成功", "base_url" => base_url(),
                    "sample_list" => $ret_list));
                return;
            } else {
                echo json_encode(array("status" => false, "msg" => "获取抽查表单列表失败"));
                return;
            }


        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
            return;
        }
    }

    /**
     * 獲取一個抽查表
     */
    public function getTable()
    {
        if (isset($_SESSION['user_id'])) {
            // 检查权限
            if (!isset($_SESSION['level'])) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }

            if (!isset($_POST['timestring'])) {
                echo json_encode(array("status" => false, "msg" => "获取抽查表单失败，没有时间标签"));
                return;
            } else {
                $date = substr($_POST['timestring'], 0, 4)."-".substr($_POST['timestring'], 4, 2).
                    "-".substr($_POST['timestring'], 6, 2)." ".substr($_POST['timestring'], 8, 2).
                    ":".substr($_POST['timestring'], 10, 2).":".substr($_POST['timestring'], 12, 2);

                $sample_object_list = $this->Moa_sampling_model->get_table($date);
                $len = count($sample_object_list);

                $ret = array();
                for($i = 0;$i < $len; $i++) {
                    $ret[$i]['sid'] = $sample_object_list[$i]->sid;
                    $ret[$i]['state'] = $sample_object_list[$i]->state;
                    $ret[$i]['timestamp'] = $sample_object_list[$i]->timestamp;
                    $ret[$i]['week'] = $sample_object_list[$i]->week;

                    $ret[$i]['target_uid'] = $sample_object_list[$i]->target_uid;
                    $target_obj = $this->Moa_user_model->get($sample_object_list[$i]->target_uid);
                    $ret[$i]['target_name'] = $target_obj->name;

                    if($sample_object_list[$i]->target_time_point == NULL) {
                        $ret[$i]['target_time_point'] = NULL;
                    } else {
                        $ret[$i]['target_time_point'] = $sample_object_list[$i]->target_time_point;
                    }

                    if($sample_object_list[$i]->target_room == NULL) {
                        $ret[$i]['target_room'] = NULL;
                    } else {
                        $ret[$i]['target_room'] = $sample_object_list[$i]->target_room;
                    }

                    $wid = $this->Moa_worker_model->get_wid_by_uid($target_obj->uid);
                    $target_worker_obj = $this->Moa_worker_model->get($wid);
                    $ret[$i]['classroom'] = explode(",", $target_worker_obj->classroom);

                    if($sample_object_list[$i]->operator_uid == NULL) {
                        $ret[$i]['operator_uid'] = NULL;
                        $ret[$i]['operator_name'] = "";
                    } else {
                        $ret[$i]['operator_uid'] = $sample_object_list[$i]->operator_uid;
                        $operator_obj = $this->Moa_user_model->get($sample_object_list[$i]->operator_uid);
                        $ret[$i]['operator_name'] = $operator_obj->name;
                    }

                    if($sample_object_list[$i]->problem == NULL) {
                        $ret[$i]['problem'] = "";
                    } else {
                        $ret[$i]['problem'] = $sample_object_list[$i]->problem;
                    }

                }

                if (!empty($ret)) {
                    echo json_encode(array("status" => TRUE, "msg" => "获取抽查表单成功", "base_url" => base_url(),
                        "sample_table" => $ret));
                    return;
                } else {
                    echo json_encode(array("status" => false, "msg" => "获取抽查表单失败"));
                    return;
                }
            }


        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
            return;
        }
    }

    public function showTable($timestring) {
        if (isset($_SESSION['user_id'])) {
            // 检查权限
            if (!isset($_SESSION['level'])) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }
            $date = substr($timestring, 0, 4)."-".substr($timestring, 4, 2).
                "-".substr($timestring, 6, 2)." ".substr($timestring, 8, 2).
                ":".substr($timestring, 10, 2).":".substr($timestring, 12, 2);

            $term = $this->Moa_school_term_model->get_term($date);

            $week = PublicMethod::get_week($term[0]->termbeginstamp, $date);

            $school_year = $term[0]->schoolyear;
            $school_term = $term[0]->schoolterm;

            $title = $school_year . $school_term .
                "第" . $week ."周";

            $this->load->view('view_sampling_table', array('data' => $timestring, 'title' => $title));

        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
            return;
        }
    }

    /**
     * 偽刪除一個抽查表
     */
    public function deleteTable()
    {
        if (isset($_SESSION['user_id'])) {
            // 检查权限
            if ($_SESSION['level'] != 1 && $_SESSION['level'] != 6) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }


            if (!isset($_POST['timestamp'])) {
                echo json_encode(array("status" => false, "msg" => "删除抽查表单失败，没有时间标签"));
                return;
            } else {
                $ret = $this->Moa_sampling_model->delete_table($_POST['timestamp']);
                if ($ret != false) {
                    echo json_encode(array("status" => TRUE, "msg" => "删除抽查表单成功"));
                    return;
                } else {
                    echo json_encode(array("status" => false, "msg" => "删除抽查表单失败"));
                    return;
                }
            }

        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
            return;
        }
    }

    /**
     * 更新一條抽查記錄
     */
    public function upDateRecord()
    {
        if (isset($_SESSION['user_id'])) {
            // 检查权限
            if ($_SESSION['level'] != 1 && $_SESSION['level'] != 6) {
                // 提示权限不够
                PublicMethod::permissionDenied();
                return;
            }

            $record = array();
            if($_POST['target_time_point'] != 'NULL')
                $record['target_time_point'] = $_POST['target_time_point'];

            if($_POST['target_room'] != 'NULL')
                $record['target_room'] = $_POST['target_room'];

            if($_POST['state'] != 0)
                $record['state'] = $_POST['state'];

            $record['problem'] = $_POST['problem'];
            $record['operator_uid'] = $_SESSION['user_id'];

            $ret = $this->Moa_sampling_model->update_a_record($record, $_POST['sid']);

            if($ret == false) {
                echo json_encode(array("status" => false, "msg" => "更新失败"));
                return;
            } else {
                echo json_encode(array("status" => true, "msg" => "更新成功"));
                return;
            }

        } else {
            // 未登录的用户请先登录
            PublicMethod::requireLogin();
            return;
        }
    }
}