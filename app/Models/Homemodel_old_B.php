<?php

    /*
    * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
    * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
    */

    /**
    * Description of Homemodel
    *
    * @author fssvi
    */
    namespace App\Models;
    use CodeIgniter\Model;
    class Homemodel extends Model {
        //put your code here
        //public $db;
        public function __construct() {
            $this->db= \Config\Database::connect();
            $this->current_datetime=date('Y-m-d H:i:s');
            //$this->db=db_connect();
        }
        public function check_user_detail($user_id,$access_token){
        $user_builder= $this->db->table('user');
        $user_builder->select('*')->where('id',$user_id)->where('access_token',$access_token);
        $get_user_data=$user_builder->get()->getRowArray();
        if(!empty($get_user_data)){
           $response['status']=true;
           $response['statuscode']=200;
           $response['message']='success';
           $response['data']=$get_user_data;
        }else{
           $response['status']=false;
           $response['statuscode']=200;
           $response['message']='No data found';
           $response['data']=[];
        }
        return $response;
    }
        public function add_access($data=''){
            if(!empty($data)){
                $builder = $this->db->table('access');
                $insert_data['access_name']=$data['access_name'];
                $insert=$builder->insert($insert_data);
                if($insert){
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='success'; 
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Not inserted'; 
                }     
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='empty';
            }
            return $response;
        }      
        //  adding specialization
        public function savespecialization($data=''){ 
            if(!empty($data)){
                $builder = $this->db->table('specialization');
                $id='';
                if(isset($data['id'])){
                    $id=$data['id'];
                    unset($data['id']);
                }
                $data['created_datetime']=$this->current_datetime; 
                if ($id!='') {
                    $insert=$builder->where('id',$id)->update($data);
                }else{
                    $insert=$builder->insert($data);
                }
                if($insert){
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='success';
                    $get_all_specialization=$this->get_all_specialization();
                    // var_dump($get_all_specialization);
                    if(isset($get_all_specialization['data'])){
                        //$response['data']=$get_all_specialization['data'];
                    }else{
                        //$response['data']=array();
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Not inserted';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data empty';
            }
            return $response;
        }
        public function get_all_specialization($data=''){
            $get_specialization= $this->db->table('specialization')->select('*')->where('status',1)->get()->getResultArray();
            if(!empty($get_specialization)){
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='Success';
                $response['data']=$get_specialization;
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
                $response['data']=[];
            }
            return $response;
        }
        // public function savespecialization($value=array()){
        //     if(!empty($value)){
        //        $id='';
        //        if(isset($value['id'])){
        //         $id=$value['id'];
        //         unset($value['id']);
        //     }
        //         if(!empty($value)){
        //           if ($id!='') {
        //             return $this->db->where('id',$id)->update('specialization',$value);
        //           }else{
        //             return $this->db->insert('specialization',$value);
        //           }
        //         }
        //     }
        //      return false;
        // }                                  .....saveing symptoms
        public function savesymptoms($data){    
            if(!empty($data)){
                $builder = $this->db->table('symptom');
                $insert=$builder->insert($data);
                if($insert){
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='success';
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Not inserted';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data empty';
            }
            return $response;
        }
        // adding employee specialization.
        public function add_employee_specialization($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if(isset($data['specialization'])){
                    $specialization=$data['specialization'];
                }else{
                    $specialization='';
                }
                if($user_id!='' && $access_token!='' && $specialization!=''){
                    //check user
                    $check_user=$this->check_user_detail($user_id,$access_token);
                    //4
                    // var_dump($check_user);
                    $response['status']=true;
                    if($check_user['status']==true){
                        //insert
                        $specialization_data['updated_datetime']=$this->current_datetime;
                        $specialization_data['user_id']=$user_id;
                        $specialization_data['specialization']=$specialization;
                        $insert_specialization=$this->db->table('employee_specialization')->insert($specialization_data);
                        if($insert_specialization){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $get_specilization=$this->get_employee_specialization($data);
                            // var_dump($get_specilization);
                            $response['data']=$get_specilization['data'];
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Not inserted in db';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data empty';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data empty';
            }
            return $response;
        }
        public function get_employee_specialization($data=''){
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='No input data found';
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $get_employee_specialization= $this->db->table('employee_specialization')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($get_employee_specialization)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$get_employee_specialization;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No data found';
                            $response['data']=[];
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                        $response['data']=[];
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Access_token or User_id Missing';
                    $response['data']=[];
                }
            }
            return $response;
        }
        public function add_bank_details($data=''){
            $response=array('status'=>false,'statuscode'=>400,'message'=>'No input data');
            if($data!=''){
                // var_dump($data);
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if($user_id!='' && $access_token!=''){
                    $bank_account_holder_name='';
                    $bank_account_number='';
                    $bank_name='';
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
                        if(isset($data['bank_account_holder_name'])){
                            $bank_account_holder_name=$data['bank_account_holder_name'];
                        }
                        if(isset($data['bank_account_number'])){
                            $bank_account_number=$data['bank_account_number'];
                        }
                        if(isset($data['bank_name'])){
                            $bank_name=$data['bank_name'];
                        }
                        if($bank_account_holder_name!='' && $bank_account_number!='' && $bank_name!=''){
                            $insert_specialization=$this->db->table('bank_details')->insert($data);
                            if($insert_specialization){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $getinpt=array('user_id'=>$user_id,'access_token'=>$access_token);
                                $get_bank_details=$this->get_bank_details($getinpt);
                                $response['data']=$get_bank_details['data'];
                            }else{
                                $response['status']=false;
                                $response['statuscode']=200;
                                $response['message']='Not inserted in db';
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='bank_account_holder_name,bank_account_number,bank_name Cannot be null';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;  
                        $response['message']='Invalid User';  
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    if ($user_id=='' && $access_token=='') {
                        $response['message']='User ID & Access_token Missing';  
                    }else if($user_id==''){
                        $response['message']='User ID Missing';  
                    }else if($access_token==''){
                        $response['message']='Access_token Missing';  
                    }
                }
            }
            return $response;
        }
        public function get_bank_details($data=''){
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='No input data found';
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $get_bank_details= $this->db->table('bank_details')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($get_bank_details)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$get_bank_details;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No data found';
                            $response['data']=[];
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                        $response['data']=[];
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Access_token or User_id Missing';
                    $response['data']=[];
                }
            }
            return $response;
        }
        public function user_doctor_feedback($data=''){
            $response=array('status'=>false,'statuscode'=>400,'message'=>'No input data');
            if($data!=''){
                // var_dump($data);
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if($user_id!='' && $access_token!=''){
                    $feedback='';
                    $doctor_id='';
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
                        if(isset($data['feedback'])){
                            $feedback=$data['feedback'];
                        }
                        if(isset($data['doctor_id'])){
                            $doctor_id=$data['doctor_id'];
                        }
                        if($feedback!='' && $doctor_id!=''){
                            $insert_specialization=$this->db->table('user_doctor_feedback')->insert($data);
                            if($insert_specialization){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $getinpt=array('user_id'=>$user_id,'access_token'=>$access_token);
                                $get_user_doctor_feedback=$this->get_user_doctor_feedback($getinpt);
                                $response['data']=$get_user_doctor_feedback['data'];
                            }else{
                                $response['status']=false;
                                $response['statuscode']=200;
                                $response['message']='Not inserted in Database';
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='feedback Cannot be null';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;  
                        $response['message']='Invalid User';  
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    if ($user_id=='' && $access_token=='') {
                        $response['message']='User ID & Access_token Missing';  
                    }else if($user_id==''){
                        $response['message']='User ID Missing';  
                    }else if($access_token==''){
                        $response['message']='Access_token Missing';  
                    }
                }
            }
            return $response;
        }
        public function get_user_doctor_feedback($data=''){
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='no input data found';
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $get_user_doctor_feedback= $this->db->table('user_doctor_feedback')->select('*')->where('user_id',$user_id)->get()->getResultArray();
                        if(!empty($get_user_doctor_feedback)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$get_user_doctor_feedback;
                        }else{
                        $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No data found';
                            $response['data']=[];
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                        $response['data']=[];
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Access_token or User_id Missing';
                    $response['data']=[];
                }
            }
            return $response;
        }
        public function save_country($data=''){
            $response=array('status'=>false,'statuscode'=>400,'message'=>'No input data');
            if($data!=''){
                $country='';
                $country_code='';
                $phone_code='';
                if(isset($data['country'])){
                    $country=$data['country'];
                }
                if(isset($data['country_code'])){
                    $country_code=$data['country_code'];
                }
                if(isset($data['phone_code'])){
                    $phone_code=$data['phone_code'];
                }
                if($country!='' && $country_code!='' && $phone_code!=''){
                    $insert_specialization=$this->db->table('country')->insert($data);
                    if($insert_specialization){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';                         
                        $get_all_country=$this->get_all_country();
                        $response['data']=$get_all_country['data'];
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='country,country_code,phone_code Cannot be null';
                }
            }
            return $response;
        }
        public function get_all_country(){
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='no input data found';
            $get_all_country= $this->db->table('country')->select('*')->get()->getResultArray();
            if(!empty($get_all_country)){
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='Success';
                $response['data']=$get_all_country;
            }else{
            $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
                $response['data']=[];
            }
            return $response;
        }
        public function currency($data=''){
            $response=array('status'=>false,'statuscode'=>400,'message'=>'no input data found');
            if($data!=''){
                $country_id='';
                $currency='';
                $currency_code='';
                $symbol='';
                if(isset($data['country_id'])){
                    $country_id=$data['country_id'];
                }
                if(isset($data['currency'])){
                    $currency=$data['currency'];
                }
                if(isset($data['currency_code'])){
                    $currency_code=$data['currency_code'];
                }
                if(isset($data['symbol'])){
                    $symbol=$data['symbol'];
                }
                // var_dump($country_id,$currency,$currency_code,$symbol);
                if($country_id!='' && $currency!='' && $currency_code!='' && $symbol!=''){
                    $insert_specialization=$this->db->table('currency')->insert($data);
                    if($insert_specialization){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';                         
                        $get_all_currency=$this->get_all_currency();
                        $response['data']=$get_all_currency['data'];
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='country_id currency currency_code symbol Cannot be null';
                }
            }
            return $response;
        }
        public function get_all_currency(){
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='no input data found';
            $get_all_currency= $this->db->table('currency')->select('*')->get()->getResultArray();
            if(!empty($get_all_currency)){
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='Success';
                $response['data']=$get_all_currency;
            }else{
            $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
                $response['data']=[];
            }
            return $response;
        }
    }
?>