<?php

    namespace App\Controllers;
    use App\Models\Homemodel;


    class Home extends BaseController
    {
        public function __construct() {
            $this->homemodel=new Homemodel();
            $this->input = \Config\Services::request();
        }
        public function index()
        {
            //return view('welcome_message');
        }

        public function add_access(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->add_access($data);
            }else{
                $result= $this->homemodel->add_access($res);
            }
            echo json_encode($result);
        }
        public function savespecialization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->savespecialization($data);
            }else{
                $result=$this->homemodel->savespecialization($res);
            } 
            echo json_encode($result);
        }
        public function savesymptoms(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->savesymptoms($data);
            }else{
                $result=$this->homemodel->savesymptoms($res);
            }
            echo json_encode($result);
        }

        public function add_employee_specialization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_employee_specialization($data);
            }else{
                $result=$this->homemodel->add_employee_specialization($res);
            }
            echo json_encode($result);
        }
        public function get_employee_specialization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->get_employee_specialization($data);
            }else{
                $result=$this->homemodel->get_employee_specialization($res);
            }
            echo json_encode($result);
        }
        public function add_bank_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            // var_dump($data);
            if(isset($data)){
                $result=$this->homemodel->add_bank_details($data);
            }else{
                $result=$this->homemodel->add_bank_details($res);
            }
            echo json_encode($result);
        }
        public function get_bank_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->get_bank_details($data);
            }else{
                $result=$this->homemodel->get_bank_details($res);
            }
            echo json_encode($result);
        }
        public function user_doctor_feedback(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->user_doctor_feedback($data);
            }else{
                $result=$this->homemodel->user_doctor_feedback($res);
            }
            echo json_encode($result);
        }
        public function get_user_doctor_feedback(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->get_user_doctor_feedback($data);
            }else{
                $result=$this->homemodel->get_user_doctor_feedback($res);
            }
            echo json_encode($result);
        }
        public function save_country(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
            // var_dump($data);
                $result=$this->homemodel->save_country($data);
            }else{
                $result=$this->homemodel->save_country($res);
            }
            echo json_encode($result);
        }
        public function get_all_country(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->get_all_country($data);
            }else{
                $result=$this->homemodel->get_all_country($res);
            }
            echo json_encode($result);
        }
        public function currency(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->currency($data);
            }else{
                $result=$this->homemodel->currency($res);
            }
            echo json_encode($result);
        }
        public function get_all_currency(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->get_currency($data);
            }else{
                $result=$this->homemodel->get_currency($res);
            }
            echo json_encode($result);
        }

        public function get_all_specialization(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->get_all_specialization($data);
            }else{
                $result=$this->homemodel->get_all_specialization($res);
            }
            echo json_encode($result);
        }
    }
?>