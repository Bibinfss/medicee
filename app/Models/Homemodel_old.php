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
include APPPATH.'ThirdParty/twilio/src/Twilio/autoload.php';
use Twilio\Rest\Client; 
class Homemodel extends Model {
    //put your code here
    //public $db;
    public function __construct() {
        $this->db= \Config\Database::connect();
        $this->current_datetime=date('Y-m-d H:i:s');
        //$this->db=db_connect();
    }
    
    public function add_access($data=''){
        //var_dump($data['user_id']);
        if(!empty($data)){
            if(isset($data['id'])){
                $id=$data['id'];
            }else{
                $id='';
            }
            $builder = $this->db->table('access');
            $insert_data['access_name']=$data['access_name'];
//            $insert_data=[
//                ['access_name'=>'1'],
//                ['access_name'=>'2']
//            ];
            //$insert=$builder->insertBatch($insert_data);
            if($id!=''){
                $save=$builder->where('id',$id)->update($insert_data);
            }else{
                $save=$builder->insert($insert_data);
            }
            //var_dump($insert);
            if($save){
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
    
    public function register($data){
        if(!empty($data)){
            //check user mobile number already exist or not
            $user_builder = $this->db->table('user');
            $user_builder->select('*')->where('country_code',$data['country_code'])->where('mobile',$data['mobile']);
            $check_mobile_number= $user_builder->get()->getRowArray();
            //$user_builder->where('country_code',$data['country_code']);
            //$check_mobile_number = $user_builder->get();
            //var_dump($check_mobile_number);
            //var_dump(bin2hex(random_bytes(16)));
            //var_dump($this->generate_otp());
            if(!empty($check_mobile_number)){
                //user found
                //var_dump('already exist');
                
                //check access_id
                //var_dump($check_mobile_number['access_id'],$data['access_id']);
                if($check_mobile_number['access_id']==$data['access_id']){
                    //var_dump('sss');
                    //do update with the existing account
                    //var_dump('user_id',$check_mobile_number['id']);
                    //$update_data['firebase_token']=$data['firebase_token'];
                    $update_data['device_type']=$data['device_type'];
                    $update_data['otp']= $this->generate_otp();
                    $update_data['app_signature_id']=$data['app_signature_id'];
                    $update_builder= $this->db->table('user');
                    $update_builder->where('id',$check_mobile_number['id']);
                    $update= $update_builder->update($update_data);
                    //var_dump($update);
                    if($update){
                        //send otp
                        $phone_mobile='+'.$data['country_code'].$data['mobile'];
                        $send_otp= $this->send_twilo_otp($phone_mobile,$update_data['otp'],$update_data['app_signature_id']);
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='success';
                        //$response['otp']= (string) $this->generate_otp();
                        $response_data=array('otp'=>(string) $update_data['otp'],
                                             'user_id'=>$check_mobile_number['id'],
                                             'access_token'=>$check_mobile_number['access_token']);
                        $response['data']=$response_data;
                        
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='not updated in db';
                        $response['data']= [];
                    }
                }else{
                    //get access type using access id
                    $get_access_name= $this->get_access_name($check_mobile_number['access_id']);
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='You have already registered with '.$get_access_name.' type';
                    $response['data']= [];
                }
            }else{
                //no user found
                //$get_country_currency_id=$this->get_country_id_by_country_code($data['country_code']);
                $user_id= $this->set_user_id();
                $insert_user_data['user_id']=$user_id;
                $insert_user_data['access_token']=bin2hex(random_bytes(16));
                //$insert_user_data['firebase_token']=$data['firebase_token'];
                $insert_user_data['created_datetime']=$this->current_datetime;
                $insert_user_data['access_id']=$data['access_id'];
                $insert_user_data['country_id']= $this->get_country_id_by_country_code($data['country_code']);
                //$insert_user_data['currency_id']= $get_country_currency_id['currency_id'];
                $insert_user_data['country_code']=$data['country_code'];
                $insert_user_data['mobile']=$data['mobile'];
                $insert_user_data['otp']= $this->generate_otp();
                $insert_user_data['device_type']=$data['device_type'];
                $insert_user_data['app_signature_id']=$data['app_signature_id'];
                $builder = $this->db->table('user');
                $insert= $builder->insert($insert_user_data);
                if($insert){
                    //send otp
                    $phone_mobile='+'.$insert_user_data['country_code'].$insert_user_data['mobile'];
                    $send_otp= $this->send_twilo_otp($phone_mobile,$insert_user_data['otp'],$insert_user_data['app_signature_id']);
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='success';
                    //var_dump($this->db->insertID());
                    $response_data=array('otp'=>(string) $insert_user_data['otp'],
                                             'user_id'=>$this->db->insertID(),
                                             'access_token'=>$insert_user_data['access_token']);
                    $response['data']=$response_data;
                }else{
                    $response['status']=true;
                    $response['statuscode']=400;
                    $response['message']='not inserted in db';
                    $response['data']= [];
                }
            }
            
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
            $response['data']= [];
        }
        return $response;
    }
    
    public function set_user_id(){
        //get last id from user table and set id sequence
        //SELECT count(*) FROM `user` where year(created_datetime)='2022' and month(created_datetime)='8'
        $current_year=date('Y');
        $current_month=date('m');
        $builder= $this->db->table('user');
        $builder->select('count(*) as count')->where('year(created_datetime)',$current_year)->where('month(created_datetime)',$current_month);
        $last_id= $builder->get()->getRowArray();
        //var_dump('last_id, ',$last_id['count']);
        $id=$last_id['count']+1;
        //call short form to get app letters
        $sequence_letter= $this->get_short_form();
        $user_id=$sequence_letter.'_'.$current_year.$current_month.sprintf('%04d',$id);
        return $user_id;
    }
    
    public function generate_otp(){
        $otp = rand(100000, 999999);
        return $otp;
    }
    
    public function send_twilo_otp($phone_number='', $otp='', $app_signature_id=''){
        $sid    = "AC08cbb470dabf561de9c9946832dfa5a1"; 
        $token  = "d361a9be6b7ef9201f545e481257ea5f"; 
        $twilio = new Client($sid, $token); 
        $otp_text='Your OTP code is '.$otp.' '.$app_signature_id;
        $message = $twilio->messages 
                          ->create($phone_number, // to 
                                   array(  
                                       "messagingServiceSid" => "MG9b6f8604879d98862740d75c02aecbd3",      
                                       "body" => $otp_text
                                   ) 
                          ); 
                          //var_dump($message);
        //return json_decode(json_encode($message),true);
    }
    
    public function get_access_name($id=''){
        $user_access_builder = $this->db->table('access');
        $user_access_builder->select('*')->where('id',$id);
        $get_access_type= $user_access_builder->get()->getRowArray();
        return $get_access_type['access_name'];
    }
    
    public function get_country_id_by_country_code($country_code=''){
        $country_builder= $this->db->table('country');
        $country_builder->select('*')->where('phone_code',$country_code);
        $get_country_details=$country_builder->get()->getRowArray();
        if(!empty($get_country_details)){
            return $get_country_details['id'];
        }else{
            return 0;
        }
        //SELECT t1.id as country_id, t2.id as currency_id FROM `country` t1 INNER JOIN `currency` t2 ON t1.id=t2.country_id where t1.country_code='91';
//        $get_country_and_currency_id= $this->db->query("SELECT t1.id as country_id, t2.id as currency_id FROM `country` t1 INNER JOIN `currency` t2 ON t1.id=t2.country_id where t1.country_code='".$country_code."'")->getRowArray();
//        if(!empty($get_country_and_currency_id)){
//            //var_dump($get_country_and_currency_id['currency_id']);
//            $id_S=array('country_id'=>$get_country_and_currency_id['country_id'],
//                        'currency_id'=>$get_country_and_currency_id['currency_id']);
//        }else{
//            $id_S=array('country_id'=>0,
//                        'currency_id'=>0);
//        }
//        var_dump($id_S);
//        return $id_S;
    }
    
    public function get_short_form(){
        $short_form_builder= $this->db->table('short_form');
        $short_form_builder->select('*')->where('status',1)->orderBy('id', 'DESC');
        $get_short_details=$short_form_builder->get()->getRowArray();
        if(!empty($get_short_details)){
            return $get_short_details['name'];
        }else{
            return 'FSS';
        }
    }
    
    public function verify_otp($data=''){
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
            
            if(isset($data['otp'])){
                $otp=$data['otp'];
            }else{
                $otp='';
            }
            //var_dump($data['user_id']);
            if($user_id!='' && $access_token!='' && $otp!=''){
                //var_dump('test');
                $otp_builder= $this->db->table('user');
                $otp_builder->select('*')->where('id',$user_id)->where('otp',$otp);
                $get_otp_details=$otp_builder->get()->getRowArray();
                if(!empty($get_otp_details)){
                    //update otp status
                    
                    $update_data['otp_status']=1;
                    $update_otp_builder= $this->db->table('user');
                    $update_otp_builder->where('id',$user_id);
                    $update=$update_otp_builder->update($update_data);
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='success';
                    //get login status
                    $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'login_status'=>$get_otp_details['login_status']);
                    //var_dump('sss',$response);
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='Wrong OTP'; 
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
                $response['data']=[];
            }
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
            $response['data']=[];
        }
        return $response;
    }
    
    public function fill_profile($data=''){
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
            if(isset($data['username'])){
                $username=$data['username'];
            }else{
                $username='';
            }
            if(isset($data['email'])){
                $email=$data['email'];
            }else{
                $email='';
            }
            if(isset($data['latitude'])){
                $latitude=$data['latitude'];
            }else{
                $latitude='';
            }
            if(isset($data['longitude'])){
                $longitude=$data['longitude'];
            }else{
                $longitude='';
            }
            if(isset($data['ip_address'])){
                $ip_address=$data['ip_address'];
            }else{
                $ip_address='';
            }
            
            if(isset($data['gender'])){
                $gender=$data['gender'];
            }else{
                $gender='';
            }
            
            if($user_id!="" && $access_token!="" && $username!="" && $email!=""){
                //check user_id and access_token is valid or not
                $check_user= $this->check_user_detail($user_id,$access_token);
                //var_dump($check_user['data']['device_type']);
                if($check_user['status']==true){
                    //update user profile data
                    $update_user_profile_builder= $this->db->table('user');
                    $update_user_profile_builder->where('id',$user_id);
                    $update_data['username']=$username;
                    $update_data['email']=$email;
                    $update_data['latitude']=$latitude;
                    $update_data['longitude']=$longitude;
                    $user_type= $this->get_access_name($check_user['data']['access_id']);
                    //var_dump($check_user['data']['access_id']);
                    if($user_type=='patient'){
                        $update_data['login_status']=1;
                    }
                    $update_profile_data=$update_user_profile_builder->update($update_data);
                    if($update_profile_data){
                        //insert login details
                        $login_history_data['user_id']=$user_id;
                        $login_history_data['device_type']=$check_user['data']['device_type'];
                        $login_history_data['ip_address']=$ip_address;
                        $login_history_data['login_datetime']= $this->current_datetime;
                        $login_history_data['latitude']=$latitude;
                        $login_history_data['longitude']=$longitude;
                        //get country by using latitude and longitude -- google cloud api
                        $login_history_data['country']='';
                        $login_history_builder= $this->db->table('login_history');
                        $insert_login_history=$login_history_builder->insert($login_history_data);
                        if($insert_login_history){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success';
                            //insert in employee_basic_details table
                            $employee_basic_data['user_id']=$user_id;
                            $employee_basic_data['access_id']=$check_user['data']['access_id'];
                            $employee_basic_data['updated_datetime']= $this->current_datetime;
                            $employee_basic_data['updated_by']= $user_id;
                            $employee_basic_data['gender']= $gender;
                            $employee_basic_data['email']= $email;
                            $employee_basic_data['profile_pic']= $data['profile_pic_path'];
                            $employee_table_builder= $this->db->table('employee_basic_details');
                            //check entry exist in employee_basic_details table
                            $check_employee_data_exist= $this->db->table('employee_basic_details')->where('user_id',$user_id)->get()->getRowArray();
                            //var_dump($check_employee_data_exist);
                            if(empty($check_employee_data_exist)){
                                $insert_into_employee_details=$employee_table_builder->insert($employee_basic_data);
                            }else{
                                //var_dump('already exist');
//                                $update_basic_data['updated_datetime']= $this->current_datetime;
//                                $update_basic_data['updated_by']=$user_id;
//                                
//                                $update_employee_details= $this->db->table('employee_basic_details');
                            }
                            //$user_type= $this->get_access_name($check_user['data']['access_id']);
                            $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'user_type'=>$user_type,'username'=>$username,'email'=>$email,'gender'=>$gender);
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not inserted in db';  
                            $response['data']=[];
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Not updated in db';  
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
                $response['message']='Data is empty'; 
                $response['data']=[];
            }
        }else{
           $response['status']=false;
           $response['statuscode']=200;
           $response['message']='No data found'; 
           $response['data']=[];
        }
        
        return $response;
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
    
    public function add_employee_experience($data=''){
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
            if(isset($data['hospital'])){
                $hospital=$data['hospital'];
            }else{
                $hospital='';
            }
            if(isset($data['years'])){
                $years=$data['years'];
            }else{
                $years='';
            }
            if(isset($data['position'])){
                $position=$data['position'];
            }else{
                $position='';
            }
            if($user_id!="" && $access_token!="" && $hospital!="" && $years!="" && $position!=""){
                //check user data is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user['status']){
                    //save to the db
                    $experience_data['user_id']=$user_id;
                    $experience_data['updated_datetime']= $this->current_datetime;
                    $experience_data['hospital']=$hospital;
                    $experience_data['years']=$years;
                    $experience_data['position']=$position;
                    $save_data= $this->db->table('employee_experience')->insert($experience_data);
                    if($save_data){
                        $last_insert_id= $this->db->insertID();
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                        $response['last_inserted_id']=$last_insert_id;
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No user data found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data is empty';
            }
            
        }else{
           $response['status']=false;
           $response['statuscode']=200;
           $response['message']='No data found';
        }
        return $response;
    }
    
    public function update_experience_document($id='',$data='') {
        $update= $this->db->table('employee_experience')->where('id',$id)->update($data);
        if($update){
            $response['status']=true;
            $response['statuscode']=200;
            $response['message']='Success';
        }else{
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='Not updated in db';
        }
        return $response;
    }
    
    public function add_employee_qualification($data=''){
        //var_dump($data);
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
            if(isset($data['access_token'])){
                $access_token=$data['access_token'];
            }else{
                $access_token='';
            }
            if(isset($data['qualification'])){
                $qualification=$data['qualification'];
            }else{
                $qualification='';
            }
            if(isset($data['start_year'])){
                $start_year=$data['start_year'];
            }else{
                $start_year='';
            }
            if(isset($data['end_year'])){
                $end_year=$data['end_year'];
            }else{
                $end_year='';
            }
            if($user_id!='' && $access_token!='' && $qualification!='' && $start_year!='' && $end_year!=''){
                //check user data is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user){
                    //save to db
                    $qualification_data['user_id']=$user_id;
                    $qualification_data['updated_datetime']= $this->current_datetime;
                    $qualification_data['qualification']=$qualification;
                    $qualification_data['start_year']=$start_year;
                    $qualification_data['end_year']=$end_year;
                    $save_qualification=$this->db->table('employee_qualification')->insert($qualification_data);
                    if($save_qualification){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                        $response['last_inserted_id']= $this->db->insertID();
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No user data found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data is empty';
            }
            
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
        }
        return $response;
    }
    
    public function update_qualification_document($id='',$data='') {
        $update= $this->db->table('employee_qualification')->where('id',$id)->update($data);
        if($update){
            $response['status']=true;
            $response['statuscode']=200;
            $response['message']='Success';
        }else{
            $response['status']=false;
            $response['statuscode']=400;
            $response['message']='Not updated in db';
        }
        return $response;
    }
    
    public function get_employee_experience($data='') {
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
            //var_dump(base_url());
            if($user_id!='' && $access_token!=''){
                //check user is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user['status']){
                    $get_experience_data= $this->db->table('employee_experience')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                    if(!empty($get_experience_data)){
                        foreach($get_experience_data as $key => $value){
                            if($get_experience_data[$key]['experience_documents']!=''){
                                $get_experience_data[$key]['experience_documents']= base_url().'/'.$get_experience_data[$key]['experience_documents'];
                            }
                        }
                        //var_dump($get_experience_data);
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                        $response['data']=$get_experience_data;
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No experience data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No user data found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data is empty';
            }
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
        }
        return $response;
    }
    
    public function get_employee_qualification($data='') {
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
                    $get_qualification_data= $this->db->table('employee_qualification')->select('*')->where('user_id',$user_id)->where('status',1)->orderBy('id asc')->get()->getResultArray();
                    if(!empty($get_qualification_data)){
                        //var_dump($get_experience_data);
                        
                        foreach ($get_qualification_data as $key => $value){
                            if($get_qualification_data[$key]['upload_documents']!=''){
                                $get_qualification_data[$key]['upload_documents']= base_url().'/'.$get_qualification_data[$key]['upload_documents'];
                            }
                        }
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                        $response['data']=$get_qualification_data;
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No experience data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No user data found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data is empty';
            }
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
        }
        return $response;
    }
    
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
                    $response['statuscode']=400;
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
                $response['statuscode']=200;
                $response['message']='Access_token or User_id Missing';
                $response['data']=[];
            }
        }
        return $response;
    } 
    public function add_doctor_consulting_fee($data='') {
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
            if(isset($data['consulting_fee'])){
                $consulting_fee=$data['consulting_fee'];
            }else{
                $consulting_fee='';
            }
            
            if($user_id!='' && $access_token!=''){
                //check user is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user['status']){
                    //var_dump($check_user['data']['currency_id']);
                    //update consulting fee
                    //$consulting_fee_data['']=
                    $consulting_fee_data['user_id']=$user_id;
                    $consulting_fee_data['datetime']= $this->current_datetime;
                    $consulting_fee_data['currency_id']=$check_user['data']['currency_id'];
                    $consulting_fee_data['consulting_fee']=$consulting_fee;
                    $save_data= $this->db->table('employee_consulting_fee')->insert($consulting_fee_data);
                    if($save_data){
                        //update account setup status or login_status = 1
                        $update_login_status_data['login_status']=1;
                        $update_login_status=$this->db->table('user')->where('id',$user_id)->update($update_login_status_data);
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No user data found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data is empty';
            }
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
        }
        return $response;
    }
    
    public function add_branch_employee_consulting_fee($data=''){
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
            if(isset($data['consulting_fee'])){
                $consulting_fee=$data['consulting_fee'];
            }else{
                $consulting_fee='';
            }
            if(isset($data['consulting_branch_id'])){
                $consulting_branch_id=$data['consulting_branch_id'];
            }else{
                $consulting_branch_id='';
            }
            
            if($user_id!='' && $access_token!='' && $consulting_branch_id!='' && $consulting_fee!=''){
                //check user is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user['status']){
                    //save data
                    $consulting_fee_data['datetime']= $this->current_datetime;
                    $consulting_fee_data['user_id']= $user_id;
                    $consulting_fee_data['consulting_branch_id']= $consulting_branch_id;
                    $consulting_fee_data['consulting_fee']= $consulting_fee;
                    $save_data= $this->db->table('employee_consulting_fee')->insert($consulting_fee_data);
                    if($save_data){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
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
                $response['message']='Data is empty';
            }
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found';
        }
        return $response;
    }
    
    public function add_doctor_slot($data='') {
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
//            if(isset($data['type'])){
//                $type=$data['type'];
//            }else{
//                $type='';
//            }
            if(isset($data['consulting_time'])){
                $consulting_time=$data['consulting_time'];
            }else{
                $consulting_time='';
            }
            if(isset($data['consulting_type'])){
                $consulting_type=$data['consulting_type'];
            }else{
                $consulting_type='';
            }
            if(isset($data['start_datetime'])){
                $start_datetime=$data['start_datetime'];
            }else{
                $start_datetime='';
            }
            if(isset($data['end_datetime'])){
                $end_datetime=$data['end_datetime'];
            }else{
                $end_datetime='';
            }
            $type='doctor';
            if($user_id!='' && $access_token!='' && $type!='' && $consulting_time!='' && $consulting_type!='' && $start_datetime!='' && $end_datetime!=''){
                //check user is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user['status']){
                    //get doctor consulting fee
                    $consulting_fee= $this->get_doctor_last_consulting_fee($user_id);
                    //var_dump($consulting_fee);
                    $consulting_master_data['created_datetime']= $this->current_datetime;
                    $consulting_master_data['user_id']= $user_id;
                    $consulting_master_data['type']= $type;
                    $consulting_master_data['consulting_time']= $consulting_time;
                    $consulting_master_data['consulting_type']= $consulting_type;
                    $consulting_master_data['start_datetime']= $start_datetime;
                    $consulting_master_data['end_datetime']= $end_datetime;
                    //check start_datetime already exist in db - doctor_slot_master table
                    //$check_slot_master= $this->db->table('doctor_slot_master')->select('*')->where('start_datetime>=',$start_datetime)->where('end_datetime<=',$end_datetime)->where('user_id',$user_id)->where('status',1)->get()->getRowArray();
                    //SELECT * FROM `doctor_slot_master` where (start_datetime>='2022-11-24 00:00:00' and end_datetime<='2022-11-24 23:59:59');
                    //get dates from start_datetime and end_datetime of user input
                    $get_start_date= explode(" ", $start_datetime);
                    $get_end_date= explode(" ", $end_datetime);
                    $set_start_datetime=$get_start_date[0].' '.'00:00:00';
                    $set_end_datetime=$get_end_date[0].' '.'23:59:59';
                    //var_dump($set_start_datetime,$set_end_datetime);
                    $get_master_data= $this->db->table('doctor_slot_master')->select('*')->where('start_datetime>=',$set_start_datetime)->where('end_datetime<=',$set_end_datetime)->get()->getResultArray();
                    if(!empty($get_master_data)){
                        //var_dump(count($get_master_data),$get_master_data);
                        $master_data_array=array();
                        $already_exist_slot_status=false;
                        foreach($get_master_data as $master_data){
                            //var_dump('ss');
                            //var_dump($master_data['start_datetime'],$master_data['end_datetime']);
                            //var_dump($master_data['start_datetime']);
                            $get_split_datetime=$this->split_datetime_from_two_datetime($master_data['start_datetime'],$master_data['end_datetime']);
                            //var_dump($get_split_datetime);
                            //remove comma from the get split datetime
                            $remove_comma= explode(',', $get_split_datetime);
                            //var_dump($remove_comma);
                            foreach($remove_comma as $splited_dates){
                                array_push($master_data_array,$splited_dates);
                            }
                            
                        }
                         //var_dump($master_data_array);
                         //check start and end time exist in the array
                         if(in_array($start_datetime, $master_data_array) || in_array($end_datetime,$master_data_array)){
                            //var_dump('ssss');
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='You book in this slot. Already registered';   
                         }else{
                            //var_dump('nnnn');
                            $save_master_data= $this->db->table('doctor_slot_master')->insert($consulting_master_data);
                            if($save_master_data){
                                $last_inserted_id= $this->db->insertID();
                                $slots = $this->getTimeSlot($consulting_time, $start_datetime, $end_datetime,5);
                                //var_dump($slots);
                                if(!empty($slots)){
                                    $doctor_slots=array();
                                    foreach ($slots as $slot){
                                        $doctor_slot_data['master_id']=$last_inserted_id;
                                        $doctor_slot_data['updated_datetime']= $this->current_datetime;
                                        $doctor_slot_data['consulting_time']= $consulting_time;
                                        $doctor_slot_data['consulting_fee']= $consulting_fee;
                                        $doctor_slot_data['currency_id']= $check_user['data']['currency_id'];
                                        $doctor_slot_data['consulting_type']= $consulting_type;
                                        $doctor_slot_data['start_datetime']= $slot['slot_start_time'];
                                        $doctor_slot_data['end_datetime']= $slot['slot_end_time'];
                                        array_push($doctor_slots,$doctor_slot_data);
                                        //var_dump($slot['slot_start_time']);
                                    }
                                    //var_dump($doctor_slots);
                                    //insert all the datas
                                    $save_slot_data= $this->db->table('doctor_slot')->insertBatch($doctor_slots);
                                    //var_dump($save_slot_data);
                                    if($save_slot_data){
                                        $response['status']=true;
                                        $response['statuscode']=200;
                                        $response['message']='Success';
                                    }else{
                                        $response['status']=false;
                                        $response['statuscode']=400;
                                        $response['message']='Not inserted in db';
                                    }
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=200;
                                    $response['message']='Slot data is empty';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='Not inserted in db';
                            }
                         }
                         //var_dump($already_exist_slot_status);
                    }else{
                        //insert new entry
                        //var_dump('else');
                        $save_master_data= $this->db->table('doctor_slot_master')->insert($consulting_master_data);
                        if($save_master_data){
                            $last_inserted_id= $this->db->insertID();
                            $slots = $this->getTimeSlot($consulting_time, $start_datetime, $end_datetime,5);
                            //var_dump($slots);
                            if(!empty($slots)){
                                $doctor_slots=array();
                                foreach ($slots as $slot){
                                    $doctor_slot_data['master_id']=$last_inserted_id;
                                    $doctor_slot_data['updated_datetime']= $this->current_datetime;
                                    $doctor_slot_data['consulting_time']= $consulting_time;
                                    $doctor_slot_data['consulting_fee']= $consulting_fee;
                                    $doctor_slot_data['currency_id']= $check_user['data']['currency_id'];
                                    $doctor_slot_data['consulting_type']= $consulting_type;
                                    $doctor_slot_data['start_datetime']= $slot['slot_start_time'];
                                    $doctor_slot_data['end_datetime']= $slot['slot_end_time'];
                                    array_push($doctor_slots,$doctor_slot_data);
                                    //var_dump($slot['slot_start_time']);
                                }
                                //var_dump($doctor_slots);
                                //insert all the datas
                                $save_slot_data= $this->db->table('doctor_slot')->insertBatch($doctor_slots);
                                //var_dump($save_slot_data);
                                if($save_slot_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Success';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not inserted in db';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=200;
                                $response['message']='Slot data is empty';
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not inserted in db';
                        }
                    }
                    //$get_master_slot_data= $this->db->table('doctor_slot_master')->select('*')->where();
                    //exit();
                    
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No user found'; 
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data is empty';   
            }
        }else{
            $response['status']=false;
            $response['statuscode']=200;
            $response['message']='No data found'; 
        }
        return $response;
    }
    
    public function get_doctor_last_consulting_fee($doctor_id=''){
        $get_consulting_fee= $this->db->table('employee_consulting_fee')->select('consulting_fee')->where('user_id',$doctor_id)->where('status',1)->get()->getRowArray();
        if(!empty($get_consulting_fee)){
            $consulting_fee=$get_consulting_fee['consulting_fee'];
        }else{
            $consulting_fee=0;
        }
        return $consulting_fee;
    }
    
    public function get_access_data(){
        $get_data= $this->db->table('access')->select('*')->where('status',1)->get()->getResultArray();
        if(!empty($get_data)){
            $response['status']=200;
            $response['statuscode']=true;
            $response['message']='Success';
            $response['data']=$get_data;
        }else{
            $response['status']=200;
            $response['statuscode']=false;
            $response['message']='No data found';
            $response['data']=[];
        }
        return $response;
    }
    
    public function getTimeSlot($interval, $start_time, $end_time,$break){
        $start = new \DateTime($start_time);
        $end = new \DateTime($end_time);
        $startTime = $start->format('Y-m-d H:i:s');
        $endTime = $end->format('Y-m-d H:i:s');
        $i=0;
        $time = [];
        while(strtotime($startTime) <= strtotime($endTime)){
//            $start = $startTime;
            $start = $startTime;
            //var_dump($start);
            $end = date('Y-m-d H:i:s',strtotime('+'.$interval.' minutes',strtotime($startTime)));
            //including break time
            $sum_with_break=$interval+$break;
            $startTime = date('Y-m-d H:i:s',strtotime('+'.$sum_with_break.' minutes',strtotime($startTime)));
            //$startTime = date('Y-m-d H:i:s',strtotime('+'.$interval.' minutes',strtotime($startTime)));
            //$startTime = date('Y-m-d H:i:s',strtotime('+'.$break.' minutes',strtotime($startTime)));
            $i++;
            if(strtotime($startTime) <= strtotime($endTime)){
                $time[$i]['slot_start_time'] = $start;
                $time[$i]['slot_end_time'] = $end;
            }
        }
        return $time;
    }
    
    public function split_datetime_from_two_datetime($start_datetime='',$end_datetime=''){
        //var_dump($start_datetime,$end_datetime);
        $end_datetime=new \DateTime($end_datetime);
        $end_datetime->modify('+1 minutes');
        $period = new \DatePeriod(
            new \DateTime($start_datetime),
            new \DateInterval('PT1M'),
            $end_datetime
       );
        //$date=array();
        $date='';
        foreach ($period as $key => $value) {
            //$value->format('Y-m-d')   
            //var_dump($key);
            //array_push($date,$value->format('Y-m-d H:i:s'));
            $date=$date.$value->format('Y-m-d H:i:s').',';
            
        }
        //var_dump($date);
        return rtrim($date, ',');
        //return $date;
    }
    
    public function update_admin_verification_accept_status($data=''){
        if(!empty($data)){
            if(isset($data['user_id'])){
                $user_id=$data['user_id'];
            }else{
                $user_id='';
            }
            if($user_id!=''){
                //update admin verification status = 1
                $update_data['admin_verification_status']=1;
                $update_data['admin_verification_datetime']= $this->current_datetime;
                $update_admin_verification= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($update_data);
                if($update_admin_verification){
                    $response['status']=200;
                    $response['statuscode']=true;
                    $response['message']='Success';
                }else{
                    $response['status']=400;
                    $response['statuscode']=false;
                    $response['message']='Not updated in db';
                }
            }else{
                $response['status']=200;
                $response['statuscode']=false;
                $response['message']='Data found';
            }
        }else{
            $response['status']=200;
            $response['statuscode']=false;
            $response['message']='No data found';
        }
        
        return $response;
    }
    
    public function update_admin_verification_reject_status($data=''){
        if(!empty($data)){
            if(isset($data['user_id'])){
                $user_id=$data['user_id'];
            }else{
                $user_id='';
            }
            if(isset($data['rejection_reason'])){
                $rejection_reason=$data['rejection_reason'];
            }else{
                $rejection_reason='';
            }
            if($user_id!='' && $rejection_reason!=''){
                //update admin verification status = 1
                $update_data['admin_verification_status']=2;
                $update_data['admin_verification_datetime']= $this->current_datetime;
                $update_data['rejection_reason']= $rejection_reason;
                $update_admin_verification= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($update_data);
                if($update_admin_verification){
                    //insert into verification_rejection_reason
                    $insert_data['datetime']= $this->current_datetime;
                    $insert_data['user_id']= $user_id;
                    $insert_data['rejection_reason']= $rejection_reason;
                    $insert= $this->db->table('verification_rejection_reason')->insert($insert_data);
                    if($insert){
                        $response['status']=200;
                        $response['statuscode']=true;
                        $response['message']='Success';
                    }else{
                        $response['status']=400;
                        $response['statuscode']=false;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=400;
                    $response['statuscode']=false;
                    $response['message']='Not updated in db';
                }
            }else{
                $response['status']=200;
                $response['statuscode']=false;
                $response['message']='Data found';
            }
        }else{
            $response['status']=200;
            $response['statuscode']=false;
            $response['message']='No data found';
        }
        
        return $response;
    }
    
    public function get_doctor_slot_based_on_date($data=''){
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
            if(isset($data['date'])){
                $date=$data['date'];
            }else{
                $date='';
            }
            if($user_id!='' && $access_token!='' && $date!=''){
                //check user is valid
                $check_user= $this->check_user_detail($user_id, $access_token);
                if($check_user['status']){
                    //get slot data using date
                    //SELECT t1.id as master_id,t2.id,t2.consulting_type,t2.start_datetime, t2.end_datetime,t2.status FROM `doctor_slot_master` t1 INNER JOIN `doctor_slot` t2 on t1.id=t2.master_id where t1.start_datetime>='2022-11-24 00:00:00' and t1.end_datetime<='2022-11-24 23:59:59';
                    //SELECT * FROM `doctor_slot_master` where (start_datetime>='2022-11-24 00:00:00' and end_datetime<='2022-11-24 23:59:59');
                    //SELECT t1.id as master_id,t2.id,t2.consulting_type,t2.start_datetime, t2.end_datetime,t2.status FROM `doctor_slot_master` t1 INNER JOIN `doctor_slot` t2 on t1.id=t2.master_id where t1.start_datetime>='2022-11-24 00:00:00' and t1.end_datetime<='2022-11-24 23:59:59';
                    $start_datetime=$date.' 00:00:00';
                    $end_datetime=$date.' 23:59:59';
                    $get_slots= $this->db->query("SELECT t2.id,t1.id as master_id,t2.consulting_type,t2.start_datetime, t2.end_datetime,t2.status FROM `doctor_slot_master` t1 INNER JOIN `doctor_slot` t2 on t1.id=t2.master_id where t1.start_datetime>='".$start_datetime."' and t1.end_datetime<='".$end_datetime."' and t1.user_id='".$user_id."' and t1.status='1'")->getResultArray();
                    if(!empty($get_slots)){
                        //var_dump($get_master_slots);
                        $response['status']=200;
                        $response['statuscode']=true;
                        $response['message']='Success';
                        $response['data']=$get_slots;
                    }else{
                        $response['status']=200;
                        $response['statuscode']=false;
                        $response['message']='No data found';
                        $response['data']=[];
                    }
                }else{
                    $response['status']=200;
                    $response['statuscode']=false;
                    $response['message']='No user data found';
                    $response['data']=[];
                }
            }else{
                $response['status']=200;
                $response['statuscode']=false;
                $response['message']='Data is empty';
                $response['data']=[];
            }
        }else{
            $response['status']=200;
            $response['statuscode']=false;
            $response['message']='No data found';
            $response['data']=[];
        }
        return $response;
    }
    
    public function add_bank_details($data=''){
            $response=array('status'=>false,'statuscode'=>200,'message'=>'No input data');
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
                                $response['statuscode']=400;
                                $response['message']='Not inserted in db';
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='bank_account_holder_name,bank_account_number,bank_name Cannot be null';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;  
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
        public function get_bank_details($data=''){
            $response['status']=false;
            $response['statuscode']=200;
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
                    $response['statuscode']=200;
                    $response['message']='Access_token or User_id Missing';
                    $response['data']=[];
                }
            }
            return $response;
        }
        
        public function resend_otp($data='') {
            if(!empty($data)){
                if($data['user_id']){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                
                if($user_id!=''){
                    //check data
                    $get_user_data= $this->db->table('user')->select('*')->where('id',$user_id)->get()->getRowArray();
                    if(!empty($get_user_data)){
                        //var_dump($get_user_data);
                        //generate OTP
                        $generate_otp= $this->generate_otp();
                        //send OTP
                        $phone_number='+'.$get_user_data['country_code'].$get_user_data['mobile'];
                        //update OTP
                        $update_data['otp']=$generate_otp;
                        $update= $this->db->table('user')->where('id',$user_id)->update($update_data);
                        if($update){
                            $send_otp=$this->send_twilo_otp($phone_number, $generate_otp, $get_user_data['app_signature_id']);
                            //var_dump($send_otp);
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=array('otp'=>$generate_otp,'user_id'=>$user_id,'access_token'=>$get_user_data['access_token']); 

                        }else{
                           $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not updated in db';
                            $response['data']=[];  
                        }
                        }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user found';
                        $response['data']=[];  
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data is empty';
                    $response['data']=[]; 
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
                $response['data']=[];
            }
            return $response;
        }
        public function save_country($data=''){
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
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
            // var_dump($access_token);
                if($user_id!='' && $access_token!=''){
                    $country='';
                    $country_code='';
                    $phone_code='';
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
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
                                $getinpt=array('user_id'=>$user_id,'access_token'=>$access_token);                          
                                $get_all_country=$this->get_all_country($getinpt);
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
        public function get_all_country($data=''){
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
        
        public function add_user_symptoms($data='') {
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
                
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                
                if(isset($data['symptoms'])){
                    $symptoms=$data['symptoms'];
                }else{
                    $symptoms=[];
                }
                if($user_id!='' && $access_token!='' && $family_member_id!='' && $symptoms!=''){
                    $split_symptom=explode(',',$symptoms);
                    //var_dump($split_symptom);
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //start book slot process
                        //check already exist or not
                        //$check_book_slot= $this->db->table('book_slot')->select('*')->where('user_id',$user_id)->where('family_member_id',$family_member_id)->where('status',0)->get()->getRowArray();
                        //var_dump($check_book_slot);
                        $book_slot_data['user_id']=$user_id;
                        $book_slot_data['family_member_id']=$family_member_id;
                        $book_slot_data['booked_datetime']= $this->current_datetime;
                        //generate booking_id squences 
                        $current_year=date('Y');
                        $current_month=date('m');
                        $get_booking_count=$this->db->table('book_slot')->select('count(*) as count')->where('year(booked_datetime)',$current_year)->where('month(booked_datetime)',$current_month)->get()->getRowArray();
                        
                        //var_dump('last_id, ',$last_id['count']);
                        $id=$get_booking_count['count']+1;
                        //call short form to get app letters
                        $sequence_letter= $this->get_short_form();
                        $booking_id=$sequence_letter.'_'.$current_year.$current_month.sprintf('%04d',$id);
                        $book_slot_data['booking_id']= $booking_id;
                        //insert data in book slot table
                        $insert_book_slot= $this->db->table('book_slot')->insert($book_slot_data);
                        if($insert_book_slot){
                            $last_inserted_value= $this->db->insertID();
                            $symptom_data=array();
                            foreach ($split_symptom as $symptom){
                                array_push($symptom_data,array('book_slot_id'=>$last_inserted_value,
                                                                'symptoms'=>$symptom));
                            }
                            $save_spcelization= $this->db->table('booking_user_symptoms')->insertBatch($symptom_data);
                            if($save_spcelization){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='success';
                                $response['data']=array('booking_id'=>$last_inserted_value);
                            }else{
                                $response['status']=false;
                                $response['statuscode']=200;
                                $response['message']='Not inserted in db';
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Not inserted in db';
                        }
                        
                        //$response['status']=false;
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data is Empty';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function book_doctor_slot($data='') {
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
                if(isset($data['book_slot_id'])){
                    $book_slot_id=$data['book_slot_id'];
                }else{
                    $book_slot_id='';
                }
                if(isset($data['booking_id'])){
                    $booking_id=$data['booking_id'];
                }else{
                    $booking_id='';
                }
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                if(isset($data['sick_notes'])){
                    $sick_notes=$data['sick_notes'];
                }else{
                    $sick_notes='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //check booking slot is valid and get vist_type
                        $check_booking_slot= $this->db->table('doctor_slot')->select('*')->where('id',$book_slot_id)->get()->getRowArray();
                        if(!empty($check_booking_slot)){
                            //var_dump($check_booking_slot);
                            //save book slots
                            $book_slot_data['doctor_slot_id']=$book_slot_id;
                            $book_slot_data['sick_notes']=$sick_notes;
                            $book_slot_data['visit_type']=$check_booking_slot['consulting_type'];
                            $update_doctor_slot= $this->db->table('book_slot')->where('id',$booking_id)->update($book_slot_data);
                            if($update_doctor_slot){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='Not updated in db';   
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Not a valided slot';
                        }
                        
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data is Empty';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        
        public function fill_patient_profile($data='', $user_data='') {
           // var_dump($user_data);
            //var_dump($data,$user_data);
            //save the data to employee details and family_member
            $user_id=$data['user_id'];
            $access_id=$user_data['access_id'];
            $updated_datetime= $this->current_datetime;
            if(isset($data['gender'])){
                $gender=$data['gender'];
            }else{
                $gender='';
            }
            if(isset($data['email'])){
                $email=$data['email'];
            }else{
                $email='';
            }
            if(isset($data['name'])){
                $name=$data['name'];
            }else{
                $name='';
            }
            if(isset($data['relation'])){
                $relation=$data['relation'];
            }else{
                $relation='';
            }
            if(isset($data['dob'])){
                $dob=$data['dob'];
            }else{
                $dob='';
            }
            if(isset($data['blood_group'])){
                $blood_group=$data['blood_group'];
            }else{
                $blood_group='';
            }
            if(isset($data['height'])){
                $height=$data['height'];
            }else{
                $height='';
            }
            if(isset($data['weight'])){
                $weight=$data['weight'];
            }else{
                $weight='';
            }
            //update user name in user table
            $user_table_data['username']=$name;
            $user_table_data['email']=$email;
            $update_user_table=$this->db->table('user')->where('id',$user_id)->update($user_table_data);
            if($update_user_table){
                $employee_data['user_id']=$user_id;
                $employee_data['access_id']=$access_id;
                $employee_data['updated_datetime']= $updated_datetime;
                $employee_data['updated_by']= $user_id;
                $employee_data['gender']=$gender;
                $employee_data['email']=$email;
                $employee_data['profile_pic']=$data['profile_pic_path'];
                $save_employee_data= $this->db->table('employee_basic_details')->insert($employee_data);
                if($save_employee_data){
                    //insert in family member table
                    $family_member_data['user_id']=$user_id;
                    $family_member_data['added_datetime']=$this->current_datetime;
                    $family_member_data['family_member_id']=$this->generate_family_member_id();
                    $family_member_data['email_id']=$email;
                    $family_member_data['username']=$name;
                    $family_member_data['profile_pic']=$data['profile_pic_path'];
                    $family_member_data['updated_datetime']=$this->current_datetime;
                    $family_member_data['relation']=$relation;
                    $family_member_data['dob']=$dob;
                    $family_member_data['gender']=$gender;
                    $family_member_data['blood_group']=$blood_group;
                    $family_member_data['height']=$height;
                    $family_member_data['weight']=$weight;
                    //also need to generate qr_code of family member
                    $family_member_data['generate_qrcode']='';
                    //var_dump($family_member_data);
                    $insert_family_member=$this->db->table('family_member')->insert($family_member_data);
                    if($insert_family_member){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Not inserted in db';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Not inserted in db';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=400;
                $response['message']='Not updated in db';
            }

            return $response;
            
        }

        public function generate_family_member_id(){
            //get last id from family_member table and set id sequence
            //SELECT count(*) FROM `user` where year(created_datetime)='2022' and month(created_datetime)='8'
            $current_year=date('Y');
            $current_month=date('m');
            $get_family_member_id= $this->db->table('family_member')->select('count(*) as count')->where('year(added_datetime)',$current_year)->where('month(added_datetime)',$current_month)->get()->getRowArray();
            //var_dump('last_id, ',$last_id['count']);
            $id=$get_family_member_id['count']+1;
            //call short form to get app letters
            $sequence_letter= $this->get_short_form();
            $family_member_id=$sequence_letter.'_FM_'.$current_year.$current_month.sprintf('%04d',$id);
            return $family_member_id;
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
        
}
