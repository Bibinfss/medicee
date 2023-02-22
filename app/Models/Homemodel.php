<?php

    /*
    * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
    * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
    */

    /**
    * Description of Homemodel
    *
    * @author fss
    */
    namespace App\Models;
    use CodeIgniter\Model;
    include APPPATH.'ThirdParty/twilio/src/Twilio/autoload.php';
    use Twilio\Rest\Client; 
    class Homemodel extends Model {
        public function __construct() {
            $this->db= \Config\Database::connect();
            date_default_timezone_set("Asia/Calcutta");
            $this->current_datetime=date('Y-m-d H:i:s');
            $this->default_img='uploads/Default/FILE_20230118002944.png';
            $this->default_hos_img='uploads/Default/FILE_20230120001445.jpg';
        }
        public function add_access($data=''){
            if(!empty($data)){
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }
                $builder = $this->db->table('access');
                $insert_data['access_name']=$data['access_name'];
                if($id!=''){
                    $save=$builder->where('id',$id)->update($insert_data);
                }else{
                    $save=$builder->insert($insert_data);
                }
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
                if(!empty($check_mobile_number)){
                    //user found
                    //check access_id
                    if($check_mobile_number['access_id']==$data['access_id']){
                        //do update with the existing account
                        $update_data['device_type']=$data['device_type'];
                        $update_data['otp']= $this->generate_otp();
                        $update_data['app_signature_id']=$data['app_signature_id'];
                        $update_builder= $this->db->table('user');
                        $update_builder->where('id',$check_mobile_number['id']);
                        $update= $update_builder->update($update_data);
                        if($update){
                            //send otp
                            $phone_mobile='+'.$data['country_code'].$data['mobile'];
                            // $send_otp= $this->send_twilo_otp($phone_mobile,$update_data['otp'],$update_data['app_signature_id']);
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success';
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
                    $user_id= $this->set_user_id();
                    $insert_user_data['user_id']=$user_id;
                    $insert_user_data['access_token']=bin2hex(random_bytes(16));
                    $insert_user_data['created_datetime']=$this->current_datetime;
                    $insert_user_data['access_id']=$data['access_id'];
                    $insert_user_data['country_id']= $this->get_country_id_by_country_code($data['country_code']);
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
            $current_year=date('Y');
            $current_month=date('m');
            $builder= $this->db->table('user');
            $builder->select('count(*) as count')->where('year(created_datetime)',$current_year)->where('month(created_datetime)',$current_month);
            $last_id= $builder->get()->getRowArray();
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
            $sid    = "AC5ef49090676f2c6602fcf0fbbb4ee226"; 
            $token  = "751c3763d9d568023666d41a0802657d"; 
            $twilio = new Client($sid, $token); 
            $otp_text='Your OTP code is '.$otp.' '.$app_signature_id;
            $message = $twilio->messages 
            ->create($phone_number, // to 
                array(  
                    "messagingServiceSid" => "MGd0a149b5b8f8620780452d78de044f9a",      
                    "body" => $otp_text
                ) 
            ); 
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
                if($user_id!='' && $access_token!='' && $otp!=''){
                    $otp_builder= $this->db->table('user');
                    $otp_builder->select('*')->where('id',$user_id)->where('otp',$otp);
                    $get_otp_details=$otp_builder->get()->getRowArray();
                    if(!empty($get_otp_details)){
                        //update otp status
                        $update_data['otp_status']=1;
                        $update_otp_builder= $this->db->table('user');
                        $update_otp_builder->where('id',$user_id);
                        $update=$update_otp_builder->update($update_data);
                        //access
                        $access= $this->db->table('access');
                        $access->select('*')->where('id',$get_otp_details['access_id']);
                        $access_details=$access->get()->getRowArray();
                        //family_member_id
                        $family_member= $this->db->table('family_member');
                        $family_member->select('*')->where('user_id',$user_id)->where('status',1)->where('default_status',1);
                        $family_member_details=$family_member->get()->getRowArray();
                        // var_dump($family_member_details);
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='success';
                        //get login status
                        if($get_otp_details['access_id']==3){
                            $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'login_status'=>$get_otp_details['login_status'],'user_type'=>$access_details['access_name'],'family_member_id'=>$family_member_details['id']);
                        }else{
                            $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'login_status'=>$get_otp_details['login_status'],'user_type'=>$access_details['access_name']);
                        }
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
                    if($check_user['status']==true){
                        //update user profile data
                        $update_user_profile_builder= $this->db->table('user');
                        $update_user_profile_builder->where('id',$user_id);
                        $update_data['username']=$username;
                        $update_data['email']=$email;
                        $update_data['latitude']=$latitude;
                        $update_data['longitude']=$longitude;
                        $user_type= $this->get_access_name($check_user['data']['access_id']);
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
                                if(empty($check_employee_data_exist)){
                                    $insert_into_employee_details=$employee_table_builder->insert($employee_basic_data);
                                }else{
                                    $update_basic_data['updated_datetime']= $this->current_datetime;
                                    $update_basic_data['updated_by']=$user_id;
                                    $update_basic_data['gender']= $gender;
                                    $update_basic_data['email']= $email;
                                    $update_basic_data['profile_pic']= $data['profile_pic_path'];
                                    $update_employee_details= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($update_basic_data);
                                }
                                if($data['profile_pic_path']!=''){
                                    $data['profile_pic_path']= base_url().'/'.$data['profile_pic_path'];
                                }
                                $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'user_type'=>$user_type,'username'=>$username,'email'=>$email,'gender'=>$gender,'profile_pic'=>$data['profile_pic_path']);
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
                        //update consulting fee
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
        public function check_doctor_verification_status($user_id=''){
            $check_doctor_verification_status= $this->db->table('employee_basic_details')->select('*')->where('user_id',$user_id)->get()->getRowArray();
            if(!empty($check_doctor_verification_status)){
                $status=$check_doctor_verification_status['admin_verification_status'];
            }else{
                $status='';
            }
            return $status;
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
                if(isset($data['break_status'])){
                    $break_status=$data['break_status'];
                    if($break_status==1){
                        $break_value=5;
                    }else{
                        $break_value=0; 
                    }
                }else{
                    $break_status='';
                    $break_value=0;
                }
                $type='doctor';
                $response['input_data']=$data;
                if($user_id!='' && $access_token!='' && $type!='' && $consulting_time!='' && $consulting_type!='' && $start_datetime!='' && $end_datetime!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //check admin verification done or not
                        $admin_verification_status= $this->check_doctor_verification_status($user_id);
                        if($admin_verification_status==1){
                            //get doctor consulting fee
                            $consulting_fee= $this->get_doctor_last_consulting_fee($user_id);
                            $consulting_master_data['created_datetime']= $this->current_datetime;
                            $consulting_master_data['created_by']= $user_id;
                            $consulting_master_data['user_id']= $user_id;
                            $consulting_master_data['type']= $type;
                            $consulting_master_data['consulting_time']= $consulting_time;
                            $consulting_master_data['consulting_type']= $consulting_type;
                            $consulting_master_data['start_datetime']= $start_datetime;
                            $consulting_master_data['end_datetime']= $end_datetime;
                            //get dates from start_datetime and end_datetime of user input
                            $get_start_date= explode(" ", $start_datetime);
                            $get_end_date= explode(" ", $end_datetime);
                            $set_start_datetime=$get_start_date[0].' '.'00:00:00';
                            $set_end_datetime=$get_end_date[0].' '.'23:59:59';
                            $get_master_data= $this->db->table('doctor_slot_master')->select('*')->where('start_datetime>=',$set_start_datetime)->where('end_datetime<=',$set_end_datetime)->get()->getResultArray();
                            if(!empty($get_master_data)){
                                $master_data_array=array();
                                $already_exist_slot_status=false;
                                foreach($get_master_data as $master_data){
                                    $get_split_datetime=$this->split_datetime_from_two_datetime($master_data['start_datetime'],$master_data['end_datetime']);
                                    //remove comma from the get split datetime
                                    $remove_comma= explode(',', $get_split_datetime);
                                    foreach($remove_comma as $splited_dates){
                                        array_push($master_data_array,$splited_dates);
                                    }
                                }
                                //check start and end time exist in the array
                                if(in_array($start_datetime, $master_data_array) || in_array($end_datetime,$master_data_array)){
                                    $response['status']=false;
                                    $response['statuscode']=200;
                                    $response['message']='You book in this slot. Already registered';   
                                }else{
                                    $save_master_data= $this->db->table('doctor_slot_master')->insert($consulting_master_data);
                                    if($save_master_data){
                                        $last_inserted_id= $this->db->insertID();
                                        $slots = $this->getTimeSlot($consulting_time, $start_datetime, $end_datetime,$break_value);
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
                                            }
                                            // var_dump($doctor_slots);die();
                                            //insert all the datas
                                            $save_slot_data= $this->db->table('doctor_slot')->insertBatch($doctor_slots);
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
                            }else{
                                //insert new entry
                                $save_master_data= $this->db->table('doctor_slot_master')->insert($consulting_master_data);
                                if($save_master_data){
                                    $last_inserted_id= $this->db->insertID();
                                    $slots = $this->getTimeSlot($consulting_time, $start_datetime, $end_datetime,$break_value);
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
                                        }
                                        //insert all the datas
                                        $save_slot_data= $this->db->table('doctor_slot')->insertBatch($doctor_slots);
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
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Please wait for admin verification';
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
                $start = $startTime;
                $end = date('Y-m-d H:i:s',strtotime('+'.$interval.' minutes',strtotime($startTime)));
                //including break time
                $sum_with_break=$interval+$break;
                $startTime = date('Y-m-d H:i:s',strtotime('+'.$sum_with_break.' minutes',strtotime($startTime)));
                $i++;
                if(strtotime($startTime) <= strtotime($endTime)){
                    $time[$i]['slot_start_time'] = $start;
                    $time[$i]['slot_end_time'] = $end;
                }
            }
            return $time;
        }
        public function split_datetime_from_two_datetime($start_datetime='',$end_datetime=''){
            $end_datetime=new \DateTime($end_datetime);
            $end_datetime->modify('+1 minutes');
            $period = new \DatePeriod(
                new \DateTime($start_datetime),
                new \DateInterval('PT1M'),
                $end_datetime
            );
            $date='';
            foreach ($period as $key => $value) {
                $date=$date.$value->format('Y-m-d H:i:s').',';
            }
            return rtrim($date, ',');
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
                        $start_datetime=$date.' 00:00:00';
                        $end_datetime=$date.' 23:59:59';
                        $get_slots= $this->db->query("SELECT t2.id,t1.id as master_id,t2.consulting_type,t2.start_datetime, t2.end_datetime,t2.status FROM `doctor_slot_master` t1 INNER JOIN `doctor_slot` t2 on t1.id=t2.master_id where t1.start_datetime>='".$start_datetime."' and t1.end_datetime<='".$end_datetime."' and t1.user_id='".$user_id."' and t1.status='1'")->getResultArray();
                        if(!empty($get_slots)){
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
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
                        //start book slot process
                        $book_slot_data['user_id']=$user_id;
                        $book_slot_data['family_member_id']=$family_member_id;
                        $book_slot_data['booked_datetime']= $this->current_datetime;
                        $current_year=date('Y');
                        $current_month=date('m');
                        $get_booking_count=$this->db->table('book_slot')->select('count(*) as count')->where('year(booked_datetime)',$current_year)->where('month(booked_datetime)',$current_month)->get()->getRowArray();
                        $id=$get_booking_count['count']+1;
                        $sequence_letter= $this->get_short_form();    //call short form to get app letters
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
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
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
                    if($check_user['status']){ //check booking slot is valid and get vist_type
                        $check_booking_slot= $this->db->table('doctor_slot')->select('*')->where('id',$book_slot_id)->get()->getRowArray();
                        if(!empty($check_booking_slot)){
                            $check_booking_id= $this->db->table('book_slot')->select('*')->where('id',$booking_id)->get()->getRowArray();
                            if(!empty($check_booking_id)){
                                //save book slots
                                $book_slot_data['doctor_slot_id']=$book_slot_id;
                                $book_slot_data['doctor_id']=$doctor_id;
                                $book_slot_data['sick_notes']=$sick_notes;
                                $book_slot_data['visit_type']=$check_booking_slot['consulting_type'];
                                $book_slot_data['booked_for']=$check_booking_slot['start_datetime'];
                            // var_dump($book_slot_data);
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
                                $response['statuscode']=400;
                                $response['message']='No data in db';
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
                $relation='self';
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
            $user_table_data['login_status']=1;
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
                    $family_member_data['default_status']=1;
                    $family_member_data['generate_qrcode']='';
                    $insert_family_member=$this->db->table('family_member')->insert($family_member_data);
                    if($insert_family_member){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                        //get family member details
                        $last_inserted_id= $this->db->insertID();
                        $get_data= $this->db->table('family_member')->select('*')->where('id',$last_inserted_id)->get()->getRowArray();
                        $response['data']=$get_data;
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
            $current_year=date('Y');
            $current_month=date('m');
            $get_family_member_id= $this->db->table('family_member')->select('count(*) as count')->where('year(added_datetime)',$current_year)->where('month(added_datetime)',$current_month)->get()->getRowArray();
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
        public function get_doctor_basic_details($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //get doctor basic details
                        $get_doctor_details= $this->db->query("SELECT t1.id, t1.username, t1.access_id, t2.email,t2.gender, t2.profile_pic, t2.admin_verification_status, t2.rejection_reason FROM `user` t1 INNER JOIN `employee_basic_details` t2 on t1.id=t2.user_id where t1.id='".$user_id."'")->getRowArray();
                        if(!empty($get_doctor_details)){
                            if($get_doctor_details['profile_pic']!=''){
                                $get_doctor_details['profile_pic']= base_url().'/'.$get_doctor_details['profile_pic'];
                            }else{
                                $get_doctor_details['profile_pic']='';
                            }
                            //get access_id data
                            $get_doctor_details['access_id']=$this->get_access_name($get_doctor_details['access_id']);
                            $get_doctor_details['assistant']=$this->db->table('doctor_assistant')->select('user.username as assistant_name, doctor_assistant.phone_number, doctor_assistant.assistant_id')->join('user','doctor_assistant.assistant_id=user.id','left')->where('doctor_id',$get_doctor_details['id'])->where('doctor_assistant.status',1)->where('user.status',1)->get()->getResultArray();
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$get_doctor_details;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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

        public function add_family_members($data='', $user_data=''){
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
            //insert in family member table
            $family_member_data['user_id']=$user_id;
            $family_member_data['added_datetime']=$this->current_datetime;
            $family_member_data['family_member_id']=$this->generate_family_member_id();
            $family_member_data['email_id']=$email;
            $family_member_data['username']=$name;
            $family_member_data['profile_pic']=$data['profile_pic'];
            $family_member_data['updated_datetime']=$this->current_datetime;
            $family_member_data['relation']=$relation;
            $family_member_data['dob']=$dob;
            $family_member_data['gender']=$gender;
            $family_member_data['blood_group']=$blood_group;
            $family_member_data['height']=$height;
            $family_member_data['weight']=$weight;
            //also need to generate qr_code of family member
            $family_member_data['default_status']=0;
            $family_member_data['generate_qrcode']='';
            //var_dump($family_member_data);
            $insert_family_member=$this->db->table('family_member')->insert($family_member_data);
            if($insert_family_member){
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='Success';
                //get family member data
                $last_inserted_id= $this->db->insertID();
                $get_data= $this->db->table('family_member')->select('*')->where('id',$last_inserted_id)->get()->getRowArray();
                //var_dump($get_data);
                $response['data']=$get_data;
            }else{
                $response['status']=false;
                $response['statuscode']=400;
                $response['message']='Not inserted in db';
            }
            return $response;
        }

        public function get_family_members($data=''){
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
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        //all the family member with status 1
                        $get_all_family_member= $this->db->table('family_member')->select('id,username,profile_pic,relation,email_id')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($get_all_family_member)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            foreach($get_all_family_member as $key => $value){
                                if($get_all_family_member[$key]['profile_pic']!=''){
                                    $get_all_family_member[$key]['profile_pic']= base_url().'/'.$get_all_family_member[$key]['profile_pic']; 
                                }else{
                                    $get_all_family_member[$key]['profile_pic']='';
                                }
                            }
                            $response['data']=$get_all_family_member;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No family member data found'; 
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
        public function get_family_members_data_by_id($data=''){
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
                if(isset($data['member_id'])){
                    $member_id=$data['member_id'];
                }else{
                    $member_id='';
                }
                if($user_id!='' && $access_token!='' && $member_id!=''){
                    //check user is valid
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){ //all the family member with status 1
                        $get_all_family_member= $this->db->table('family_member')->select('*')->where('user_id',$user_id)->where('id',$member_id)->where('status',1)->get()->getResultArray();
                        if(!empty($get_all_family_member)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            foreach($get_all_family_member as $key => $value){
                                if($get_all_family_member[$key]['profile_pic']!=''){
                                    $get_all_family_member[$key]['profile_pic']= base_url().'/'.$get_all_family_member[$key]['profile_pic']; 
                                }else{
                                    $get_all_family_member[$key]['profile_pic']='';
                                }
                                unset($get_all_family_member[$key]['default_status']);
                                unset($get_all_family_member[$key]['status']);
                            }
                            $response['data']=$get_all_family_member;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No family member data found'; 
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
        public function edit_family_members_data($data=''){
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
                if(isset($data['member_id'])){
                    $member_id=$data['member_id'];
                }else{
                    $member_id='';
                }
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
                if(isset($data['profile_pic_path'])){
                    $profile_pic_path=$data['profile_pic_path'];
                }else{
                    $profile_pic_path='';
                }
                if($user_id!='' && $access_token!='' && $member_id!=''){
                //check user is valid
                $check_user_data= $this->check_user_detail($user_id, $access_token);
                if($check_user_data['status']){
                //update family member data by member_id
                if($email!=''){
                $update_data['email_id']=$email; 
                $update_user_table_data['email']=$email;
                $update_employee_table_data['email']=$email;
                }
                if($name!=''){
                $update_data['username']=$name;
                $update_user_table_data['username']=$name;
                }
                if($relation!=''){
                $update_data['relation']=$relation;
                }

                if($dob!=''){
                $update_data['dob']=$dob;
                }
                if($gender!=''){
                $update_data['gender']=$gender;
                $update_employee_table_data['gender']=$gender;
                }
                if($blood_group!=''){
                $update_data['blood_group']=$blood_group;
                }
                if($height!=''){
                $update_data['height']=$height;
                }
                if($weight!=''){
                $update_data['weight']=$weight;
                }
                if($profile_pic_path!=''){
                $update_data['profile_pic']=$profile_pic_path;
                $update_employee_table_data['profile_pic']=$profile_pic_path;
                }
                $update_data['generate_qrcode']='';
                $update_data['updated_datetime']= $this->current_datetime;
                //member_id is valid 
                $check_family_member= $this->db->table('family_member')->select('*')->where('id',$member_id)->get()->getRowArray();
                if(!empty($check_family_member)){
                //                            var_dump('ssss');
                //                            var_dump($check_family_member['default_status']);
                if($check_family_member['default_status']==1){
                //update in user table
                $update_user_table= $this->db->table('user')->where('id',$user_id)->update($update_user_table_data);
                $update_employee_table_data['updated_datetime']= $this->current_datetime;
                $update_employee_table= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($update_employee_table_data);

                $update_family_member_data= $this->db->table('family_member')->where('id',$member_id)->where('user_id',$user_id)->update($update_data);
                if($update_family_member_data){
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='success';
                //get family member data
                $get_family_member_data= $this->db->table('family_member')->select('*')->where('id',$member_id)->where('user_id',$user_id)->get()->getRowArray();
                //var_dump($get_family_member_data);
                //need to unset unwanted field key
                unset($get_family_member_data['default_status']);
                unset($get_family_member_data['status']);
                $response['data']=$get_family_member_data;
                }else{
                $response['status']=false;
                $response['statuscode']=400;
                $response['message']='Not updated to db';
                $response['data']=[];
                }
                }
                }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No member data found';
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

        public function get_doctor_career_details($data=''){
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
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='Success';
                    $get_employee_qualification= $this->get_employee_qualification($data);
                    $get_employee_experience= $this->get_employee_experience($data);
                    $get_employee_specialization=$this->get_employee_specialization($data);
                    $response['data']=array("qualification"=>$get_employee_qualification['data'],"experience"=>$get_employee_experience['data'],"specialization"=>$get_employee_specialization['data']);
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

        public function save_family_member_qr_code($qr_path='',$id=''){
            if($qr_path!='' && $id!=''){
                //update qr path to db
                $update_data['generate_qrcode']=$qr_path;
                $update_qr_code= $this->db->table('family_member')->where('id',$id)->update($update_data);
                if($update_qr_code){
                    //get all data
                    $get_family_member_data= $this->db->table('family_member')->select('*')->where('id',$id)->get()->getRowArray();
                    if(!empty($get_family_member_data)){
                        unset($get_family_member_data['default_status']); 
                        unset($get_family_member_data['status']); 
                        if($get_family_member_data['profile_pic']!=''){
                            $get_family_member_data['profile_pic']= base_url().'/'.$get_family_member_data['profile_pic'];
                        }
                        if($get_family_member_data['generate_qrcode']!=''){
                            $get_family_member_data['generate_qrcode']= base_url().'/'.$get_family_member_data['generate_qrcode'];
                        }
                    }
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='success';
                    $response['data']=$get_family_member_data;
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='qr code not saved in db';
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

        public function add_doctor_basic_details($data=''){
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
                if(isset($data['consulting_fee'])){
                    $consulting_fee=$data['consulting_fee'];
                }else{
                    $consulting_fee='';
                }
                if(isset($data['profile_pic'])){
                    $profile_pic=$data['profile_pic'];
                }else{
                    $profile_pic='';
                }
                if($user_id!="" && $access_token!="" && $username!="" && $email!="" && $consulting_fee!='' && $profile_pic!=''){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
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
                                //insert in employee_basic_details table
                                $employee_basic_data['user_id']=$user_id;
                                $employee_basic_data['access_id']=$check_user['data']['access_id'];
                                $employee_basic_data['updated_datetime']= $this->current_datetime;
                                $employee_basic_data['updated_by']= $user_id;
                                $employee_basic_data['gender']= $gender;
                                $employee_basic_data['email']= $email;
                                $employee_basic_data['profile_pic']= $profile_pic;
                                $employee_table_builder= $this->db->table('employee_basic_details');
                                //check entry exist in employee_basic_details table
                                $check_employee_data_exist= $this->db->table('employee_basic_details')->where('user_id',$user_id)->get()->getRowArray();
                                if(empty($check_employee_data_exist)){
                                    $insert_into_employee_details=$employee_table_builder->insert($employee_basic_data);
                                }else{
                                    $update_basic_data['updated_datetime']= $this->current_datetime;
                                    $update_basic_data['updated_by']=$user_id;
                                    $update_basic_data['gender']= $gender;
                                    $update_basic_data['email']= $email;
                                    $update_basic_data['profile_pic']= $profile_pic;
                                    $update_employee_details= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($update_basic_data);
                                }
                                if($data['profile_pic']!=''){
                                    $data['profile_pic']= base_url().'/'.$data['profile_pic'];
                                }
                                //save specialization
                                //insert
                                $specialization_status=false;
                                $qualification_status=false;
                                $experience_status=false;
                                $consulting_status=false;
                                $error_msg='';
                                if(isset($data['specialization'])){
                                    $specialization=$data['specialization'];
                                    $specialization_array_data=array();
                                    if(!empty($specialization)){
                                        foreach($specialization as $specialization_data){
                                            $specialization=$this->db->table('specialization')->where('id',$specialization_data)->get()->getRowArray();
                                            // var_dump($specialization["specialization"]);
                                            if(isset($specialization["specialization"])){
                                                $specialization=$specialization["specialization"];
                                            }else{
                                                $specialization='';
                                            }
                                            array_push($specialization_array_data,array('updated_datetime'=> $this->current_datetime,
                                            'user_id'=>$user_id,
                                            'specialization'=>$specialization,
                                            'specialization_id'=>$specialization_data));
                                        }
                                        $insert_specialization=$this->db->table('employee_specialization')->insertBatch($specialization_array_data);
                                        if($insert_specialization>0){
                                            $specialization_status=true;
                                        }else{
                                            $error_msg='Specialization not saved in db'; 
                                        }
                                    }
                                }
                                //save qualification
                                if(isset($data['qualification'])){
                                    $qualification=$data['qualification'];
                                    $qualification_array_data=array();
                                    if(!empty($qualification)){
                                        foreach($qualification as $qualification_data){
                                            array_push($qualification_array_data, array(
                                                'user_id'=>$user_id,
                                                'updated_datetime'=> $this->current_datetime,
                                                'qualification'=>$qualification_data['qualification'],
                                                'start_year'=>$qualification_data['start_year'],
                                                'end_year'=>$qualification_data['end_year'],
                                                'upload_documents'=>$qualification_data['qualification_document']
                                            ));
                                        }
                                        $insert_qualification=$this->db->table('employee_qualification')->insertBatch($qualification_array_data);
                                        if($insert_qualification>0){
                                            $qualification_status=true;
                                        }else{
                                            $error_msg='Qualification not saved in db';
                                        }
                                    }
                                }

                                //save experience
                                if(isset($data['experience'])){
                                    $experience=$data['experience'];
                                    $experience_array_data=array();
                                    if(!empty($experience)){
                                        foreach($experience as $experience_data){
                                            array_push($experience_array_data,array(
                                                'user_id'=>$user_id,
                                                'updated_datetime'=> $this->current_datetime,
                                                'hospital'=> $experience_data['hospital'],
                                                'years'=> $experience_data['years'],
                                                'position'=> $experience_data['position'],
                                                'experience_documents'=> $experience_data['experience_document'],
                                            ));
                                        }
                                        $insert_experience=$this->db->table('employee_experience')->insertBatch($experience_array_data);
                                        if($insert_experience>0){
                                            $experience_status=true;
                                        }else{
                                            $error_msg='Experience not saved in db';
                                        }
                                    }
                                }

                                //save consulting fee
                                $consulting_fee_data['user_id']=$user_id;
                                $consulting_fee_data['datetime']= $this->current_datetime;
                                $consulting_fee_data['currency_id']=$check_user['data']['currency_id'];
                                $consulting_fee_data['consulting_fee']=$consulting_fee;
                                $save_data= $this->db->table('employee_consulting_fee')->insert($consulting_fee_data);
                                if($save_data){
                                    $consulting_status=true;
                                    //update account setup status or login_status = 1
                                    $update_login_status_data['login_status']=1;
                                    $update_login_status=$this->db->table('user')->where('id',$user_id)->update($update_login_status_data);
                                }else{
                                    $error_msg='Consulting not saved in db'; 
                                }
                                //upload 
                                //if($specialization_status==true && $qualification_status==true && $experience_status==true && $consulting_status==true){
                                if($consulting_status==true){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='success';
                                    $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'user_type'=>$user_type,'username'=>$username,'email'=>$email,'gender'=>$gender,'profile_pic'=>$data['profile_pic']);
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']=$error_msg;
                                    $response['data']=[];
                                }
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
        public function add_doctor_basic_details11($data=''){
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
                if(isset($data['consulting_fee'])){
                    $consulting_fee=$data['consulting_fee'];
                }else{
                    $consulting_fee='';
                }
                if(isset($data['profile_pic'])){
                    $profile_pic=$data['profile_pic'];
                }else{
                    $profile_pic='';
                }
                if($user_id!="" && $access_token!="" && $username!="" && $email!="" && $consulting_fee!='' && $profile_pic!=''){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
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
                                //insert in employee_basic_details table
                                $employee_basic_data['user_id']=$user_id;
                                $employee_basic_data['access_id']=$check_user['data']['access_id'];
                                $employee_basic_data['updated_datetime']= $this->current_datetime;
                                $employee_basic_data['updated_by']= $user_id;
                                $employee_basic_data['gender']= $gender;
                                $employee_basic_data['email']= $email;
                                $employee_basic_data['profile_pic']= $profile_pic;
                                $employee_table_builder= $this->db->table('employee_basic_details');
                                //check entry exist in employee_basic_details table
                                $check_employee_data_exist= $this->db->table('employee_basic_details')->where('user_id',$user_id)->get()->getRowArray();
                                if(empty($check_employee_data_exist)){
                                    $insert_into_employee_details=$employee_table_builder->insert($employee_basic_data);
                                }else{
                                    $update_basic_data['updated_datetime']= $this->current_datetime;
                                    $update_basic_data['updated_by']=$user_id;
                                    $update_basic_data['gender']= $gender;
                                    $update_basic_data['email']= $email;
                                    $update_basic_data['profile_pic']= $profile_pic;
                                    $update_employee_details= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($update_basic_data);
                                }
                                if($data['profile_pic']!=''){
                                    $data['profile_pic']= base_url().'/'.$data['profile_pic'];
                                }
                                //save specialization
                                //insert
                                $specialization_status=false;
                                $qualification_status=false;
                                $experience_status=false;
                                $consulting_status=false;
                                $error_msg='';
                                if(isset($data['specialization'])){
                                    $specialization=$data['specialization'];
                                    $specialization_array_data=array();
                                    if(!empty($specialization)){
                                        foreach($specialization as $specialization_data){
                                            // var_dump($specialization_data);
                                            array_push($specialization_array_data,array('updated_datetime'=> $this->current_datetime,
                                            'user_id'=>$user_id,
                                            'specialization'=>$specialization_data));
                                        }
                                        $insert_specialization=$this->db->table('employee_specialization')->insertBatch($specialization_array_data);
                                        if($insert_specialization>0){
                                            $specialization_status=true;
                                        }else{
                                            $error_msg='Specialization not saved in db'; 
                                        }
                                    }
                                }
                                //save qualification
                                if(isset($data['qualification'])){
                                    $qualification=$data['qualification'];
                                    $qualification_array_data=array();
                                    if(!empty($qualification)){
                                        foreach($qualification as $qualification_data){
                                            array_push($qualification_array_data, array(
                                                'user_id'=>$user_id,
                                                'updated_datetime'=> $this->current_datetime,
                                                'qualification'=>$qualification_data['qualification'],
                                                'start_year'=>$qualification_data['start_year'],
                                                'end_year'=>$qualification_data['end_year'],
                                                'upload_documents'=>$qualification_data['qualification_document']
                                            ));
                                        }
                                        $insert_qualification=$this->db->table('employee_qualification')->insertBatch($qualification_array_data);
                                        if($insert_qualification>0){
                                            $qualification_status=true;
                                        }else{
                                            $error_msg='Qualification not saved in db';
                                        }
                                    }
                                }

                                //save experience
                                if(isset($data['experience'])){
                                    $experience=$data['experience'];
                                    $experience_array_data=array();
                                    if(!empty($experience)){
                                        foreach($experience as $experience_data){
                                            array_push($experience_array_data,array(
                                                'user_id'=>$user_id,
                                                'updated_datetime'=> $this->current_datetime,
                                                'hospital'=> $experience_data['hospital'],
                                                'years'=> $experience_data['years'],
                                                'position'=> $experience_data['position'],
                                                'experience_documents'=> $experience_data['experience_document'],
                                            ));
                                        }
                                        $insert_experience=$this->db->table('employee_experience')->insertBatch($experience_array_data);
                                        if($insert_experience>0){
                                            $experience_status=true;
                                        }else{
                                            $error_msg='Experience not saved in db';
                                        }
                                    }
                                }

                                //save consulting fee
                                $consulting_fee_data['user_id']=$user_id;
                                $consulting_fee_data['datetime']= $this->current_datetime;
                                $consulting_fee_data['currency_id']=$check_user['data']['currency_id'];
                                $consulting_fee_data['consulting_fee']=$consulting_fee;
                                $save_data= $this->db->table('employee_consulting_fee')->insert($consulting_fee_data);
                                if($save_data){
                                    $consulting_status=true;
                                    //update account setup status or login_status = 1
                                    $update_login_status_data['login_status']=1;
                                    $update_login_status=$this->db->table('user')->where('id',$user_id)->update($update_login_status_data);
                                }else{
                                    $error_msg='Consulting not saved in db'; 
                                }
                                //upload 
                                //if($specialization_status==true && $qualification_status==true && $experience_status==true && $consulting_status==true){
                                if($consulting_status==true){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='success';
                                    $response['data']=array('user_id'=>$user_id,'access_token'=>$access_token,'user_type'=>$user_type,'username'=>$username,'email'=>$email,'gender'=>$gender,'profile_pic'=>$data['profile_pic']);
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']=$error_msg;
                                    $response['data']=[];
                                }
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

        public function doctor_details($data=''){
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
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='Success';
                    $get_employee_qualification= $this->get_employee_qualification($data);
                    $get_employee_experience= $this->get_employee_experience($data);
                    $get_employee_specialization=$this->get_employee_specialization($data);
                    $get_employee_basic_details=$this->get_employee_basic_details($data);
                    $response['data']=array("basic_details"=>$get_employee_basic_details['data'],"qualification"=>$get_employee_qualification['data'],"experience"=>$get_employee_experience['data'],"specialization"=>$get_employee_specialization['data']);
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
        public function get_employee_basic_details($data=''){
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
                        $get_employee_basic_details= $this->db->table('employee_basic_details')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getRowArray();
                        if(!empty($get_employee_basic_details)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            if($get_employee_basic_details['profile_pic']!=''){
                                $get_employee_basic_details['profile_pic']= base_url().'/'.$get_employee_basic_details['profile_pic'];
                            }else{
                                $get_employee_basic_details['profile_pic']='';
                            }
                            $get_employee_basic_details['access_id']=$this->get_access_name($get_employee_basic_details['access_id']);
                            $loginstatus=$get_user_data= $this->db->table('user')->select('*')->where('id',$user_id)->get()->getRowArray();
                            $get_employee_basic_details['login_status']=$loginstatus['login_status'];
                            $get_employee_basic_details['username']=$loginstatus['username'];
                            $response['data']=$get_employee_basic_details;
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
        public function update_employee_qualification($data=''){
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
                if(isset($data['upload_documents'])){
                    $upload_documents=$data['upload_documents'];
                }else{
                    $upload_documents='';
                }
                if($user_id!='' && $access_token!='' && $qualification!='' && $start_year!='' && $end_year!='' && $upload_documents!=''){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user){
                        //save to db
                        $qualification_data['user_id']=$user_id;
                        $qualification_data['updated_datetime']= $this->current_datetime;
                        $qualification_data['qualification']=$qualification;
                        $qualification_data['start_year']=$start_year;
                        $qualification_data['end_year']=$end_year;
                        $qualification_data['upload_documents']=$upload_documents;
                        $save_qualification=$this->db->table('employee_qualification')->insert($qualification_data);
                        if($save_qualification){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['last_inserted_id']= $this->db->insertID();
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
        public function update_employee_experience($data=''){
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
                if(isset($data['experience_documents'])){
                    $experience_documents=$data['experience_documents'];
                }else{
                    $experience_documents='';
                }
                if($user_id!="" && $access_token!="" && $hospital!="" && $years!="" && $position!="" && $experience_documents!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $experience_data['user_id']=$user_id;
                        $experience_data['updated_datetime']= $this->current_datetime;
                        $experience_data['hospital']=$hospital;
                        $experience_data['years']=$years;
                        $experience_data['position']=$position;
                        $experience_data['experience_documents']=$experience_documents;
                        $save_data= $this->db->table('employee_experience')->insert($experience_data);
                        if($save_data){
                            $last_insert_id= $this->db->insertID();
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['last_inserted_id']=$last_insert_id;
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

        //reshma
        
        public function update_family_medicalhistory($data=''){
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
                if($user_id!="" && $access_token!="" && $family_member_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        // $experience_data['user_id']=$user_id;
                        $insert_data['family_member_id']=$family_member_id;
                        $insert_data['added_datetime']= $this->current_datetime;
                        if(isset($data['description'])){
                            $insert_data['description']=$data['description'];
                        }else{
                            $insert_data['description']='';
                        }
                        if(isset($data['file_path'])){
                            $insert_data['file_path']=$data['file_path'];
                        }else{
                            $insert_data['file_path']='';
                        }
                        $save_data= $this->db->table('medical_history')->insert($insert_data);
                        if($save_data){
                            $last_insert_id= $this->db->insertID();
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['last_inserted_id']=$last_insert_id;
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
        public function add_family_doctor($data=''){
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
                if(isset($data['family_doctor_id'])){
                    $family_doctor_id=$data['family_doctor_id'];
                }else{
                    $family_doctor_id='';
                }
                if($user_id!="" && $access_token!="" && $family_doctor_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $update_data['family_doctor_id']=$family_doctor_id;
                        $builder = $this->db->table('user');
                        $update=$builder->where('id',$user_id)->update($update_data);
                        if($update){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Updated Successfully';
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not Updated in db';
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
        public function request_refund($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['slot_id'])){
                    $slot_id=$data['slot_id'];
                }else{
                    $slot_id='';
                }
                if($user_id!="" && $access_token!="" && $slot_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $builder = $this->db->table('request_refund');
                        // $update=$builder->where('id',$user_id)->update($update_data);
                        $save=$builder->insert($data);
                        if($save){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Inserted Successfully';
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not Inserted in db';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function add_favourite_doctor($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    // unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($user_id!="" && $access_token!="" && $doctor_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $data['added_datetime']=$this->current_datetime;
                        $builder = $this->db->table('favourite_doctors');
                        $data_exist=$this->db->table('favourite_doctors')->select('favourite_doctors.*')->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.family_member_id',$family_member_id)->where('favourite_doctors.doctor_id',$doctor_id)->where('favourite_doctors.status',1)->get()->getResultArray();
                        if(empty($data_exist)){
                            $save=$builder->insert($data);
                            if($save){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Inserted Successfully';
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='Not Inserted in db';
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Data exist';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }  
        public function list_family_members($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_family_member= $this->db->table('family_member')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($list_family_member)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_family_member;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function show_family_doctor($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    // var_dump($check_user);
                    if($check_user['status']){
                        //list family members
                        $list_family_member= $this->db->table('user')->select('family_doctor_id')->where('id',$user_id)->get()->getRowArray();
                        // var_dump($list_family_member);
                        if(!empty($list_family_member)){
                            $family_doctor_details['family_doctor_id']=$list_family_member['family_doctor_id'];
                            // $family_doctor_details=$this->db->table('user')->select('user.*,employee_basic_details.profile_pic')->where('user.id',$list_family_member['family_doctor_id'])->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->get()->getRowArray();
                            $family_doctor_details=$this->db->table('user')->select('user.username,user.id as doctor_id,employee_basic_details.profile_pic')->where('user.id',$list_family_member['family_doctor_id'])->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->get()->getRowArray();
                            if(!empty($family_doctor_details)){
                                if($family_doctor_details['profile_pic']!=''){
                                    $family_doctor_details['profile_pic']= base_url().'/'.$family_doctor_details['profile_pic'];
                                }else{
                                    $family_doctor_details['profile_pic']='';
                                }
                            }
                            $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$list_family_member['family_doctor_id'])->where('status',1)->get()->getRowArray();
                            if(!empty($experience)){
                                if(isset($experience['experience'])){
                                    $family_doctor_details['experience']=$experience['experience'];
                                }else{
                                    $family_doctor_details['experience']=0;
                                }
                            }else{
                                $family_doctor_details['experience']=0;
                            }
                            $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.
                                *')->where('doctor_id',$list_family_member['family_doctor_id'])->where('status',1)->where('working_status',1)->get()->getResultArray();
                            $organisation_array=[];
                            $organ_str="";
                            foreach ($organisation as $ky => $val) {
                                // array_push($organisation_array, $val['hospital_name']);
                                if($ky!=0){
                                    $organ_str=$organ_str.',';
                                }
                                $organ_str=$organ_str.$val['hospital_name'];
                            }
                            $family_doctor_details['organisation']=$organ_str;
                            // var_dump($organ_str);
                            $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$list_family_member['family_doctor_id'])->where('status',1)->get()->getResultArray();
                            $specialization_array=[];
                            $special_str="";
                            foreach ($specialization as $ke => $va) {
                            // var_dump($specialization);
                                // array_push($specialization_array, $va['specialization']);
                                if($ke!=0){
                                    $special_str=$special_str.',';
                                }
                                $special_str=$special_str.$va['specialization'];
                            }
                            $family_doctor_details['specialization']=$special_str;
                            if(isset($family_doctor_details['family_doctor_id'])){
                                unset($family_doctor_details['family_doctor_id']);
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$family_doctor_details;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function show_favourite_doctors($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $patient_details= $this->db->table('family_member')->select('family_member.id')->where('family_member.user_id',$user_id)->where('family_member.default_status',1)->get()->getRowArray();
                        if(isset($data['family_member_id'])){
                            if($data['family_member_id']!=0){
                                $family_member_id=$data['family_member_id'];
                            }else{
                                $family_member_id=$patient_details['id'];
                            }
                        }else{
                            $family_member_id=$patient_details['id'];
                        }
                        //list family members
                        $list_favourite_doctor= $this->db->table('favourite_doctors')->select('user.username,user.id as doctor_id, favourite_doctors.family_member_id,favourite_doctors.id as id, employee_basic_details.profile_pic')->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.family_member_id',$family_member_id)->where('favourite_doctors.status',1)->join('user','user.id=favourite_doctors.doctor_id','left')->join('employee_basic_details','user.id=employee_basic_details.user_id','left')->groupBy('favourite_doctors.id')->get()->getResultArray();
                        if(!empty($list_favourite_doctor)){
                            foreach ($list_favourite_doctor as $key => $value) {
                                if(!empty($list_favourite_doctor[$key])){
                                    if($list_favourite_doctor[$key]['profile_pic']!=''){
                                        $list_favourite_doctor[$key]['profile_pic']= base_url().'/'.$list_favourite_doctor[$key]['profile_pic'];
                                    }else{
                                        $list_favourite_doctor[$key]['profile_pic']='';
                                    }
                                    $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getRowArray();
                                    if(!empty($experience)){
                                        if(isset($experience['experience'])){
                                            $list_favourite_doctor[$key]['experience']=$experience['experience'];
                                        }else{
                                            $list_favourite_doctor[$key]['experience']=0;
                                        }
                                    }else{
                                        $list_favourite_doctor[$key]['experience']=0;
                                    }
                                    // $list_favourite_doctor[$key]['organisation']=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*')->where('doctor_id',$value['id'])->where('status',1)->where('working_status',1)->get()->getResultArray();
                                    // $list_favourite_doctor[$key]['specialization']=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$value['id'])->where('status',1)->get()->getResultArray();
                                    $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.
                                        *')->where('doctor_id',$value['doctor_id'])->where('status',1)->where('working_status',1)->get()->getResultArray();
                                    $organisation_array=[];
                                    $organ_str="";
                                    foreach ($organisation as $ky => $val) {
                                        // array_push($organisation_array, $val['hospital_name']);
                                        if($ky!=0){
                                            $organ_str=$organ_str.',';
                                        }
                                        $organ_str=$organ_str.$val['hospital_name'];
                                    }
                                    $list_favourite_doctor[$key]['organisation']=$organ_str;
                                    $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getResultArray();
                                    $specialization_array=[];
                                    $special_str="";
                                    foreach ($specialization as $ke => $va) {
                                    // var_dump($specialization);
                                        // array_push($specialization_array, $va['specialization']);
                                        if($ke!=0){
                                            $special_str=$special_str.',';
                                        }
                                        $special_str=$special_str.$va['specialization'];
                                    }
                                    $list_favourite_doctor[$key]['specialization']=$special_str;
                                    unset($list_favourite_doctor[$key]['id']);
                                }
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_favourite_doctor;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function family_doctor_count($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_family_doctor= $this->db->table('user')->select('user.*')->where('family_doctor_id',$user_id)->get()->getResultArray();
                        // var_dump(count($list_family_doctor));
                        if(!empty($list_family_doctor)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['data']=$list_family_doctor;
                            $response['family_doctor_count']=count($list_family_doctor);
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['family_doctor']=0;
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function datewise_booked_slot($data=''){
            if(!empty($data)){
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                    unset($data['doctor_id']);
                }else{
                    $user_id='';
                    $doctor_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['date'])){
                    $fromdatetime=$data['date'].' 00:00:00';
                }else{
                    $fromdatetime='';
                }
                if(isset($data['date'])){
                    $todatetime=$data['date'].' 23:59:59';
                }else{
                    $todatetime='';
                }
                if($doctor_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_slot_booked= $this->db->table('book_slot')->select('book_slot.*,`family_member`.`username` as patient_name')->where('doctor_id',$doctor_id)->where('doctor_slot.start_datetime>=',$fromdatetime)->where('doctor_slot.start_datetime<=',$todatetime)->join('doctor_slot','doctor_slot.id=book_slot.doctor_slot_id','left')->join('family_member','family_member.id=book_slot.family_member_id','left')->get()->getResultArray();
                        // var_dump($list_slot_booked);
                        // var_dump($todatetime,$this->db->getLastQuery());
                        if(!empty($list_slot_booked)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['data']=$list_family_doctor;
                            $response['booked_slot']=$list_slot_booked;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Booking found';
                            $response['booked_slot']=0;
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function patient_details($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //set family member id
                        $patient_details= $this->db->table('family_member')->select('family_member.id')->where('family_member.user_id',$user_id)->where('family_member.default_status',1)->get()->getRowArray();
                        if(isset($data['family_member_id'])){
                            if($data['family_member_id']!=0){
                                $family_member_id=$data['family_member_id'];
                            }else{
                                $family_member_id=$patient_details['id'];
                            }
                        }else{
                            $family_member_id=$patient_details['id'];
                        }
                        //get doctor basic details
                        // $patient_details= $this->db->table('user')->select('user.id,user.username,user.access_id,user.mobile,`employee_basic_details`.`email`,`employee_basic_details`.`gender`,`employee_basic_details`.`profile_pic`,`family_member`.`blood_group`,`family_member`.`height`,`family_member`.`weight`,`family_member`.`id` as family_member_id')->where('user.id',$user_id)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->join('family_member','family_member.user_id=user.id','left')->get()->getRowArray();
                        $patient_details= $this->db->table('user')->select('user.id,family_member.username,user.access_id,user.mobile,user.longitude,user.latitude,`family_member`.`email_id`,`family_member`.`gender`,`family_member`.`profile_pic`,`family_member`.`blood_group`,`family_member`.`height`,`family_member`.`weight`,`family_member`.`relation`,`family_member`.`id` as family_member_id')->where('user.id',$user_id)->where('family_member.id',$family_member_id)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->join('family_member','family_member.user_id=user.id','left')->get()->getRowArray();
                        if(!empty($patient_details)){
                            if($patient_details['profile_pic']!=''){
                                $patient_details['profile_pic']= base_url().'/'.$patient_details['profile_pic'];
                            }else{
                                $patient_details['profile_pic']='';
                            }
                            //get access_id data
                            $patient_details['access_id']=$this->get_access_name($patient_details['access_id']);
                            // $patient_details['medical_history']=$this->patient_medical_history($patient_details['family_member_id']);
                            // $family_member_id=$this->db->table('family_member')->select('family_member.id as family_member_id')->where('user_id',$patient_details['id'])->where('default_status',1)->get()->getRowArray();
                            // $patient_details['family_member_id']=$family_member_id['family_member_id'];
                            $family_member_ids=$this->db->table('family_member')->select('family_member.id as family_member_id,family_member.username as family_member_name,family_member.relation as relation,family_member.profile_pic as profile_pic')->where('user_id',$user_id)->get()->getResultArray();
                            foreach ($family_member_ids as $ky => $va) {
                                if($family_member_ids[$ky]['profile_pic']!=''){
                                    $family_member_ids[$ky]['profile_pic']= base_url().'/'.$family_member_ids[$ky]['profile_pic'];
                                }else{
                                    $family_member_ids[$ky]['profile_pic']='';
                                }
                            }
                            $patient_details['family_member_ids']=$family_member_ids;
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$patient_details;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function patient_medical_history($user_id=''){
            if($user_id!=''){
                $medicalhistory= $this->db->table('medical_history')->select('medical_history.added_datetime,medical_history.description,medical_history.file_path')->where('medical_history.user_id',$user_id)->get()->getResultArray();
                if(!empty($medicalhistory)){
                    foreach ($medicalhistory as $key => $value) {
                        if($medicalhistory[$key]['file_path']!=''){
                            $medicalhistory[$key]['file_path']= base_url().'/'.$medicalhistory[$key]['file_path'];
                        }else{
                            $medicalhistory[$key]['file_path']='';
                        }
                    }
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='Success';
                    $response['data']=$medicalhistory;
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='No data found';
                    $response['data']=[];
                }
            }
        }
        public function list_all_hospitals($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        // $hospital_list= $this->db->table('user')->select('user.*')->where('user.access_id',2)->get()->getResultArray();
                        $hospital_list= $this->db->table('user')->select('user.id,user.username,user.mobile,user.latitude,user.access_id,user.longitude,user.country_code,`user`.`email`,`employee_basic_details`.`profile_pic`')->where('user.access_id',2)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->get()->getResultArray();
                        if(!empty($hospital_list)){
                            foreach ($hospital_list as $key => $value) {
                                if($hospital_list[$key]['profile_pic']!=''){
                                    $hospital_list[$key]['profile_pic']= base_url().'/'.$hospital_list[$key]['profile_pic'];
                                }else{
                                    $hospital_list[$key]['profile_pic']='';
                                }
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$hospital_list;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function add_doctor_assistance($data=''){
            // $user_id=$data['user_id'];
            // $access_id=$data['access_id'];
            $datetime= $this->current_datetime;
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
            if(isset($data['country_code'])){
                $country_code=$data['country_code'];
            }else{
                $country_code='';
            }
            if(isset($data['phone_number'])){
                $phone_number=$data['phone_number'];
            }else{
                $phone_number='';
            }
            if(isset($data['doctor_id'])){
                $doctor_id=$data['doctor_id'];
            }else{
                $doctor_id='';
            }
            if(isset($data['assistant_id'])){
                $assistant_id=$data['assistant_id'];
            }else{
                $assistant_id='';
            }
            //check user is valid
            $check_user_data=$this->check_user_detail($user_id,$access_token);
            if($check_user_data['status']){
                //check user data with country code and phone number
                $check_assistant_exist=$this->db->table('user')->select('*')->where('country_code',$country_code)->where('mobile',$phone_number)->get()->getRowArray();
                //var_dump($check_assistant_exist);
                if(!empty($check_assistant_exist)){
                    // var_dump($check_assistant_exist['access_id']);
                    $get_access_name=$this->get_access_name($check_assistant_exist['access_id']);
                    if($get_access_name=='Assistant' || $get_access_name=='assistant'){
                        $add_doctor_assistance['datetime']=$datetime;
                        $add_doctor_assistance['country_code']=$country_code;
                        $add_doctor_assistance['phone_number']=$phone_number;
                        $add_doctor_assistance['doctor_id']=$doctor_id;
                        $add_doctor_assistance['assistant_id']=$assistant_id;
                        //check assistance is already added or not
                        $check_assisant=$this->db->table('doctor_assistant')->select('*')->where('assistant_id',$assistant_id)->where('status','1')->get()->getRowArray();
                        if(!empty($check_assisant)){
                            $insert_data=$this->db->table('doctor_assistant')->insert($add_doctor_assistance);
                            if($insert_data){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='Not inserted in db';
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='This person is already assistant';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='this user is not a assistant';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No assistant found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No user found';
            }
            return $response;
        }
        public function delete_qualification($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            //check data exist in array
                            // $check_qualification_id= $this->db->table('employee_qualification');
                            // $check_qualification_id->select('*')->where('id',$id)->where('status','1');
                            // $qualification_data=$check_qualification_id->get()->getRowArray();
                            $check_qualification_id=$this->db->table('employee_qualification')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_qualification_id)){
                                $data= $this->db->table('employee_qualification')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no qualification id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Qualification Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        } 
        public function delete_specialization($data=''){
            // var_dump($data);
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id=''; 
                }
                //$response['status']=true;
                if($user_id!=" " && $access_token!=" "){
                    // var_dump($user_id,$access_token);
                    $check_user=$this->check_user_detail($user_id,$access_token);
                    // var_dump($check_user);
                    if($check_user['status']==true){

                        if($id!=''){
                            $update['status']=0;
                            $check_specializtion_id=$this->db->table('employee_specialization')->where('id',$id)->where('status',1)->get()->getRowArray();
                            if(!empty($check_specializtion_id)){ 
                            $data= $this->db->table('employee_specialization')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="No specialization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="No data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Specialization Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="User not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="User_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="No input data";
            }
            return $response;
        }
        public function delete_work_experience($data=''){
            // var_dump($data);
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id=''; 
                }
                //$response['status']=true;
                if($user_id!=" " && $access_token!=" "){
                    // var_dump($user_id,$access_token);
                    $check_user=$this->check_user_detail($user_id,$access_token);
                    // var_dump($check_user);
                    if($check_user['status']==true){

                        if($id!=''){
                            $update['status']=0;
                            $check_id=$this->db->table('employee_experience')->where('id',$id)->where('status',1)->get()->getRowArray();
                            if(!empty($check_id)){ 
                            $data= $this->db->table('employee_experience')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="No employee experience id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="No data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="employee experienceId missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="User not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="User_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="No input data";
            }
            return $response;
        }
        public function list_all_doctor_assistance($data=''){
                $get_doctor_assistance= $this->db->table('doctor_assistant')->select('*')->where('status',1)->get()->getResultArray();
                if(!empty($get_doctor_assistance)){
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='Success';
                    $response['data']=$get_doctor_assistance;
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No data found';
                    $response['data']=[];
                }
                return $response;
            }
        
        public function remove_doctor_assistance($data=''){
                // var_dump($data);
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_remove_doctor=$this->db->table('doctor_assistant')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_remove_doctor)){
                                $data= $this->db->table('doctor_assistant')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function delete_family_member($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_remove_family=$this->db->table('family_member')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_remove_family)){
                                $data= $this->db->table('family_member')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function delete_favourite_doctor($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_favourite_doctor=$this->db->table('favourite_doctors')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_favourite_doctor)){
                                $data= $this->db->table('favourite_doctors')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function list_family_members_data_by_id($data=''){
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
                if(isset($data['member_id'])){
                    $member_id=$data['member_id'];
                }else{
                    $member_id='';
                }
                if($user_id!='' && $access_token!='' && $member_id!=''){
                    //check user is valid
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        //all the family member with status 1
                        $get_all_family_member= $this->db->table('family_member')->select('*')->where('user_id',$user_id)->where('id',$member_id)->where('status',1)->get()->getResultArray();
                        //var_dump($get_all_family_member);
                        if(!empty($get_all_family_member)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            foreach($get_all_family_member as $key => $value){
                                if($get_all_family_member[$key]['profile_pic']!=''){
                                   $get_all_family_member[$key]['profile_pic']= base_url().'/'.$get_all_family_member[$key]['profile_pic']; 
                                }else{
                                    $get_all_family_member[$key]['profile_pic']='';
                                }
                                unset($get_all_family_member[$key]['default_status']);
                                unset($get_all_family_member[$key]['status']);
                            }
                            $response['data']=$get_all_family_member;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No family member data found'; 
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
        public function add_user_medical_history($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    // unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['description'])){
                    $description=$data['description'];
                }else{
                    $description='';
                }
                if(isset($data['file_path'])){
                    $file_path=$data['file_path'];
                }else{
                    $file_path='';
                }
                if($user_id!="" && $access_token!="" && $file_path!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $data['added_datetime']=$this->current_datetime;
                        $builder = $this->db->table('medical_history');
                        $save=$builder->insert($data);
                        if($save){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Inserted Successfully';
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not Inserted in db';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }  
        public function add_banner_list($data=''){
            if(!empty($data)){
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }
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
                if(isset($data['image'])){
                    $image=$data['image'];
                }else{
                    $image='';
                }
                if(isset($data['description'])){
                    $description=$data['description'];
                }else{
                    $description='';
                }
                if($user_id!='' && $access_token!=''){
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    if($check_user['status']==true){
                        $builder = $this->db->table('banner_list');
                        $insert_data['user_id']=$data['user_id'];
                        $insert_data['start_date']=$data['start_date'];
                        $insert_data['end_date']=$data['end_date'];
                        $insert_data['image']=$data['image'];
                        $insert_data['description']=$data['description'];
                        $insert_data['longitude']=$data['longitude'];
                        $insert_data['latitude']=$data['latitude'];
                        $insert_data['type']=$data['type'];
                        if($id!=''){
                            $save=$builder->where('id',$id)->update($insert_data);
                        }else{
                            $save=$builder->insert($insert_data);
                        }
                        if($save){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not Updated'; 
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='user not found';
                    }
                }else{
                    $response['status']=true;
                    $response['statuscode']=400;
                    $response['message']='user_id/access_token missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='Data empty';
            }
            return $response;
        }
        public function list_all_bannerlist($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $bannerlist_data= $this->db->table('banner_list')->select('banner_list.id,banner_list.image,banner_list.type,banner_list.description,banner_list.provider_id')->where('banner_list.status',1)->get()->getResultArray();
                        // $bannerlist_data= $this->db->table('banner_list')->select('user.*,`banner_list`.*')->where('banner_list.status',1)->join('user','user.id=banner_list.user_id','left')->get()->getRowArray();
                        if(!empty($bannerlist_data)){
                            foreach ($bannerlist_data as $key => $value) {
                                if($bannerlist_data[$key]['image']!=''){
                                    $bannerlist_data[$key]['image']= base_url().'/'.$bannerlist_data[$key]['image'];
                                }else{
                                    $bannerlist_data[$key]['image']='';
                                }
                                //get access_id data
                                // $bannerlist_data[$key]['access_id']=$this->get_access_name($bannerlist_data[$key]['access_id']);
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$bannerlist_data;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function list_all_doctors($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $doctor_list= $this->db->table('user')->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                        if(!empty($doctor_list)){
                            $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                            foreach ($doctor_list as $key => $value) {
                                if($doctor_list[$key]['profile_pic']!=''){
                                    $doctor_list[$key]['profile_pic']= base_url().'/'.$doctor_list[$key]['profile_pic'];
                                }else{
                                    $doctor_list[$key]['profile_pic']='';
                                }
                                $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getRowArray();
                                if(!empty($experience)){
                                    if(isset($experience['experience'])){
                                        $doctor_list[$key]['experience']=$experience['experience'];
                                    }else{
                                        $doctor_list[$key]['experience']=0;
                                    }
                                }else{
                                    $doctor_list[$key]['experience']=0;
                                }
                                if(!empty($favourite_doctor_id)){
                                    if($favourite_doctor_id['doctor_id']==$value['doctor_id']){
                                        $doctor_list[$key]['favourite_doctor_status']=1;
                                    }else{
                                        $doctor_list[$key]['favourite_doctor_status']=0;
                                    }
                                }else{
                                    $doctor_list[$key]['favourite_doctor_status']=0;
                                }
                                $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.doctor_id',$value['doctor_id'])->get()->getRowArray();
                                if(!empty($favourite_doctor_id)){
                                    if($favourite_doctor_id['doctor_id']==$value['doctor_id']){
                                        $doctor_list[$key]['favourite_doctor_status']=1;
                                    }else{
                                        $doctor_list[$key]['favourite_doctor_status']=0;
                                    }
                                }else{
                                    $doctor_list[$key]['favourite_doctor_status']=0;
                                }
                                if(!empty($family_doctor_id)){
                                    if($family_doctor_id['family_doctor_id']==$value['doctor_id']){
                                        $doctor_list[$key]['family_doctor_status']=1;
                                    }else{
                                        $doctor_list[$key]['family_doctor_status']=0;
                                    }
                                }else{
                                    $doctor_list[$key]['family_doctor_status']=0;
                                }
                                $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.
                                    *')->where('doctor_id',$value['doctor_id'])->where('status',1)->where('working_status',1)->get()->getResultArray();
                                $organisation_array=[];
                                $organ_str="";
                                foreach ($organisation as $oky => $val) {
                                    if($oky!=0){
                                        $organ_str=$organ_str.',';
                                    }
                                    $organ_str=$organ_str.$val['hospital_name'];
                                }
                                $doctor_list[$key]['organisation']=$organ_str;
                                $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getResultArray();
                                $specialization_array=[];
                                $special_str="";
                                foreach ($specialization as $ke => $va) {
                                    if($ke!=0){
                                        $special_str=$special_str.',';
                                    }
                                    $special_str=$special_str.$va['specialization'];
                                }
                                $doctor_list[$key]['specialization']=$special_str;
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$doctor_list;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function doctor_available_slots($data=''){
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
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        if($doctor_id!=''){  
                            $result_array=array();
                            $current_datetime=$this->current_datetime;
                            $doctor_slot= $this->db->table('doctor_slot')->select('doctor_slot.*,doctor_slot_master.user_id as doctor_id')->where('doctor_slot_master.user_id',$doctor_id)->where('doctor_slot_master.status',1)->where('doctor_slot.status',1)->where('doctor_slot.start_datetime>=',$current_datetime)->join('doctor_slot_master','doctor_slot_master.id=doctor_slot.master_id','left')->orderBy('doctor_slot.start_datetime','asc')->get()->getResultArray();
                            if(!empty($doctor_slot)){
                                foreach ($doctor_slot as $key => $value) {
                                    $doctor_slot[$key]['booked_status']='1';
                                    $booked_slots= $this->db->table('book_slot')->select('book_slot.*')->where('book_slot.doctor_slot_id',$value['id'])->where('book_slot.doctor_id',$value['doctor_id'])->where('book_slot.status',1)->get()->getResultArray();
                                    if(!empty($booked_slots)){
                                        unset($doctor_slot[$key]);
                                    }
                                    if(isset($doctor_slot[$key])){
                                        array_push($result_array,$doctor_slot[$key]);
                                    }
                                }
                                    // var_dump($doctor_slot);
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $response['data']=$result_array;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='No data found';
                                $response['data']=[];
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Doctor_id Missing';
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
                    $response['message']='Data is Empty';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No Input data found';
                $response['data']=[];
            }
            return $response;
        }
        public function edit_patient_profile($data='') {
            //update the data to employee details and family_member
            $updated_datetime= $this->current_datetime;
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
                $relation='self';
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
            if(isset($data['profile_pic_path'])){
                $profile_pic_path=$data['profile_pic_path'];
            }else{
                $profile_pic_path='';
            }
            //update user name in user table
            $user_table_data['username']=$name;
            $user_table_data['email']=$email;
            $update_user_table=$this->db->table('user')->where('id',$user_id)->update($user_table_data);
            if($update_user_table){
                $employee_data['updated_by']= $user_id;
                $employee_data['gender']=$gender;
                $employee_data['email']=$email;
                $employee_data['profile_pic']=$profile_pic_path;
                $save_employee_data= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($employee_data);
                if($save_employee_data){
                    //insert in family member table
                    $family_member_data['email_id']=$email;
                    $family_member_data['username']=$name;
                    $family_member_data['profile_pic']=$profile_pic_path;
                    $family_member_data['updated_datetime']=$this->current_datetime;
                    $family_member_data['relation']=$relation;
                    $family_member_data['dob']=$dob;
                    $family_member_data['gender']=$gender;
                    $family_member_data['blood_group']=$blood_group;
                    $family_member_data['height']=$height;
                    $family_member_data['weight']=$weight;
                    $insert_family_member=$this->db->table('family_member')->where('user_id',$user_id)->where('default_status',1)->update($family_member_data);
                    if($insert_family_member){
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='Success';
                        //get family member details
                        // $last_inserted_id= $this->db->insertID();
                        $get_data= $this->db->table('family_member')->select('*')->where('user_id',$user_id)->where('default_status',1)->get()->getRowArray();
                        $response['data']=$get_data;
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Not Updated in Family Member db';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='Not Updated in Employee Basic Details db';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=400;
                $response['message']='Not updated in User db';
            }
            return $response;
        }
        public function datewise_patient_book_slot($data=''){
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
                    $fromdatetime=$data['date'].' 00:00:00';
                }else{
                    $fromdatetime='';
                }
                if(isset($data['date'])){
                    $todatetime=$data['date'].' 23:59:59';
                }else{
                    $todatetime='';
                }
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $book_slot_data= $this->db->table('book_slot')->select('`book_slot`.*,user.username as patient_name')->where('book_slot.user_id',$user_id)->where('book_slot.status',1)->where('book_slot.booked_for>=',$fromdatetime)->where('book_slot.booked_for<=',$todatetime)->join('user','user.id=book_slot.doctor_id','left')->get()->getResultArray();
                        if(!empty($book_slot_data)){
                            foreach ($book_slot_data as $key => $value) {
                                $user_family_data= $this->db->table('family_member')->select('family_member.username as family_member_name')->where('family_member.id',$value['family_member_id'])->where('family_member.user_id',$value['user_id'])->get()->getRowArray();
                                $user_doctor_data= $this->db->table('user')->select('user.username as doctor_name')->where('user.id',$value['doctor_id'])->get()->getRowArray();
                                if(!empty($user_family_data)){
                                    $book_slot_data[$key]['family_member_name']=$user_family_data['family_member_name'];                                
                                }
                                if(!empty($user_doctor_data) && $value['doctor_id']!=0){
                                    $book_slot_data[$key]['doctor_name']=$user_doctor_data['doctor_name'];
                                }else{
                                    $book_slot_data[$key]['doctor_name']='';
                                }
                                unset($book_slot_data[$key]['medical_report']);
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$book_slot_data;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function upload_doctor_medical_history($data=''){
            if(!empty($data)){
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                    // unset($data['doctor_id']);
                }else{
                    $doctor_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['patient_id'])){
                    $patient_id=$data['patient_id'];
                }else{
                    $patient_id='';
                }
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                if(isset($data['description'])){
                    $description=$data['description'];
                }else{
                    $description='';
                }
                if(isset($data['report_path'])){
                    $report_path=$data['report_path'];
                }else{
                    $report_path='';
                }
                if($doctor_id!="" && $access_token!="" && $report_path!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $data['added_datetime']=$this->current_datetime;
                        $builder = $this->db->table('doctor_medical_history');
                        $save=$builder->insert($data);
                        if($save){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Inserted Successfully';
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not Inserted in db';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function list_doctor_medical_history($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $doctor_medical_history= $this->db->table('doctor_medical_history')->select('doctor_medical_history.*,user.username as patient_name')->where('doctor_medical_history.patient_id',$user_id)->join('user','user.id=doctor_medical_history.patient_id','left')->get()->getResultArray();
                        if(!empty($doctor_medical_history)){
                            foreach ($doctor_medical_history as $key => $value) {
                                if($doctor_medical_history[$key]['report_path']!=''){
                                    $doctor_medical_history[$key]['report_path']= base_url().'/'.$doctor_medical_history[$key]['report_path'];
                                }else{
                                    $doctor_medical_history[$key]['report_path']='';
                                }
                                $user_doctor_data= $this->db->table('user')->select('user.username as doctor_name')->where('user.id',$value['doctor_id'])->get()->getRowArray();
                                $doctor_medical_history[$key]['doctor_name']=$user_doctor_data['doctor_name'];
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$doctor_medical_history;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='user_id/Access_token is Empty';
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
        public function patient_book_slot_history($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $patient_details= $this->db->table('family_member')->select('family_member.id')->where('family_member.user_id',$user_id)->where('family_member.default_status',1)->get()->getRowArray();
                        if(isset($data['family_member_id'])){
                            if($data['family_member_id']!=0){
                                $family_member_id=$data['family_member_id'];
                            }else{
                                $family_member_id=$patient_details['id'];
                            }
                        }else{
                            $family_member_id=$patient_details['id'];
                        }
                        $book_slot_data= $this->db->table('book_slot')->select('`book_slot`.id,`book_slot`.sick_notes,`book_slot`.booked_datetime,`book_slot`.visit_type,`book_slot`.booked_for,`book_slot`.booking_id,`book_slot`.prescription,`book_slot`.doctor_id')->where('book_slot.user_id',$user_id)->where('book_slot.family_member_id',$family_member_id)->where('book_slot.status',1)->join('user','user.id=book_slot.doctor_id','left')->get()->getResultArray();
                        if(!empty($book_slot_data)){
                            foreach ($book_slot_data as $key => $value) {
                                $user_doctor_data= $this->db->table('user')->select('user.username as doctor_name')->where('user.id',$value['doctor_id'])->get()->getRowArray();
                                if(!empty($user_doctor_data)){
                                    $book_slot_data[$key]['doctor_name']=$user_doctor_data['doctor_name'];
                                }
                                unset($book_slot_data[$key]['doctor_id']);
                                unset($book_slot_data[$key]['medical_report']);
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$book_slot_data;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function patient_book_slot_history11($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $book_slot_data= $this->db->table('book_slot')->select('`book_slot`.*,user.username as patient_name')->where('book_slot.user_id',$user_id)->where('book_slot.status',1)->join('user','user.id=book_slot.doctor_id','left')->get()->getResultArray();
                        if(!empty($book_slot_data)){
                            foreach ($book_slot_data as $key => $value) {
                                $user_family_data= $this->db->table('family_member')->select('family_member.username as family_member_name')->where('family_member.id',$value['family_member_id'])->where('family_member.user_id',$value['user_id'])->get()->getRowArray();
                                $user_doctor_data= $this->db->table('user')->select('user.username as doctor_name')->where('user.id',$value['doctor_id'])->get()->getRowArray();
                                // var_dump($user_family_data,$user_doctor_data);
                                $book_slot_data[$key]['family_member_name']=$user_family_data['family_member_name'];
                                $book_slot_data[$key]['doctor_name']=$user_doctor_data['doctor_name'];
                                unset($book_slot_data[$key]['medical_report']);
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$book_slot_data;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function otp($data=''){
            if(!empty($data)){
                if(isset($data['mobile'])){
                    $mobile=$data['mobile'];
                }else{
                    $mobile='';
                }
                $get_otp_data= $this->db->table('user')->select('*')->where('mobile',$mobile)->where('status',1)->get()->getResultArray();
                if(!empty($get_otp_data)){
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='Success';
                    $response['data']=$get_otp_data;
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No otp data found';
                }
                return $response;
            }
        }
        public function add_lab_refferals($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['book_slot_id'])){
                    $book_slot_id=$data['book_slot_id'];
                }else{
                    $book_slot_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!='' && $access_token!='' ){
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $builder = $this->db->table('book_slot');
                        $update_data['refferal_id']=$this->db->insertID();
                        $save=$builder->where('id',$book_slot_id)->update($update_data);
                        // $data['created_datetime']=$this->current_datetime;
                        $builder = $this->db->table('referrals');
                        $save=$builder->insert($data);
                        if($save){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success'; 
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Error'; 
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='User Not Found';
                        $response['data']=[];
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='UserID/Access_token Missing';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
                $response['data']=[];
            }
            return $response;
        }
        public function get_default_image($id=''){
            $builder = $this->db->table('default_data');
            $builder->select('*');
            $get_access_type= $builder->get()->getRowArray();
            return base_url().$get_access_type['file_path'];
        }
        public function list_all_specialization($data=''){
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
                        $employee_specialization= $this->db->table('specialization')->select('specialization.specialization,specialization.image_path,specialization.id as specialization_id')->where('status',1)->get()->getResultArray();
                        foreach ($employee_specialization as $key => $value) {
                            if($employee_specialization[$key]['image_path']!=''){
                                $employee_specialization[$key]['image_path']= base_url().'/'.$employee_specialization[$key]['image_path'];
                            }else{
                                $employee_specialization[$key]['image_path']=base_url().'/'.$this->default_img;
                            }
                        }
                        if(!empty($employee_specialization)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$employee_specialization;
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
        public function family_doctor_history($data=''){
            if(!empty($data)){
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                    unset($data['doctor_id']);
                }else{
                    $doctor_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($doctor_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $family_doctor= $this->db->table('user')->select('user.username,user.id as patient_id,employee_basic_details.profile_pic')->where('user.family_doctor_id',$doctor_id)->where('user.status',1)->join('employee_basic_details','user.id=employee_basic_details.user_id','left')->groupBy('user.id')->get()->getResultArray();
                        if(!empty($family_doctor)){
                            foreach ($family_doctor as $key => $value) {
                                if(!empty($family_doctor[$key])){
                                    if($family_doctor[$key]['profile_pic']!=''){
                                        $family_doctor[$key]['profile_pic']= base_url().'/'.$family_doctor[$key]['profile_pic'];
                                    }else{
                                        $family_doctor[$key]['profile_pic']=base_url().'/'.$this->default_img;
                                    }
                                }
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$family_doctor;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function doctor_upcomming_appoinments($data=''){
            if(!empty($data)){
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($doctor_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        if($doctor_id!=''){  
                            $result_array=array();
                            $current_datetime=$this->current_datetime;
                            $book_slot= $this->db->table('book_slot')->select('book_slot.*,user.username as patient_name,family_member.username as family_member_name,user.mobile as patient_num, employee_basic_details.profile_pic, employee_basic_details.email')->where('book_slot.doctor_id',$doctor_id)->where('book_slot.status',1)->where('book_slot.booked_for>=',$current_datetime)->join('user','user.id=book_slot.user_id','left')->join('employee_basic_details','employee_basic_details.user_id=book_slot.user_id','left')->join('family_member','family_member.id=book_slot.family_member_id','left')->groupBy('book_slot.id')->orderBy('book_slot.booked_for','asc')->get()->getResultArray();
                            // var_dump($this->db->getLastQuery());
                            if(!empty($book_slot)){
                                foreach ($book_slot as $key => $value) {
                                    if($book_slot[$key]['profile_pic']!=''){
                                        $book_slot[$key]['profile_pic']= base_url().'/'.$book_slot[$key]['profile_pic'];
                                    }else{
                                        $book_slot[$key]['profile_pic']='';
                                    }
                                }
                                    // var_dump($doctor_slot);
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $response['data']=$book_slot;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='No data found';
                                $response['data']=[];
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Doctor_id Missing';
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
                    $response['message']='Data is Empty';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No Input data found';
                $response['data']=[];
            }
            return $response;
        } 
        public function doctor_new_appoinments($data=''){
            if(!empty($data)){
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($doctor_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        if($doctor_id!=''){  
                            $result_array=array();
                            $current_datetime=date('Y-m-d').' 00:00:00';
                            // $current_datetime=$this->current_datetime;
                            $book_slot= $this->db->table('book_slot')->select('book_slot.*,user.username as patient_name,family_member.username as family_member_name,user.mobile as patient_num, employee_basic_details.profile_pic')->where('book_slot.doctor_id',$doctor_id)->where('book_slot.status',1)->where('book_slot.booked_datetime>=',$current_datetime)->join('user','user.id=book_slot.user_id','left')->join('employee_basic_details','employee_basic_details.user_id=book_slot.user_id','left')->join('family_member','family_member.id=book_slot.family_member_id','left')->groupBy('book_slot.id')->orderBy('book_slot.booked_datetime','asc')->get()->getResultArray();
                            // var_dump($this->db->getLastQuery());
                            if(!empty($book_slot)){
                                foreach ($book_slot as $key => $value) {
                                    if($book_slot[$key]['profile_pic']!=''){
                                        $book_slot[$key]['profile_pic']= base_url().'/'.$book_slot[$key]['profile_pic'];
                                    }else{
                                        $book_slot[$key]['profile_pic']='';
                                    }
                                }
                                    // var_dump($doctor_slot);
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $response['data']=$book_slot;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='No data found';
                                $response['data']=[];
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Doctor_id Missing';
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
                    $response['message']='Data is Empty';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No Input data found';
                $response['data']=[];
            }
            return $response;
        } 
        public function locationwise_limited_hospitals($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        if($latitude!='' && $longitude!=''){
                            $hospital_list= $this->db->table('user')->select('user.id,user.username as hospital_name')->where('user.access_id',2)->where('user.latitude',$latitude)->where('user.longitude',$longitude)->orderBy('user.id','asc')->groupBy('user.id')->get()->getResultArray();
                            if(!empty($hospital_list)){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='location wise doctors';
                                $response['data']=$hospital_list;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['data']= $this->db->table('user')->select('user.id,user.username as hospital_name')->where('user.access_id',2)->orderBy('user.id','asc')->groupBy('user.id')->get()->getResultArray();
                                $response['message']='List of all Doctors';
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['data']= $this->db->table('user')->select('user.id,user.username as hospital_name')->where('user.access_id',2)->orderBy('user.id','asc')->groupBy('user.id')->get()->getResultArray();
                            $response['message']='List of all Doctors';
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
                    $response['message']='Data is Empty';
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
        public function locationwise_limited_pharmacy($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        if($latitude!='' && $longitude!=''){
                            $pharmacy_list= $this->db->table('user')->select('user.id,user.username as pharmacy_name')->where('user.access_id',4)->where('user.latitude',$latitude)->where('user.longitude',$longitude)->orderBy('user.id','asc')->groupBy('user.id')->limit(6)->get()->getResultArray();
                            if(!empty($pharmacy_list)){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='location Wise Pharmacy';
                                $response['data']=$pharmacy_list;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='List of all Doctors';
                                $response['data']=$this->db->table('user')->select('user.id,user.username as pharmacy_name')->where('user.access_id',2)->orderBy('user.id','asc')->groupBy('user.id')->get()->getResultArray();
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['data']= $this->db->table('user')->select('user.id,user.username as pharmacy_name')->where('user.access_id',2)->orderBy('user.id','asc')->groupBy('user.id')->get()->getResultArray();
                            $response['message']='List of all Doctors';
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
                    $response['message']='Data is Empty';
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
        public function doctors_my_appoinment($data=''){
            if(!empty($data)){
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                $current_datetime=$this->current_datetime;
                // var_dump($doctor_id);
                if($doctor_id!='' && $access_token!='' && $current_datetime!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        // $get_slots= $this->db->query("SELECT t2.id,t1.id as master_id,t2.consulting_type,t2.start_datetime, t2.end_datetime,t2.status FROM `doctor_slot_master` t1 INNER JOIN `doctor_slot` t2 on t1.id=t2.master_id where t1.start_datetime>='".$current_datetime."' and t1.user_id='".$doctor_id."' and t1.status='1'")->getResultArray();
                        $get_slots= $this->db->table('doctor_slot')->select('doctor_slot.start_datetime,doctor_slot.end_datetime,doctor_slot.consulting_type,doctor_slot.id')->where('doctor_slot_master.user_id',$doctor_id)->where('doctor_slot_master.status',1)->where('doctor_slot.status',1)->where('doctor_slot.start_datetime>=',$current_datetime)->join('doctor_slot_master','doctor_slot_master.id=doctor_slot.master_id','left')->orderBy('doctor_slot.start_datetime','asc')->get()->getResultArray();
                        // var_dump($this->db->getLastQuery());
                        if(!empty($get_slots)){
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
        public function doctor_available_status($data=''){
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
            if(isset($data['available_status'])){
                $available_status=$data['available_status'];
            }else{
                $available_status='';
            }
            //check user is valid
            $check_user_data=$this->check_user_detail($user_id,$access_token);
            if($check_user_data['status']){
                // $add_doctor['datetime']=$this->current_datetime;
                $data['available_status']=$available_status;
                $insert_data= $this->db->table('user')->where('id',$user_id)->update($data);
                // $insert_data=$this->db->table('user')->update($add_status);
                if($insert_data){
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
            return $response;
        }
        public function list_doctor_available_status($data=''){
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
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        $get_doctor_status= $this->db->table('user')->select('available_status')->where('id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($get_doctor_status)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            $response['data']=$get_doctor_status; 
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='no data found'; 
                            $response['data']=array(); 
                        }
                    }else{
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='no user found'; 
                        }
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='user_id/access_token missing'; 
                }
            }else{
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='no input data found'; 
            }
            return $response;
        }
        public function doctors_appoinment_history($data=''){
            if(!empty($data)){
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($doctor_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($doctor_id, $access_token);
                    if($check_user['status']){
                        if($doctor_id!=''){  
                            $result_array=array();
                            $current_datetime=$this->current_datetime;
                            $book_slot= $this->db->table('book_slot')->select('book_slot.*,user.username as patient_name,family_member.username as family_member_name,user.mobile as patient_num, employee_basic_details.profile_pic')->where('book_slot.doctor_id',$doctor_id)->where('book_slot.status',1)->join('user','user.id=book_slot.user_id','left')->join('employee_basic_details','employee_basic_details.user_id=book_slot.user_id','left')->join('family_member','family_member.id=book_slot.family_member_id','left')->orderBy('book_slot.booked_for','asc')->get()->getResultArray();
                            // var_dump($this->db->getLastQuery());
                            if(!empty($book_slot)){
                                foreach ($book_slot as $key => $value) {
                                    if($book_slot[$key]['profile_pic']!=''){
                                        $book_slot[$key]['profile_pic']= base_url().'/'.$book_slot[$key]['profile_pic'];
                                    }else{
                                        $book_slot[$key]['profile_pic']='';
                                    }
                                }
                                    // var_dump($doctor_slot);
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $response['data']=$book_slot;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='No data found';
                                $response['data']=[];
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Doctor_id Missing';
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
                    $response['message']='Data is Empty';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No Input data found';
                $response['data']=[];
            }
            return $response;
        }
        public function list_all_doctors_filter($data=''){
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
                if(isset($data['search'])){
                    $search=$data['search'];
                }else{
                    $search='';
                }
                if($user_id!='' && $access_token!='' ){
                    $resulting_array=array();
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    $patient_details= $this->db->table('family_member')->select('family_member.id')->where('family_member.user_id',$user_id)->where('family_member.default_status',1)->get()->getRowArray();
                    if(isset($data['family_member_id'])){
                        if($data['family_member_id']!=0){
                            $family_member_id=$data['family_member_id'];
                        }else{
                            $family_member_id=$patient_details['id'];
                        }
                    }else{
                        $family_member_id=$patient_details['id'];
                    }
                    if($check_user['status']){
                        $user_data=$check_user['data'];
                        $longitude=$user_data['longitude'];
                        $latitude=$user_data['latitude'];
                        $builder=$this->db->table('user');
                        if($latitude!=''){
                            $builder=$builder->where('user.latitude',$latitude);
                        }
                        if($longitude!=''){
                            $builder=$builder->where('user.longitude',$longitude);
                        }
                        if($search!=''){
                            $builder=$builder->like('user.username',$search);
                        }
                        $res= $builder->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                        $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                        foreach ($res as $main_key => $main_value) {
                            if($res[$main_key]['profile_pic']!=''){
                                $res[$main_key]['profile_pic']= base_url().'/'.$res[$main_key]['profile_pic'];
                            }else{
                                $res[$main_key]['profile_pic']='';
                            }
                            $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$main_value['doctor_id'])->where('status',1)->get()->getRowArray();
                            if(!empty($experience)){
                                if(isset($experience['experience'])){
                                    $res[$main_key]['experience']=$experience['experience'];
                                }else{
                                    $res[$main_key]['experience']=0;
                                }
                            }else{
                                $res[$main_key]['experience']=0;
                            }
                            $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.doctor_id',$main_value['doctor_id'])->where('favourite_doctors.family_member_id',$family_member_id)->get()->getRowArray();
                            if(!empty($favourite_doctor_id)){
                                if($favourite_doctor_id['doctor_id']==$main_value['doctor_id']){
                                    $res[$main_key]['favourite_doctor_status']="1";
                                }else{
                                    $res[$main_key]['favourite_doctor_status']="0";
                                }
                            }else{
                                $res[$main_key]['favourite_doctor_status']="0";
                            }
                            if(!empty($family_doctor_id)){
                                if($family_doctor_id['family_doctor_id']==$main_value['doctor_id']){
                                    $res[$main_key]['family_doctor_status']="1";
                                }else{
                                    $res[$main_key]['family_doctor_status']="0";
                                }
                            }else{
                                $res[$main_key]['family_doctor_status']="0";
                            }
                            $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as hospital_name')->where('doctor_id',$main_value['doctor_id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                            $organisation_array=[];
                            $organ_str="";
                            foreach ($organisation as $oky => $val) {
                                if($oky!=0){
                                    $organ_str=$organ_str.',';
                                }
                                $organ_str=$organ_str.$val['hospital_name'];
                            }
                            $res[$main_key]['organisation']=$organ_str;
                            $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$main_value['doctor_id'])->where('status',1)->get()->getResultArray();
                            $specialization_array=[];
                            $special_str="";
                            foreach ($specialization as $ke => $va) {
                                if($ke!=0){
                                    $special_str=$special_str.',';
                                }
                                $special_str=$special_str.$va['specialization'];
                            }
                            $res[$main_key]['specialization']=$special_str;
                            array_push($resulting_array,array("doctor_id"=>$res[$main_key]['doctor_id'],"doctor_name"=>$res[$main_key]['doctor_name'],"profile_pic"=>$res[$main_key]['profile_pic'],"experience"=>$res[$main_key]['experience'],"favourite_doctor_status"=>$res[$main_key]['favourite_doctor_status'],"family_doctor_status"=>$res[$main_key]['family_doctor_status'],"organisation"=>$res[$main_key]['organisation'],"specialization"=>$res[$main_key]['specialization']));
                        }
                        // var_dump($res);
                        $builder1=$this->db->table('user');
                        if($latitude!=''){
                            $builder1=$builder1->where('user.latitude!=',$latitude);
                        }
                        if($longitude!=''){
                            $builder1=$builder1->where('user.longitude!=',$longitude);
                        }
                        if($search!=''){
                            $builder1=$builder1->like('user.username',$search);
                        }
                        $all_doctor_list= $builder1->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                        $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                        foreach ($all_doctor_list as $dtr_key => $dtr_value) {
                            if($all_doctor_list[$dtr_key]['profile_pic']!=''){
                                $all_doctor_list[$dtr_key]['profile_pic']= base_url().'/'.$all_doctor_list[$dtr_key]['profile_pic'];
                            }else{
                                $all_doctor_list[$dtr_key]['profile_pic']='';
                            }
                            $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$dtr_value['doctor_id'])->where('status',1)->get()->getRowArray();
                            if(!empty($experience)){
                                if(isset($experience['experience'])){
                                    $all_doctor_list[$dtr_key]['experience']=$experience['experience'];
                                }else{
                                    $all_doctor_list[$dtr_key]['experience']=0;
                                }
                            }else{
                                $all_doctor_list[$dtr_key]['experience']=0;
                            }
                            $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.doctor_id',$dtr_value['doctor_id'])->where('favourite_doctors.family_member_id',$family_member_id)->get()->getRowArray();
                            if(!empty($favourite_doctor_id)){
                                if($favourite_doctor_id['doctor_id']==$dtr_value['doctor_id']){
                                    $all_doctor_list[$dtr_key]['favourite_doctor_status']="1";
                                }else{
                                    $all_doctor_list[$dtr_key]['favourite_doctor_status']="0";
                                }
                            }else{
                                $all_doctor_list[$dtr_key]['favourite_doctor_status']="0";
                            }
                            if(!empty($family_doctor_id)){
                                if($family_doctor_id['family_doctor_id']==$dtr_value['doctor_id']){
                                    $all_doctor_list[$dtr_key]['family_doctor_status']="1";
                                }else{
                                    $all_doctor_list[$dtr_key]['family_doctor_status']="0";
                                }
                            }else{
                                $all_doctor_list[$dtr_key]['family_doctor_status']="0";
                            }
                            $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*, user.username as hospital_name')->where('doctor_id',$dtr_value['doctor_id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                            $organisation_array=[];
                            $organ_str="";
                            foreach ($organisation as $oky => $val) {
                                if($oky!=0){
                                    $organ_str=$organ_str.',';
                                }
                                $organ_str=$organ_str.$val['hospital_name'];
                            }
                            $all_doctor_list[$dtr_key]['organisation']=$organ_str;
                            $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$dtr_value['doctor_id'])->where('status',1)->get()->getResultArray();
                            $specialization_array=[];
                            $special_str="";
                            foreach ($specialization as $ke => $va) {
                                if($ke!=0){
                                    $special_str=$special_str.',';
                                }
                                $special_str=$special_str.$va['specialization'];
                            }
                            $all_doctor_list[$dtr_key]['specialization']=$special_str;
                        }
                        if (!empty($res)) {
                            foreach ($res as $key => $value) {
                                if(!empty($all_doctor_list)){
                                    foreach ($all_doctor_list as $adlkey => $adlval) {
                                        if($value['doctor_id']!=$adlval['doctor_id']){
                                            array_push($resulting_array,array("doctor_id"=>$all_doctor_list[$adlkey]['doctor_id'],"doctor_name"=>$all_doctor_list[$adlkey]['doctor_name'],"profile_pic"=>$all_doctor_list[$adlkey]['profile_pic'],"experience"=>$all_doctor_list[$adlkey]['experience'],"favourite_doctor_status"=>$all_doctor_list[$adlkey]['favourite_doctor_status'],"family_doctor_status"=>$all_doctor_list[$adlkey]['family_doctor_status'],"organisation"=>$all_doctor_list[$adlkey]['organisation'],"specialization"=>$all_doctor_list[$adlkey]['specialization']));
                                        }
                                        if($value['doctor_id']==$adlval['doctor_id']){
                                            unset($all_doctor_list[$adlkey]);
                                        }
                                    }
                                        array($all_doctor_list);
                                }
                            }
                        }else{
                            if(!empty($all_doctor_list)){
                                foreach ($all_doctor_list as $adlkey => $adlval) {
                                    array_push($resulting_array,array("doctor_id"=>$all_doctor_list[$adlkey]['doctor_id'],"doctor_name"=>$all_doctor_list[$adlkey]['doctor_name'],"profile_pic"=>$all_doctor_list[$adlkey]['profile_pic'],"experience"=>$all_doctor_list[$adlkey]['experience'],"favourite_doctor_status"=>$all_doctor_list[$adlkey]['favourite_doctor_status'],"family_doctor_status"=>$all_doctor_list[$adlkey]['family_doctor_status'],"organisation"=>$all_doctor_list[$adlkey]['organisation'],"specialization"=>$all_doctor_list[$adlkey]['specialization']));
                                }
                            }
                        }
                        // var_dump($all_doctor_list);
                        if(!empty($all_doctor_list)){
                            array_push($res,$all_doctor_list);
                        }
                        $lon_build=$this->db->table('user');
                        if($latitude!=''){
                            $lon_build=$lon_build->where('user.latitude!=',$latitude);
                        }
                        if($longitude!=''){
                            $lon_build=$lon_build->where('user.longitude',$longitude);
                        }
                        if($search!=''){
                            $lon_build=$lon_build->like('user.username',$search);
                        }
                        $lon_doctor_list= $lon_build->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                        $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                        foreach ($lon_doctor_list as $lon_key => $lon_value) {
                            if($lon_doctor_list[$lon_key]['profile_pic']!=''){
                                $lon_doctor_list[$lon_key]['profile_pic']= base_url().'/'.$lon_doctor_list[$lon_key]['profile_pic'];
                            }else{
                                $lon_doctor_list[$lon_key]['profile_pic']='';
                            }
                            $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$lon_value['doctor_id'])->where('status',1)->get()->getRowArray();
                            if(!empty($experience)){
                                if(isset($experience['experience'])){
                                    $lon_doctor_list[$lon_key]['experience']=$experience['experience'];
                                }else{
                                    $lon_doctor_list[$lon_key]['experience']=0;
                                }
                            }else{
                                $lon_doctor_list[$lon_key]['experience']=0;
                            }
                            $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.doctor_id',$lon_value['doctor_id'])->where('favourite_doctors.family_member_id',$family_member_id)->get()->getRowArray();
                            if(!empty($favourite_doctor_id)){
                                if($favourite_doctor_id['doctor_id']==$lon_value['doctor_id']){
                                    $lon_doctor_list[$lon_key]['favourite_doctor_status']="1";
                                }else{
                                    $lon_doctor_list[$lon_key]['favourite_doctor_status']="0";
                                }
                            }else{
                                $lon_doctor_list[$lon_key]['favourite_doctor_status']="0";
                            }
                            if(!empty($family_doctor_id)){
                                if($family_doctor_id['family_doctor_id']==$lon_value['doctor_id']){
                                    $lon_doctor_list[$lon_key]['family_doctor_status']="1";
                                }else{
                                    $lon_doctor_list[$lon_key]['family_doctor_status']="0";
                                }
                            }else{
                                $lon_doctor_list[$lon_key]['family_doctor_status']="0";
                            }                            
                            $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*, user.username as hospital_name')->where('doctor_id',$lon_value['doctor_id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                            $organisation_array=[];
                            $organ_str="";
                            foreach ($organisation as $oky => $val) {
                                if($oky!=0){
                                    $organ_str=$organ_str.',';
                                }
                                $organ_str=$organ_str.$val['hospital_name'];
                            }
                            $lon_doctor_list[$lon_key]['organisation']=$organ_str;
                            $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$lon_value['doctor_id'])->where('status',1)->get()->getResultArray();
                            $specialization_array=[];
                            $special_str="";
                            foreach ($specialization as $ke => $va) {
                                if($ke!=0){
                                    $special_str=$special_str.',';
                                }
                                $special_str=$special_str.$va['specialization'];
                            }
                            $lon_doctor_list[$lon_key]['specialization']=$special_str;
                        }
                        // var_dump($res);die();
                        if (!empty($res)) {
                            foreach ($res as $key => $value) {
                                if(!empty($lon_doctor_list)){
                                    foreach ($lon_doctor_list as $lonkey => $lonval) {
                                        if(isset($value['doctor_id'])){
                                            if($value['doctor_id']!=$lonval['doctor_id']){
                                                array_push($resulting_array,array("doctor_id"=>$lon_doctor_list[$lonkey]['doctor_id'],"doctor_name"=>$lon_doctor_list[$lonkey]['doctor_name'],"profile_pic"=>$lon_doctor_list[$lonkey]['profile_pic'],"experience"=>$lon_doctor_list[$lonkey]['experience'],"favourite_doctor_status"=>$lon_doctor_list[$lonkey]['favourite_doctor_status'],"family_doctor_status"=>$lon_doctor_list[$lonkey]['family_doctor_status'],"organisation"=>$lon_doctor_list[$lonkey]['organisation'],"specialization"=>$lon_doctor_list[$lonkey]['specialization']));
                                            }
                                            if($value['doctor_id']==$lonval['doctor_id']){
                                                unset($lon_doctor_list[$lonkey]);
                                            }
                                        }else{
                                            array_push($resulting_array,array("doctor_id"=>$lon_doctor_list[$lonkey]['doctor_id'],"doctor_name"=>$lon_doctor_list[$lonkey]['doctor_name'],"profile_pic"=>$lon_doctor_list[$lonkey]['profile_pic'],"experience"=>$lon_doctor_list[$lonkey]['experience'],"favourite_doctor_status"=>$lon_doctor_list[$lonkey]['favourite_doctor_status'],"family_doctor_status"=>$lon_doctor_list[$lonkey]['family_doctor_status'],"organisation"=>$lon_doctor_list[$lonkey]['organisation'],"specialization"=>$lon_doctor_list[$lonkey]['specialization']));
                                        }
                                    }
                                    array($lon_doctor_list);
                                }
                            }
                        }else{
                            if(!empty($lon_doctor_list)){
                                foreach ($lon_doctor_list as $lonkey => $lonval) {
                                    array_push($resulting_array,array("doctor_id"=>$lon_doctor_list[$lonkey]['doctor_id'],"doctor_name"=>$lon_doctor_list[$lonkey]['doctor_name'],"profile_pic"=>$lon_doctor_list[$lonkey]['profile_pic'],"experience"=>$lon_doctor_list[$lonkey]['experience'],"favourite_doctor_status"=>$lon_doctor_list[$lonkey]['favourite_doctor_status'],"family_doctor_status"=>$lon_doctor_list[$lonkey]['family_doctor_status'],"organisation"=>$lon_doctor_list[$lonkey]['organisation'],"specialization"=>$lon_doctor_list[$lonkey]['specialization']));
                                }
                            }
                        }
                        // var_dump($all_doctor_list);
                        if(!empty($lon_doctor_list)){
                            array_push($res,$lon_doctor_list);
                        }
                        $lat_build=$this->db->table('user');
                        if($latitude!=''){
                            $lat_build=$lat_build->where('user.latitude',$latitude);
                        }
                        if($longitude!=''){
                            $lat_build=$lat_build->where('user.longitude!=',$longitude);
                        }
                        if($search!=''){
                            $lat_build=$lat_build->like('user.username',$search);
                        }
                        $lat_doctor_list= $lat_build->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                        $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                        foreach ($lat_doctor_list as $lat_key => $lat_value) {
                            if($lat_doctor_list[$lat_key]['profile_pic']!=''){
                                $lat_doctor_list[$lat_key]['profile_pic']= base_url().'/'.$lat_doctor_list[$lat_key]['profile_pic'];
                            }else{
                                $lat_doctor_list[$lat_key]['profile_pic']='';
                            }
                            $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$lat_value['doctor_id'])->where('status',1)->get()->getRowArray();
                            if(!empty($experience)){
                                if(isset($experience['experience'])){
                                    $lat_doctor_list[$lat_key]['experience']=$experience['experience'];
                                }else{
                                    $lat_doctor_list[$lat_key]['experience']=0;
                                }
                            }else{
                                $lat_doctor_list[$lat_key]['experience']=0;
                            }
                            $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.doctor_id',$lat_value['doctor_id'])->where('favourite_doctors.family_member_id',$family_member_id)->get()->getRowArray();
                            if(!empty($favourite_doctor_id)){
                                if($favourite_doctor_id['doctor_id']==$lat_value['doctor_id']){
                                    $lat_doctor_list[$lat_key]['favourite_doctor_status']="1";
                                }else{
                                    $lat_doctor_list[$lat_key]['favourite_doctor_status']="0";
                                }
                            }else{
                                $lat_doctor_list[$lat_key]['favourite_doctor_status']="0";
                            }
                            if(!empty($family_doctor_id)){
                                if($family_doctor_id['family_doctor_id']==$lat_value['doctor_id']){
                                    $lat_doctor_list[$lat_key]['family_doctor_status']="1";
                                }else{
                                    $lat_doctor_list[$lat_key]['family_doctor_status']="0";
                                }
                            }else{
                                $lat_doctor_list[$lat_key]['family_doctor_status']="0";
                            }
                            $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*, user.username as hospital_name')->where('doctor_id',$lat_value['doctor_id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                            $organisation_array=[];
                            $organ_str="";
                            foreach ($organisation as $oky => $val) {
                                if($oky!=0){
                                    $organ_str=$organ_str.',';
                                }
                                $organ_str=$organ_str.$val['hospital_name'];
                            }
                            $lat_doctor_list[$lat_key]['organisation']=$organ_str;
                            $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$lat_value['doctor_id'])->where('status',1)->get()->getResultArray();
                            $specialization_array=[];
                            $special_str="";
                            foreach ($specialization as $ke => $va) {
                                if($ke!=0){
                                    $special_str=$special_str.',';
                                }
                                $special_str=$special_str.$va['specialization'];
                            }
                            $lat_doctor_list[$lat_key]['specialization']=$special_str;
                        }
                        if (!empty($res)) {
                            foreach ($res as $key => $value) {
                                if(!empty($lat_doctor_list)){
                                    foreach ($lat_doctor_list as $latkey => $latval) {
                                        if($value['doctor_id']!=$latval['doctor_id']){
                                            array_push($resulting_array,array("doctor_id"=>$lat_doctor_list[$latkey]['doctor_id'],"doctor_name"=>$lat_doctor_list[$latkey]['doctor_name'],"profile_pic"=>$lat_doctor_list[$latkey]['profile_pic'],"experience"=>$lat_doctor_list[$latkey]['experience'],"favourite_doctor_status"=>$lat_doctor_list[$latkey]['favourite_doctor_status'],"family_doctor_status"=>$lat_doctor_list[$latkey]['family_doctor_status'],"organisation"=>$lat_doctor_list[$latkey]['organisation'],"specialization"=>$lat_doctor_list[$latkey]['specialization']));
                                        }
                                        if($value['doctor_id']==$latval['doctor_id']){
                                            unset($lat_doctor_list[$latkey]);
                                        }
                                    }
                                        array($lat_doctor_list);
                                }
                            }
                        }else{
                            if(!empty($lat_doctor_list)){
                                foreach ($lat_doctor_list as $latkey => $latval) {
                                    array_push($resulting_array,array("doctor_id"=>$lat_doctor_list[$latkey]['doctor_id'],"doctor_name"=>$lat_doctor_list[$latkey]['doctor_name'],"profile_pic"=>$lat_doctor_list[$latkey]['profile_pic'],"experience"=>$lat_doctor_list[$latkey]['experience'],"favourite_doctor_status"=>$lat_doctor_list[$latkey]['favourite_doctor_status'],"family_doctor_status"=>$lat_doctor_list[$latkey]['family_doctor_status'],"organisation"=>$lat_doctor_list[$latkey]['organisation'],"specialization"=>$lat_doctor_list[$latkey]['specialization']));
                                }
                            }
                        }
                        // var_dump($all_doctor_list);
                        if(!empty($lat_doctor_list)){
                            array_push($res,$lat_doctor_list);
                        }
                        if($search!=''){
                            $builder2=$this->db->table('user');
                            if($latitude!=''){
                                $builder2=$builder2->where('user.latitude!=',$latitude);
                            }
                            if($longitude!=''){
                                $builder2=$builder2->where('user.longitude!=',$longitude);
                            }
                            if($search!=''){
                                $builder2=$builder2->like('user.username',$search);
                            }
                            $all_hospital_ids= $builder2->select('user.id as hospital_id')->where('user.access_id',2)->get()->getResultArray();
                            // var_dump($this->db->getlastQuery());
                            $builders2=$this->db->table('user');
                            if($latitude!=''){
                                $builders2=$builders2->where('user.latitude',$latitude);
                            }
                            if($longitude!=''){
                                $builders2=$builders2->where('user.longitude',$longitude);
                            }
                            if($search!=''){
                                $builders2=$builders2->like('user.username',$search);
                            }
                            $all_hospital_id= $builders2->select('user.id as hospital_id')->where('user.access_id',2)->get()->getResultArray();
                            $idsarray=array();
                            if(!empty($all_hospital_ids)){
                                foreach ($all_hospital_ids as $k => $v) {
                                    array_push($idsarray,array("hospital_id"=>$v['hospital_id']));
                                    if(!empty($all_hospital_id)){
                                        foreach ($all_hospital_id as $keyid => $valueid) {
                                            if($v['hospital_id']!=$valueid['hospital_id']){
                                                array_push($idsarray,array("hospital_id"=>$valueid['hospital_id']));
                                            }
                                        }
                                        array($idsarray);
                                    }
                                }
                            }else{
                                if(!empty($all_hospital_id)){
                                    foreach ($all_hospital_id as $keyid => $valueid) {
                                        array_push($idsarray,array("hospital_id"=>$valueid['hospital_id']));
                                    }
                                    array($idsarray);
                                }
                            }
                            $build_lat=$this->db->table('user');
                            if($latitude!=''){
                                $build_lat=$build_lat->where('user.latitude',$latitude);
                            }
                            if($longitude!=''){
                                $build_lat=$build_lat->where('user.longitude!=',$longitude);
                            }
                            if($search!=''){
                                $build_lat=$build_lat->like('user.username',$search);
                            }
                            $lat_hospital_id= $build_lat->select('user.id as hospital_id')->where('user.access_id',2)->get()->getResultArray();
                            $idsarray=array();
                            if(!empty($all_hospital_ids)){
                                foreach ($all_hospital_ids as $k => $v) {
                                    array_push($idsarray,array("hospital_id"=>$v['hospital_id']));
                                    if(!empty($lat_hospital_id)){
                                        foreach ($lat_hospital_id as $keyid => $valueid) {
                                            if($v['hospital_id']!=$valueid['hospital_id']){
                                                array_push($idsarray,array("hospital_id"=>$valueid['hospital_id']));
                                            }
                                        }
                                        array($idsarray);
                                    }
                                }
                            }else{
                                if(!empty($lat_hospital_id)){
                                    foreach ($lat_hospital_id as $keyid => $valueid) {
                                        array_push($idsarray,array("hospital_id"=>$valueid['hospital_id']));
                                    }
                                    array($idsarray);
                                }
                            }
                            $build_lon=$this->db->table('user');
                            if($latitude!=''){
                                $build_lon=$build_lon->where('user.latitude!=',$latitude);
                            }
                            if($longitude!=''){
                                $build_lon=$build_lon->where('user.longitude',$longitude);
                            }
                            if($search!=''){
                                $build_lon=$build_lon->like('user.username',$search);
                            }
                            $lon_hospital_id= $build_lon->select('user.id as hospital_id')->where('user.access_id',2)->get()->getResultArray();
                            $idsarray=array();
                            if(!empty($all_hospital_ids)){
                                foreach ($all_hospital_ids as $k => $v) {
                                    array_push($idsarray,array("hospital_id"=>$v['hospital_id']));
                                    if(!empty($lon_hospital_id)){
                                        foreach ($lon_hospital_id as $keyid => $valueid) {
                                            if($v['hospital_id']!=$valueid['hospital_id']){
                                                array_push($idsarray,array("hospital_id"=>$valueid['hospital_id']));
                                            }
                                        }
                                        array($idsarray);
                                    }
                                }
                            }else{
                                if(!empty($lon_hospital_id)){
                                    foreach ($lon_hospital_id as $keyid => $valueid) {
                                        array_push($idsarray,array("hospital_id"=>$valueid['hospital_id']));
                                    }
                                    array($idsarray);
                                }
                            }
                            foreach ($idsarray as $key_ids => $value_ids) {
                                $hospitalwisedoctor_list= $this->db->table('doctor_current_organisation')->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->where('doctor_current_organisation.hospital_id',$value_ids['hospital_id'])->join('user','doctor_current_organisation.doctor_id=user.id','left')->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                                $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                                foreach ($hospitalwisedoctor_list as $hptldtr_key => $hptldtr_value) {
                                    if($hospitalwisedoctor_list[$hptldtr_key]['profile_pic']!=''){
                                        $hospitalwisedoctor_list[$hptldtr_key]['profile_pic']= base_url().'/'.$hospitalwisedoctor_list[$hptldtr_key]['profile_pic'];
                                    }else{
                                        $hospitalwisedoctor_list[$hptldtr_key]['profile_pic']='';
                                    }
                                    $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$hptldtr_value['doctor_id'])->where('status',1)->get()->getRowArray();
                                    if(!empty($experience)){
                                        if(isset($experience['experience'])){
                                            $hospitalwisedoctor_list[$hptldtr_key]['experience']=$experience['experience'];
                                        }else{
                                            $hospitalwisedoctor_list[$hptldtr_key]['experience']=0;
                                        }
                                    }else{
                                        $hospitalwisedoctor_list[$hptldtr_key]['experience']=0;
                                    }
                                    $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.family_member_id',$family_member_id)->where('favourite_doctors.doctor_id',$hptldtr_value['doctor_id'])->get()->getRowArray();
                                    if(!empty($favourite_doctor_id)){
                                        if($favourite_doctor_id['doctor_id']==$hptldtr_value['doctor_id']){
                                            $hospitalwisedoctor_list[$hptldtr_key]['favourite_doctor_status']="1";
                                        }else{
                                            $hospitalwisedoctor_list[$hptldtr_key]['favourite_doctor_status']="0";
                                        }
                                    }else{
                                        $hospitalwisedoctor_list[$hptldtr_key]['favourite_doctor_status']="0";
                                    }
                                    if(!empty($family_doctor_id)){
                                        if($family_doctor_id['family_doctor_id']==$hptldtr_value['doctor_id']){
                                            $hospitalwisedoctor_list[$hptldtr_key]['family_doctor_status']="1";
                                        }else{
                                            $hospitalwisedoctor_list[$hptldtr_key]['family_doctor_status']="0";
                                        }
                                    }else{
                                        $hospitalwisedoctor_list[$hptldtr_key]['family_doctor_status']="0";
                                    }
                                    $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as hospital_name')->where('doctor_id',$hptldtr_value['doctor_id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                                    $organisation_array=[];
                                    $organ_str="";
                                    foreach ($organisation as $oky => $val) {
                                        if($oky!=0){
                                            $organ_str=$organ_str.',';
                                        }
                                        $organ_str=$organ_str.$val['hospital_name'];
                                    }
                                    $hospitalwisedoctor_list[$hptldtr_key]['organisation']=$organ_str;
                                    $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$hptldtr_value['doctor_id'])->where('status',1)->get()->getResultArray();
                                    $specialization_array=[];
                                    $special_str="";
                                    foreach ($specialization as $ke => $va) {
                                        if($ke!=0){
                                            $special_str=$special_str.',';
                                        }
                                        $special_str=$special_str.$va['specialization'];
                                    }
                                    $hospitalwisedoctor_list[$hptldtr_key]['specialization']=$special_str;
                                }
                            }
                            if (!empty($res)) {
                                foreach ($res as $key => $value) {
                                    if(!empty($hospitalwisedoctor_list)){
                                        foreach ($hospitalwisedoctor_list as $hdkey => $hdval) {
                                            if(isset($value['doctor_id'])){
                                                if($value['doctor_id']!=$hdval['doctor_id']){
                                                    array_push($resulting_array,array("doctor_id"=>$hospitalwisedoctor_list[$hdkey]['doctor_id'],"doctor_name"=>$hospitalwisedoctor_list[$hdkey]['doctor_name'],"profile_pic"=>$hospitalwisedoctor_list[$hdkey]['profile_pic'],"experience"=>$hospitalwisedoctor_list[$hdkey]['experience'],"favourite_doctor_status"=>$hospitalwisedoctor_list[$hdkey]['favourite_doctor_status'],"family_doctor_status"=>$hospitalwisedoctor_list[$hdkey]['family_doctor_status'],"organisation"=>$hospitalwisedoctor_list[$hdkey]['organisation'],"specialization"=>$hospitalwisedoctor_list[$hdkey]['specialization']));
                                                }
                                                if($value['doctor_id']==$hdval['doctor_id']){
                                                    unset($hospitalwisedoctor_list[$hdkey]);
                                                }
                                            }
                                        }
                                        array($hospitalwisedoctor_list);
                                    }
                                }
                            }else{
                                if(!empty($hospitalwisedoctor_list)){
                                    foreach ($hospitalwisedoctor_list as $hdkey => $hdval) {
                                        array_push($resulting_array,array("doctor_id"=>$hospitalwisedoctor_list[$hdkey]['doctor_id'],"doctor_name"=>$hospitalwisedoctor_list[$hdkey]['doctor_name'],"profile_pic"=>$hospitalwisedoctor_list[$hdkey]['profile_pic'],"experience"=>$hospitalwisedoctor_list[$hdkey]['experience'],"favourite_doctor_status"=>$hospitalwisedoctor_list[$hdkey]['favourite_doctor_status'],"family_doctor_status"=>$hospitalwisedoctor_list[$hdkey]['family_doctor_status'],"organisation"=>$hospitalwisedoctor_list[$hdkey]['organisation'],"specialization"=>$hospitalwisedoctor_list[$hdkey]['specialization']));
                                    }
                                }
                            }
                            if(!empty($hospitalwisedoctor_list)){
                                array_push($res,$hospitalwisedoctor_list);
                            }
                            $spe_builder=$this->db->table('specialization');
                            if($search!=''){
                                $spe_builder=$spe_builder->like('specialization.specialization',$search);
                            }
                            $specialization_id= $spe_builder->select('specialization.id')->get()->getResultArray();
                            foreach ($specialization_id as $skey => $sval) {
                                $specializationdoctor_list= $this->db->table('employee_specialization')->select('user.id as doctor_id, user.username as doctor_name,`employee_basic_details`.`profile_pic`')->where('user.access_id',1)->where('employee_specialization.specialization_id',$sval['id'])->join('user','employee_specialization.user_id=user.id','left')->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('user.id')->orderBy('user.id','asc')->get()->getResultArray();
                                $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                                foreach ($specializationdoctor_list as $special_key => $special_value) {
                                    if($specializationdoctor_list[$special_key]['profile_pic']!=''){
                                        $specializationdoctor_list[$special_key]['profile_pic']= base_url().'/'.$specializationdoctor_list[$special_key]['profile_pic'];
                                    }else{
                                        $specializationdoctor_list[$special_key]['profile_pic']='';
                                    }
                                    $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$special_value['doctor_id'])->where('status',1)->get()->getRowArray();
                                    if(!empty($experience)){
                                        if(isset($experience['experience'])){
                                            $specializationdoctor_list[$special_key]['experience']=$experience['experience'];
                                        }else{
                                            $specializationdoctor_list[$special_key]['experience']=0;
                                        }
                                    }else{
                                        $specializationdoctor_list[$special_key]['experience']=0;
                                    }
                                    $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.family_member_id',$family_member_id)->where('favourite_doctors.doctor_id',$special_value['doctor_id'])->get()->getRowArray();
                                    if(!empty($favourite_doctor_id)){
                                        if($favourite_doctor_id['doctor_id']==$special_value['doctor_id']){
                                            $specializationdoctor_list[$special_key]['favourite_doctor_status']="1";
                                        }else{
                                            $specializationdoctor_list[$special_key]['favourite_doctor_status']="0";
                                        }
                                    }else{
                                        $specializationdoctor_list[$special_key]['favourite_doctor_status']="0";
                                    }
                                    if(!empty($family_doctor_id)){
                                        if($family_doctor_id['family_doctor_id']==$special_value['doctor_id']){
                                            $specializationdoctor_list[$special_key]['family_doctor_status']="1";
                                        }else{
                                            $specializationdoctor_list[$special_key]['family_doctor_status']="0";
                                        }
                                    }else{
                                        $specializationdoctor_list[$special_key]['family_doctor_status']="0";
                                    }
                                    $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as hospital_name')->where('doctor_id',$special_value['doctor_id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                                    $organisation_array=[];
                                    $organ_str="";
                                    foreach ($organisation as $oky => $val) {
                                        if($oky!=0){
                                            $organ_str=$organ_str.',';
                                        }
                                        $organ_str=$organ_str.$val['hospital_name'];
                                    }
                                    $specializationdoctor_list[$special_key]['organisation']=$organ_str;
                                    $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$special_value['doctor_id'])->where('status',1)->get()->getResultArray();
                                    $specialization_array=[];
                                    $special_str="";
                                    foreach ($specialization as $ke => $va) {
                                        if($ke!=0){
                                            $special_str=$special_str.',';
                                        }
                                        $special_str=$special_str.$va['specialization'];
                                    }
                                    $specializationdoctor_list[$special_key]['specialization']=$special_str;
                                }
                            }
                            if (!empty($res)) {
                                foreach ($res as $key => $value) {
                                    if(!empty($specializationdoctor_list)){
                                        foreach ($specializationdoctor_list as $sdkey => $sdval) {
                                            if(isset($value['doctor_id'])){
                                                if($value['doctor_id']!=$sdval['doctor_id']){
                                                    array_push($resulting_array,array("doctor_id"=>$specializationdoctor_list[$sdkey]['doctor_id'],"doctor_name"=>$specializationdoctor_list[$sdkey]['doctor_name'],"profile_pic"=>$specializationdoctor_list[$sdkey]['profile_pic'],"experience"=>$specializationdoctor_list[$sdkey]['experience'],"favourite_doctor_status"=>$specializationdoctor_list[$sdkey]['favourite_doctor_status'],"family_doctor_status"=>$specializationdoctor_list[$sdkey]['family_doctor_status'],"organisation"=>$specializationdoctor_list[$sdkey]['organisation'],"specialization"=>$specializationdoctor_list[$sdkey]['specialization']));
                                                }
                                                if($value['doctor_id']==$sdval['doctor_id']){
                                                    unset($specializationdoctor_list[$sdkey]);
                                                }
                                            }
                                        }
                                        array($specializationdoctor_list);
                                    }
                                }
                            }else{
                                if(!empty($specializationdoctor_list)){
                                    foreach ($specializationdoctor_list as $sdkey => $sdval) {
                                        array_push($resulting_array,array("doctor_id"=>$specializationdoctor_list[$sdkey]['doctor_id'],"doctor_name"=>$specializationdoctor_list[$sdkey]['doctor_name'],"profile_pic"=>$specializationdoctor_list[$sdkey]['profile_pic'],"experience"=>$specializationdoctor_list[$sdkey]['experience'],"favourite_doctor_status"=>$specializationdoctor_list[$sdkey]['favourite_doctor_status'],"family_doctor_status"=>$specializationdoctor_list[$sdkey]['family_doctor_status'],"organisation"=>$specializationdoctor_list[$sdkey]['organisation'],"specialization"=>$specializationdoctor_list[$sdkey]['specialization']));
                                    }
                                }
                            }
                            if(!empty($specializationdoctor_list)){
                                array_push($res,$specializationdoctor_list);
                            }
                        }
                        // var_dump($resulting_array);
                        if(!empty($resulting_array)){
                            $response['status']=true;
                            $response['statuscode']=400;
                            $response['message']='Success';
                            $response['data']=$resulting_array;
                            // $response['data']=$res;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=400;
                            $response['message']='No Doctors Found';
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
                    $response['message']='Data is Empty';
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
        public function delete_medical_history($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    if($check_user['status']==true){
                        if($id!=''){
                            $update['status']=0;   //update  data
                            $check_medical_history=$this->db->table('medical_history')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_medical_history)){    //check data exist in array
                                $data= $this->db->table('medical_history')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="Not Deleted";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="No data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Medical History Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        } 
        public function doctor_profile_details($data=''){
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
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                if($user_id!='' && $access_token!='' && $doctor_id!=''){
                    //check user is valid
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){ //all the family member with status 1
                        $get_doctor_details= $this->db->table('user')->select('user.username as doctor_name,user.id as id,employee_basic_details.profile_pic')->where('user.access_id',1)->where('user.id',$doctor_id)->where('user.status',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->get()->getRowArray();
                        $family_doctor_id=$this->db->table('user')->select('user.family_doctor_id')->where('user.status',1)->where('user.id',$user_id)->get()->getRowArray();
                        if(!empty($get_doctor_details)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            // foreach($get_doctor_details as $key => $value){
                            if($get_doctor_details['profile_pic']!=''){
                               $get_doctor_details['profile_pic']= base_url().'/'.$get_doctor_details['profile_pic']; 
                            }else{
                                $get_doctor_details['profile_pic']='';
                            }
                            $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$get_doctor_details['id'])->where('status',1)->get()->getRowArray();
                            if(!empty($experience)){
                                if(isset($experience['experience'])){
                                    $get_doctor_details['experience']=$experience['experience'];
                                }else{
                                    $get_doctor_details['experience']=0;
                                }
                            }else{
                                $get_doctor_details['experience']=0;
                            }
                            $get_doctor_details['rating']=$get_doctor_details['experience']; // default
                            $get_doctor_details['slot_available_status']=true; // default
                            $favourite_doctor_id=$this->db->table('favourite_doctors')->select('favourite_doctors.doctor_id')->where('favourite_doctors.status',1)->where('favourite_doctors.user_id',$user_id)->where('favourite_doctors.doctor_id',$get_doctor_details['id'])->where('favourite_doctors.family_member_id',$family_member_id)->get()->getRowArray();
                            if(!empty($favourite_doctor_id)){
                                if($favourite_doctor_id['doctor_id']==$get_doctor_details['id']){
                                    $get_doctor_details['favourite_doctor_status']="1";
                                }else{
                                    $get_doctor_details['favourite_doctor_status']="0";
                                }
                            }else{
                                $get_doctor_details['favourite_doctor_status']="0";
                            }
                            if(!empty($family_doctor_id)){
                                if($family_doctor_id['family_doctor_id']==$get_doctor_details['doctor_id']){
                                    $res[$main_key]['family_doctor_status']="1";
                                }else{
                                    $res[$main_key]['family_doctor_status']="0";
                                }
                            }else{
                                $res[$main_key]['family_doctor_status']="0";
                            }
                            $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as hospital_name')->where('doctor_id',$get_doctor_details['id'])->where('doctor_current_organisation.status',1)->where('working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                            $organisation_array=[];
                            $organ_str="";
                            foreach ($organisation as $oky => $val) {
                                if($oky!=0){
                                    $organ_str=$organ_str.',';
                                }
                                $organ_str=$organ_str.$val['hospital_name'];
                            }
                            $get_doctor_details['organisation']=$organ_str;
                            $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$get_doctor_details['id'])->where('status',1)->get()->getResultArray();
                            $specialization_array=[];
                            $special_str="";
                            foreach ($specialization as $ke => $va) {
                                if($ke!=0){
                                    $special_str=$special_str.',';
                                }
                                $special_str=$special_str.$va['specialization'];
                            }
                            $get_doctor_details['specialization']=$special_str;
                            $designation=$this->db->table('employee_qualification')->select('employee_qualification.*')->where('user_id',$get_doctor_details['id'])->where('status',1)->get()->getResultArray();
                            $desig_str="";
                            foreach ($designation as $ke => $va) {
                                if($ke!=0){
                                    $desig_str=$desig_str.',';
                                }
                                $desig_str=$desig_str.$va['qualification'];
                            }
                            $get_doctor_details['designation']=$desig_str;
                            unset($get_doctor_details['default_status']);
                            unset($get_doctor_details['status']);
                            // }
                            $response['data']=$get_doctor_details;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Doctor Not found'; 
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
                    $response['message']='No data found';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
                $response['data']=[];
            }
            return $response;
        }
        public function list_all_symptoms($data=''){
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
                        $employee_symptom= $this->db->table('symptom')->select('symptom.symptom,symptom.specialization_id,symptom.id as symptom_id')->where('status',1)->get()->getResultArray();
                        foreach ($employee_symptom as $key => $value) {}
                        if(!empty($employee_symptom)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$employee_symptom;
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
        public function slot_booking($data='') {
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
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
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
                if(isset($data['visit_type'])){
                    $visit_type=$data['visit_type'];
                }else{
                    $visit_type='';
                }
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){ //check booking slot is valid and get vist_type
                        $check_booking_slot= $this->db->table('doctor_slot')->select('*')->where('id',$book_slot_id)->get()->getRowArray();
                        if(!empty($check_booking_slot)){
                            //save book slots
                            $book_slot_data['doctor_slot_id']=$book_slot_id;
                            $book_slot_data['doctor_id']=$doctor_id;
                            $book_slot_data['sick_notes']=$sick_notes;
                            $book_slot_data['visit_type']=$visit_type;
                            $book_slot_data['booked_for']=$check_booking_slot['start_datetime'];
                            $book_slot_data['user_id']=$user_id;
                            $book_slot_data['family_member_id']=$family_member_id;
                            $book_slot_data['booked_datetime']= $this->current_datetime;
                            $current_year=date('Y');
                            $current_month=date('m');
                            $get_booking_count=$this->db->table('book_slot')->select('count(*) as count')->where('year(booked_datetime)',$current_year)->where('month(booked_datetime)',$current_month)->get()->getRowArray();
                            $id=$get_booking_count['count']+1;
                            $sequence_letter= $this->get_short_form();    //call short form to get app letters
                            $booking_id=$sequence_letter.'_'.$current_year.$current_month.sprintf('%04d',$id);
                            $book_slot_data['booking_id']= $booking_id;
                            //insert data in book slot table
                            $update_doctor_slot = $this->db->table('book_slot')->insert($book_slot_data);
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
        public function doctor_available_slot_details($data=''){
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
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        if($doctor_id!=''){  
                            $result_array=array();
                            $current_datetime=$this->current_datetime;
                            $doctor_slot= $this->db->table('doctor_slot')->select('doctor_slot.*,doctor_slot_master.user_id as doctor_id')->where('doctor_slot_master.user_id',$doctor_id)->where('doctor_slot_master.status',1)->where('doctor_slot.status',1)->where('doctor_slot.start_datetime>=',$current_datetime)->join('doctor_slot_master','doctor_slot_master.id=doctor_slot.master_id','left')->orderBy('doctor_slot.start_datetime','asc')->get()->getResultArray();
                            $dates=array();
                            $message_list=array();
                            if(!empty($doctor_slot)){
                                foreach ($doctor_slot as $key => $value) {
                                    $doctor_slot[$key]['booked_status']='1';
                                    $booked_slots= $this->db->table('book_slot')->select('book_slot.*')->where('book_slot.doctor_slot_id',$value['id'])->where('book_slot.doctor_id',$value['doctor_id'])->where('book_slot.status',1)->get()->getResultArray();
                                    if(!empty($booked_slots)){
                                        unset($doctor_slot[$key]);
                                    }
                                    if(isset($doctor_slot[$key])){
                                        array_push($result_array,$doctor_slot[$key]);
                                    }
                                }
                                // $current_date=date("Y-m-d");
                                foreach ($result_array as $key => $value) {
                                    $get_date= explode(" ",$result_array[$key]['date']);
                                    // var_dump($get_date);
                                    $message_date=$get_date[0];
                                    if(!in_array($message_date, $dates)){}
                                }
                                    // var_dump($result_array);
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $response['data']=$result_array;
                            }else{
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='No data found';
                                $response['data']=[];
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='Doctor_id Missing';
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
                    $response['message']='Data is Empty';
                    $response['data']=[];
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No Input data found';
                $response['data']=[];
            }
            return $response;
        }
        public function hospitalwise_doctor_list($data=''){
            if(!empty($data)){
                if(isset($data['hospital_id'])){
                    $hospital_id=$data['hospital_id'];
                }else{
                    $hospital_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                }else{
                    $access_token='';
                }
                if($hospital_id!="" && $access_token!=""){
                        //check user data is valid
                    $check_user= $this->check_user_detail($hospital_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_hospitalwise= $this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as  hospital_name')->where('doctor_current_organisation.hospital_id',$hospital_id)->where('doctor_current_organisation.status',1)->join('user','user_id=doctor_current_organisation.doctor_id','left')->get()->getResultArray();
                        if(!empty($list_hospitalwise)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_hospitalwise;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No data found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data';
            }
            return $response;
        }
        public function delete_doctor_slot($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['doctor_slot_id'])){
                    $doctor_slot_id=$data['doctor_slot_id'];
                    unset($data['doctor_slot_id']);
                }else{
                    $doctor_slot_id='';
                }
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    // var_dump($check_user);
                    if($check_user['status']){
                        if($doctor_slot_id!=''){
                            $book_slot=$this->db->table('book_slot')->select('*')->where('doctor_slot_id',$doctor_slot_id)->get()->getRowArray();
                            if(empty($book_slot)){
                                $doctor_slot=$this->db->table('doctor_slot')->select('*')->where('id',$doctor_slot_id)->where('status',1)->get()->getRowArray();
                                if(!empty($doctor_slot)){
                                    if($this->current_datetime<=$doctor_slot['start_datetime']){
                                        $insert_data['status']=0;
                                        $builder = $this->db->table('doctor_slot');
                                        $save=$builder->where('id',$doctor_slot_id)->update($insert_data);
                                        if($save){
                                            $response['status']=true;
                                            $response['statuscode']=200;
                                            $response['message']='Deleted';
                                        }else{
                                            $response['status']=false;
                                            $response['statuscode']=400;
                                            $response['message']='Not Deleted';
                                        }
                                    }else{
                                        $response['status']=false;
                                        $response['statuscode']=400;
                                        $response['message']='This slot already expired cann"t delete';
                                    }
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=200;
                                    $response['message']='No slot Found';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=200;
                                $response['message']='This slot already booked cannot delete this.';
                            }
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Deleted Slot ID Missing';
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
                    $response['message']='UserID/Access_token is Empty';
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
        public function list_generate_medical_report($data=''){
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
                if(isset($data['patient_id'])){
                    $patient_id=$data['patient_id'];
                }else{
                    $patient_id='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $generate_medical_report= $this->db->table('generate_medical_report')->select('*')->where('doctor_id',$user_id)->where('patient_id',$patient_id)->where('status',1)->get()->getResultArray();
                        if(!empty($generate_medical_report)){
                            foreach($generate_medical_report as $key => $value){}
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$generate_medical_report;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No report found';
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
        public function patient_feedback($data=''){
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
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
                        if(isset($data['feedback'])){
                            $feedback=$data['feedback'];
                        }
                        if(isset($data['doctor_id'])){
                            $doctor_id=$data['doctor_id'];
                        }else{
                            $doctor_id='';
                        }
                        if(isset($data['rating '])){
                            $rating =$data['rating '];
                        }
                        if($doctor_id!=''){
                            $data['added_datetime']=$this->current_datetime;
                            $insert_feedback=$this->db->table('patient_feedback')->insert($data);
                            if($insert_feedback){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                            }else{
                                $response['status']=false;
                                $response['statuscode']=200;
                                $response['message']='Not inserted in Database';
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Feedback Cannot be Null';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;  
                        $response['message']='Invalid Doctor_id';  
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    if ($user_id=='' && $access_token=='') {
                        $response['message']='Doctor_id ID & Access_token Missing';  
                    }else if($user_id==''){
                        $response['message']='Doctor_id ID Missing';  
                    }else if($access_token==''){
                        $response['message']='Access_token Missing';  
                    }
                }
            }
            return $response;
        }
        public function list_patient_feedback($data=''){
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
                        $patient_feedback= $this->db->table('patient_feedback')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($patient_feedback)){
                            foreach($patient_feedback as $key => $value){}
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$patient_feedback;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No report found';
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
        public function generate_medical_report($data=''){
            if(!empty($data)){
                if(isset($data['booking_slot_id'])){
                    $booking_slot_id=$data['booking_slot_id'];
                }else{
                    $booking_slot_id='';
                }
                if(isset($data['follow_up_date'])){
                    $follow_up_date=$data['follow_up_date'];
                    unset($data['follow_up_date']);
                }else{
                    $follow_up_date='';
                }
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
                // var_dump($user_id);
                if($user_id!='' && $access_token!='' && $booking_slot_id!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        $slot_details=$this->db->table('book_slot')->select('*')->where('status',1)->where('id',$booking_slot_id)->get()->getRowArray();
                        // var_dump($slot_details);
                        if(!empty($slot_details)){
                            $data['added_datetime']=$this->current_datetime;
                            $data['doctor_id']=$user_id;
                            $data['patient_id']=$slot_details['user_id'];
                            $data['family_member_id']=$slot_details['family_member_id'];
                            $savedata= $this->db->table('doctor_medical_history')->insert($data);
                            if ($savedata) {
                                $update_data['next_follow_up_date']=$follow_up_date;
                                $update=$this->db->table('book_slot')->where('id',$booking_slot_id)->update($update_data);
                                $response['status']='true';
                                $response['statuscode']='200';
                                $response['message']='Updated Successfully';
                            }else{
                                $response['status']='false';
                                $response['statuscode']='400';
                                $response['message']='Not Updated';
                            }
                        }else{
                            $response['status']='false';
                            $response['statuscode']='400';
                            $response['message']='No Slot Found for this ID';
                        }
                    }else{
                        $response['status']='false';
                        $response['statuscode']='400';
                        $response['message']='No User Found';
                    }
                }else{
                    $response['status']='false';
                    $response['statuscode']='400';
                    $response['message']='access_token / doctor_id missing';  
                }
            }else{
                $response['status']='false';
                $response['statuscode']='400';
                $response['message']=' input Data Missing'; 
            }
            return $response;
        }
        public function add_emergency_contact($data=''){
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
                if(isset($data['name'])){
                    $name=$data['name'];
                }else{
                    $name='name';
                }
                if(isset($data['mobile'])){
                    $mobile=$data['mobile'];
                }else{
                    $mobile='mobile';
                }
                if(isset($data['relation'])){
                    $relation=$data['relation'];
                }else{
                    $relation='relation';
                }
                if($user_id!='' && $access_token!='' && $mobile!=''){
                    //check user
                    $check_user=$this->check_user_detail($user_id,$access_token);
                    if($check_user['status']==true){
                        //insert
                        $emergency_data['user_id']=$user_id;
                        $emergency_data['mobile']=$mobile; 
                        $emergency_data['name']=$name; 
                        $emergency_data['relation']=$relation;
                        $data_exist=$this->db->table('emergency_contact')->select('*')->where('user_id',$user_id)->where('mobile',$mobile)->where('status',1)->get()->getResultArray();
                        if(empty($data_exist)){
                            $insert_emergency=$this->db->table('emergency_contact')->insert($emergency_data);
                            if($insert_emergency){
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
                            $response['message']='Mobile numbery already exist';
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
        public function list_emergency_contact($data=''){
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
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        $get_emergency_contact= $this->db->table('emergency_contact')->select('emergency_contact.name,emergency_contact.mobile,emergency_contact.relation')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($get_emergency_contact)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            $response['data']=$get_emergency_contact; 
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='no data found'; 
                            $response['data']=array(); 
                        }
                    }else{
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='no user found'; 
                        }
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='user_id/access_token missing'; 
                }
            }else{
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='no input data found'; 
            }
            return $response;
        }
        public function delete_emergency_contact($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    if($check_user['status']==true){
                        if($id!=''){
                            $update['status']=0;
                            $check_emergency_contact=$this->db->table('emergency_contact')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_emergency_contact)){
                                $result= $this->db->table('emergency_contact')->where('id',$id)->update($update);
                                if($result){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no emergency contact id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="Emergency Contact Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function patient_profile_details($data='', $user_data='') {
            //save the basic data to employee details and family_member
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
            if(isset($data['name'])){
                $name=$data['name'];
            }else{
                $name='';
            }
            if(isset($data['email'])){
                $email=$data['email'];
            }else{
                $email='';
            }
            if(isset($data['gender'])){
                $gender=$data['gender'];
            }else{
                $gender='';
            }
            if(isset($data['profile_pic'])){
                $profile_pic=$data['profile_pic'];
            }else{
                $profile_pic='';
            }
            $relation='self';
            $access_id=$user_data['access_id'];
            $updated_datetime= $this->current_datetime;
            //check thid email exist for another user
            $mail_data= $this->db->table('user')->select('*')->where('email',$email)->where('user_id!=',$user_id)->where('status','1')->get()->getResultArray();
            if(empty($mail_data)){
                //update user name in user table
                $user_table_data['username']=$name;
                $user_table_data['email']=$email;
                $update_user_table=$this->db->table('user')->where('id',$user_id)->update($user_table_data);
                if($update_user_table){
                    $employee_data['updated_datetime']= $updated_datetime;
                    $employee_data['updated_by']= $user_id;
                    if($gender!=''){
                        $employee_data['gender']=$gender;
                    }
                    if($email!=''){
                        $employee_data['email']=$email;
                    }
                    if($profile_pic!=''){
                        $employee_data['profile_pic']=$profile_pic;
                    }
                    $check_employee_data_exist= $this->db->table('employee_basic_details')->where('user_id',$user_id)->get()->getRowArray();
                    if(empty($check_employee_data_exist)){
                        $employee_data['user_id']=$user_id;
                        $employee_data['access_id']=$access_id;
                        $update_employee_data= $this->db->table('employee_basic_details')->insert($employee_data);
                    }else{
                        $update_employee_data= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($employee_data);
                    }
                    if($update_employee_data){
                        //insert in family member table
                        if($name!=''){
                            $family_member_data['username']=$name;
                        }
                        if($email!=''){
                            $family_member_data['email_id']=$email;
                        }
                        if($profile_pic!=''){
                            $family_member_data['profile_pic']=$profile_pic;
                        }
                        if($gender!=''){
                            $family_member_data['gender']=$gender;
                        }
                        $family_member_data['updated_datetime']=$this->current_datetime;
                        $check_family_member_exist= $this->db->table('family_member')->where('user_id',$user_id)->where('default_status',1)->get()->getRowArray();
                        if(empty($check_employee_data_exist)){
                            $family_member_data['user_id']=$user_id;
                            $family_member_data['added_datetime']=$this->current_datetime;
                            $family_member_data['family_member_id']=$this->generate_family_member_id();
                            $family_member_data['default_status']=1;
                            $family_member_data['relation']=$relation;
                            //also need to generate qr_code of family member
                            $family_member_data['generate_qrcode']='';
                            $update_family_member=$this->db->table('family_member')->insert($family_member_data);
                            $last_inserted_id= $this->db->insertID();
                        }else{
                            $update_family_member= $this->db->table('family_member')->where('user_id',$user_id)->where('default_status',1)->update($family_member_data);
                            $last_inserted_id= $check_family_member_exist['id'];
                        }
                        if($update_family_member){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            //get family member details
                            // $get_data= $this->db->table('family_member')->select('*')->where('id',$last_inserted_id)->get()->getRowArray();
                            // $response['data']=$get_data;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Family Member Not inserted in db';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=400;
                        $response['message']='Basic Details Not inserted in db';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=400;
                    $response['message']='User Not updated in db';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=400;
                $response['message']='this email already register for another user,please try anothor email';
                //this email already register for another user please try anothor email
            }
            return $response;
        }
        public function show_doctor_details($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_doctor= $this->db->table('user')->select('*')->where('id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($list_doctor)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_doctor;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        } 
        public function show_pharmacy_details($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_pharmacy= $this->db->table('user')->select('user.username as pharmacy_name,user.id as id,user.mobile as mobile,user.email as email,employee_basic_details.profile_pic as profile-pic')->where('user.id',$user_id)->where('user.status',1)->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->groupBy('employee_basic_details.user_id')->get()->getResultArray();
                        if(!empty($list_pharmacy)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_pharmacy;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        } 
        public function add_case_history($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    // unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['file_path'])){
                    $file_path=$data['file_path'];
                }else{
                    $file_path='';
                }
                if(isset($data['family_member_id'])){
                    $family_member_id=$data['family_member_id'];
                }else{
                    $family_member_id='';
                }
                if($user_id!="" && $access_token!="" && $file_path!="" && $family_member_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $data['added_datetime']=$this->current_datetime;
                        $builder = $this->db->table('medical_history');
                        $save=$builder->insert($data);
                        if($save){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Inserted Successfully';
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not Inserted in db';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }  
        public function list_case_history($data=''){
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
                if($user_id!='' && $access_token!='' ){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $patient_details= $this->db->table('family_member')->select('family_member.id')->where('family_member.user_id',$user_id)->where('family_member.default_status',1)->get()->getRowArray();
                        if(isset($data['family_member_id'])){
                            if($data['family_member_id']!=0){
                                $family_member_id=$data['family_member_id'];
                            }else{
                                $family_member_id=$patient_details['id'];
                            }
                        }else{
                            $family_member_id=$patient_details['id'];
                        }
                        $medical_history= $this->db->table('medical_history')->select('medical_history.id,medical_history.added_datetime,medical_history.date_time,medical_history.file_path,medical_history.file_type,medical_history.document_name')->where('medical_history.user_id',$user_id)->where('medical_history.family_member_id',$family_member_id)->where('medical_history.status',1)->get()->getResultArray();
                        // var_dump($medical_history);
                        if(!empty($medical_history)){
                            foreach ($medical_history as $key => $value) {
                                if($medical_history[$key]['file_path']!=''){
                                    $medical_history[$key]['file_path']= base_url().'/'.$medical_history[$key]['file_path'];
                                }else{
                                    $medical_history[$key]['file_path']='';
                                }
                                $medical_history[$key]['added_datetime']=date('Y-m-d', strtotime($medical_history[$key]['added_datetime']));
                                if(isset($medical_history[$key]['user_id'])){
                                    unset($medical_history[$key]['user_id']);
                                }
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$medical_history;
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='No data found';
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
                    $response['message']='Data is Empty';
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
        public function list_lab_test($data=''){
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
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        $get_lab_test= $this->db->table('lab_test_details')->select('*')->where('status',1)->get()->getResultArray();
                        if(!empty($get_lab_test)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='success'; 
                            $response['data']=$get_lab_test; 
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='no data found'; 
                            $response['data']=array(); 
                        }
                    }else{
                        $response['status']=true;
                        $response['statuscode']=200;
                        $response['message']='no user found'; 
                        }
                }else{
                    $response['status']=true;
                    $response['statuscode']=200;
                    $response['message']='user_id/access_token missing'; 
                }
            }else{
                $response['status']=true;
                $response['statuscode']=200;
                $response['message']='no input data found'; 
            }
            return $response;
        }
        public function edit_employee_qualification($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if (isset($data['id'])) {
                    $id=$data['id'];
                    unset($data['id']);
                }else{
                    $id='';
                }
                if ($user_id!='' && $access_token!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        if ($id!=''){
                            $id_data= $this->db->table('employee_qualification')->select('*')->where('id',$id)->where('status',1)->get()->getResultArray();
                            if(!empty($id_data)){
                                $save_data= $this->db->table('employee_qualification')->where('id',$id)->update($data);
                                if($save_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Updated Successfully';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not Updated';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='Not Updated in Employee qualification Details db';
                            }    
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No user data found';
                        }                         
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='id is empty';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='user id access token mising';
  
                    // userid access token mising
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function update_doctor_prescription($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['slot_id'])){
                    $slot_id=$data['slot_id'];
                }else{
                    $slot_id='';
                }
                if($user_id!="" && $access_token!="" && $slot_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $data['added_datetime']= $this->current_datetime;
                        $save_data= $this->db->table('prescription')->insert($data);
                        if($save_data){
                            $last_insert_id= $this->db->insertID();
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['last_inserted_id']=$last_insert_id;
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
        public function list_medicine_dosage($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valuser_id
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $list_medicine_dosage= $this->db->table('medicine_dosage')->select('*')->where('status',1)->get()->getResultArray();
                        if(!empty($list_medicine_dosage)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_medicine_dosage;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function list_medicine_time($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valuser_id
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $list_medicine_time= $this->db->table('medicine_time')->select('*')->where('status',1)->get()->getResultArray();
                        if(!empty($list_medicine_time)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_medicine_time;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function updates_doctor_assistant_status($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!="" ){
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $save_data= $this->db->table('employee_basic_details')->where('user_id',$user_id)->update($data);
                        if($save_data){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['last_inserted_id']=$last_insert_id;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not updated in db';
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
        public function add_medicine($data='') {
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!='' && $access_token!=''){
                    //check user is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //update consulting fee
                        $data['created_datetime']= $this->current_datetime;
                        $data['added_by']= $user_id;
                       
                        $save_data= $this->db->table('medicines')->insert($data);
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
        public function my_doctor_list($data=''){
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
                        $patient_details= $this->db->table('family_member')->select('family_member.id')->where('family_member.user_id',$user_id)->where('family_member.default_status',1)->get()->getRowArray();
                        if(isset($data['family_member_id'])){
                            if($data['family_member_id']!=0){
                                $family_member_id=$data['family_member_id'];
                            }else{
                                $family_member_id=$patient_details['id'];
                            }
                        }else{
                            if (!empty($patient_details)){
                                $family_member_id=$patient_details['id'];
                            }else{
                                $family_member_id=0;
                            }
                        }
                        $get_doctor_details=$this->db->table('book_slot')->select('user.username as doctor_name,user.id as doctor_id, book_slot.family_member_id, employee_basic_details.profile_pic')->where('book_slot.user_id',$user_id)->where('book_slot.family_member_id',$family_member_id)->where('book_slot.status',1)->join('user','book_slot.doctor_id=user.id','left')->join('employee_basic_details','user.id=employee_basic_details.user_id','left')->groupBy('book_slot.doctor_id')->get()->getResultArray();
                        
                        if(!empty($get_doctor_details)){
                            foreach ($get_doctor_details as $key => $value) {
                                if(!empty($get_doctor_details[$key])){
                                    if($get_doctor_details[$key]['profile_pic']!=''){
                                        $get_doctor_details[$key]['profile_pic']= base_url().'/'.$get_doctor_details[$key]['profile_pic'];
                                    }else{
                                        $get_doctor_details[$key]['profile_pic']='';
                                    }
                                    $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getRowArray();
                                    if(!empty($experience)){
                                        if(isset($experience['experience'])){
                                            $get_doctor_details[$key]['experience']=$experience['experience'];
                                        }else{
                                            $get_doctor_details[$key]['experience']=0;
                                        }
                                    }else{
                                        $get_doctor_details[$key]['experience']=0;
                                    }
                                   
                                    $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as hospital_name')->where('doctor_current_organisation.doctor_id',$value['doctor_id'])->where('doctor_current_organisation.status',1)->where('doctor_current_organisation.working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                                    $organisation_array=[];
                                    $organ_str="";
                                    foreach ($organisation as $ky => $val) {
                                        // array_push($organisation_array, $val['hospital_name']);
                                        if($ky!=0){
                                            $organ_str=$organ_str.',';
                                        }
                                        $organ_str=$organ_str.$val['hospital_name'];
                                    }
                                    $get_doctor_details[$key]['organisation']=$organ_str;
                                    $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getResultArray();
                                    $specialization_array=[];
                                    $special_str="";
                                    foreach ($specialization as $ke => $va) {
                                    
                                        if($ke!=0){
                                            $special_str=$special_str.',';
                                        }
                                        $special_str=$special_str.$va['specialization'];
                                    }
                                    $get_doctor_details[$key]['specialization']=$special_str;
                                    unset($get_doctor_details[$key]['id']);
                                }
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$get_doctor_details;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function search_medicine($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['search'])){
                    $search=$data['search'];
                }else{
                    $search='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $builder=$this->db->table('medicines')->select('*');
                        if($search!=''){
                            $builder=$builder->like('medicines.medicine_name',$search);
                        }
                        $list_medicines= $builder->where('status',1)->get()->getResultArray();
                        // var_dump($this->db->getlastQuery());
                        if(!empty($list_medicines)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_medicines;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function show_patient_stickers($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_stickers= $this->db->table('book_slot')->select('book_slot.id as slot_id,book_slot.stickers as stickers')->get()->getResultArray();
                        if(!empty($list_stickers)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_stickers;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function service_feedback($data=''){
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
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        $data['added_datetime']=$this->current_datetime;
                        $savedata= $this->db->table('doctor_feedback')->insert($data);
                        if ($savedata) {
                            $response['status']='true';
                            $response['statuscode']='200';
                            $response['message']='Updated Successfully';
                        }else{
                            $response['status']='false';
                            $response['statuscode']='400';
                            $response['message']='Not Updated';
                        }
                    }else{
                        $response['status']='false';
                        $response['statuscode']='400';
                        $response['message']='No User Found';
                    }
                }else{
                    $response['status']='false';
                    $response['statuscode']='400';
                    $response['message']='access_token/user_id missing';  
                }
            }else{
                $response['status']='false';
                $response['statuscode']='400';
                $response['message']=' input Data Missing'; 
            }
            return $response;
        }
        public function add_organisation_experience($data=''){
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
                if(isset($data['establish_date'])){
                    $establish_date=$data['establish_date'];
                }else{
                    $establish_date='';
                }
                if(isset($data['established_by'])){
                    $established_by=$data['established_by'];
                }else{
                    $established_by='';
                }
                if($user_id!="" && $access_token!="" && $establish_date!="" && $established_by!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $experience_data['user_id']=$user_id;
                        $experience_data['created_datetime']= $this->current_datetime;
                        $experience_data['establish_date']=$establish_date;
                        $experience_data['established_by']=$established_by;
                        $save_data= $this->db->table('organization_experience')->insert($experience_data);
                        if($save_data){
                            $last_insert_id= $this->db->insertID();
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
        public function list_organisation_experience($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_work_experience= $this->db->table('organization_experience')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($list_work_experience)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_work_experience;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function delete_organisation_experience($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_qualification_id=$this->db->table('organization_experience')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_qualification_id)){
                                $data= $this->db->table('organization_experience')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no experience id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization experience Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function edit_work_experience($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if (isset($data['id'])) {
                    $id=$data['id'];
                    unset($data['id']);
                }else{
                    $id='';
                }
                if (isset($data['establish_date'])) {
                    $establish_date=$data['establish_date'];
                }else{
                    $establish_date='';
                }
                if (isset($data['established_by'])) {
                    $established_by=$data['established_by'];
                }else{
                    $established_by='';
                }
                if ($user_id!='' && $access_token!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        if ($id!=''){
                            $id_data= $this->db->table('organization_experience')->select('*')->where('id',$id)->where('status',1)->get()->getResultArray();
                            if(!empty($id_data)){
                                $save_data= $this->db->table('organization_experience')->where('id',$id)->update($data);
                                if($save_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Updated Successfully';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not Updated';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='ID Not Found in db';
                            }    
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No user data found';
                        }                         
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='id is empty';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='user id access token mising';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function add_doctor_organization($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['doctor_id'])){
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if($user_id!="" && $access_token!="" && $doctor_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //save to the db
                        $data['added_by']= $user_id;
                        $data['hospital_id']=$user_id;
                        $data['created_datetime']= $this->current_datetime;
                        $save_data= $this->db->table('doctor_current_organisation')->insert($data);
                        if($save_data){
                            $last_insert_id= $this->db->insertID();
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
        public function edit_doctor_organization($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if (isset($data['id'])) {
                    $id=$data['id'];
                    unset($data['id']);
                }else{
                    $id='';
                }
                if (isset($data['doctor_id'])) {
                    $doctor_id=$data['doctor_id'];
                }else{
                    $doctor_id='';
                }
                if ($user_id!='' && $access_token!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                            $data['added_by']= $user_id;
                        if ($id!=''){
                            $id_data= $this->db->table('doctor_current_organisation')->select('*')->where('id',$id)->where('status',1)->get()->getResultArray();
                            if(!empty($id_data)){
                                $save_data= $this->db->table('doctor_current_organisation')->where('id',$id)->update($data);
                                if($save_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Updated Successfully';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not Updated';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='ID Not Found in db';
                            }    
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No user data found';
                        }                         
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='id is empty';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='user id access token mising';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function list_doctor_organization($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        // $data['added_by']= $user_id;
                        //list family members
                        $list_current_organisation= $this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as doctor_name,employee_basic_details.profile_pic as profile_pic')->where('hospital_id',$user_id)->where('doctor_current_organisation.status',1)->join('user','doctor_current_organisation.doctor_id=user.id','left')->join('employee_basic_details','employee_basic_details.user_id=user.id','left')->get()->getResultArray();
                        if(!empty($list_current_organisation)){
                            foreach ($list_current_organisation as $key => $value) {
                                if($list_current_organisation[$key]['profile_pic']!=''){
                                    $list_current_organisation[$key]['profile_pic']= base_url().'/'.$list_current_organisation[$key]['profile_pic'];
                                }else{
                                    $list_current_organisation[$key]['profile_pic']='';
                                }
                                $experience=$this->db->table('employee_experience')->select('SUM(employee_experience.years) as experience')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getRowArray();
                                if(!empty($experience)){
                                    if(isset($experience['experience'])){
                                        $list_current_organisation[$key]['experience']=$experience['experience'];
                                    }else{
                                        $list_current_organisation[$key]['experience']=0;
                                    }
                                }else{
                                    $list_current_organisation[$key]['experience']=0;
                                }
                               
                                $organisation=$this->db->table('doctor_current_organisation')->select('doctor_current_organisation.*,user.username as hospital_name')->where('doctor_current_organisation.doctor_id',$value['doctor_id'])->where('doctor_current_organisation.status',1)->where('doctor_current_organisation.working_status',1)->join('user','user.id=doctor_current_organisation.hospital_id','left')->get()->getResultArray();
                                $organisation_array=[];
                                $organ_str="";
                                foreach ($organisation as $ky => $val) {
                                    // array_push($organisation_array, $val['hospital_name']);
                                    if($ky!=0){
                                        $organ_str=$organ_str.',';
                                    }
                                    $organ_str=$organ_str.$val['hospital_name'];
                                }
                                $list_current_organisation[$key]['organisation']=$organ_str;
                                $specialization=$this->db->table('employee_specialization')->select('employee_specialization.*')->where('user_id',$value['doctor_id'])->where('status',1)->get()->getResultArray();
                                $specialization_array=[];
                                $special_str="";
                                foreach ($specialization as $ke => $va) {
                                
                                    if($ke!=0){
                                        $special_str=$special_str.',';
                                    }
                                    $special_str=$special_str.$va['specialization'];
                                }
                                $list_current_organisation[$key]['specialization']=$special_str;
                            }
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_current_organisation;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function delete_doctor_organization($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_qualification_id=$this->db->table('doctor_current_organisation')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_qualification_id)){
                                $data= $this->db->table('doctor_current_organisation')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no organization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization  Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function add_organization_bank($data=''){
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
                        if(isset($data['branch'])){
                            $branch=$data['branch'];
                        }
                        if(isset($data['bank_code'])){
                            $bank_code=$data['bank_code'];
                        }
                        if($bank_account_holder_name!='' && $bank_account_number!='' && $bank_name!=''){
                            $insert_specialization=$this->db->table('bank_details')->insert($data);
                            if($insert_specialization){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                                $getinpt=array('user_id'=>$user_id,'access_token'=>$access_token);
                                // $get_bank_details=$this->get_bank_details($getinpt);
                                // $response['data']=$get_bank_details['data'];
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
        public function edit_organization_bank($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if (isset($data['id'])) {
                    $id=$data['id'];
                    unset($data['id']);
                }else{
                    $id='';
                }
                if (isset($data['bank_account_number'])) {
                    $bank_account_number=$data['bank_account_number'];
                }else{
                    $bank_account_number='';
                }
                if (isset($data['bank_account_holder_name'])) {
                    $bank_account_holder_name=$data['bank_account_holder_name'];
                }else{
                    $bank_account_holder_name='';
                }
                if (isset($data['bank_name'])) {
                    $bank_name=$data['bank_name'];
                    unset($data['bank_name']);
                }else{
                    $bank_name='';
                }
                if (isset($data['branch'])) {
                    $branch=$data['branch'];
                    unset($data['branch']);
                }else{
                    $branch='';
                }
                if (isset($data['bank_code'])) {
                    $bank_code=$data['bank_code'];
                    unset($data['bank_code']);
                }else{
                    $bank_code='';
                }
                if ($user_id!='' && $access_token!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                        if ($id!=''){
                            $id_data= $this->db->table('bank_details')->select('*')->where('id',$id)->where('status',1)->get()->getResultArray();
                            if(!empty($id_data)){
                                $save_data= $this->db->table('bank_details')->where('id',$id)->update($data);
                                if($save_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Updated Successfully';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not Updated';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='ID Not Found in db';
                            }    
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='id is empty';
                        }                         
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='user id access token mising';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function list_organization_bank($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_bank_details= $this->db->table('bank_details')->select('*')->where('user_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($list_bank_details)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_bank_details;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function delete_organization_bank($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_bank_details=$this->db->table('bank_details')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_bank_details)){
                                $data= $this->db->table('bank_details')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no organization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization  Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function add_lab_organization($data=''){
            $response=array('status'=>false,'statuscode'=>200,'message'=>'No input data');
            if($data!=''){
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
                if($user_id!='' && $access_token!=''){
                    $data['hospital_id']= $user_id;
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
                        $insert_organization=$this->db->table('lab_organistaion')->insert($data);
                        if($insert_organization){
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
        public function edit_lab_organization($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if (isset($data['id'])) {
                    $id=$data['id'];
                    unset($data['id']);
                }else{
                    $id='';
                }
                if ($user_id!='' && $access_token!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                            $data['hospital_id']= $user_id;
                        if ($id!=''){
                            $id_data= $this->db->table('lab_organistaion')->select('*')->where('id',$id)->where('status',1)->get()->getResultArray();
                            if(!empty($id_data)){
                                $save_data= $this->db->table('lab_organistaion')->where('id',$id)->update($data);
                                if($save_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Updated Successfully';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not Updated';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='ID Not Found in db';
                            }    
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No user data found';
                        }                         
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='id is empty';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='user id access token mising';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function list_lab_organization($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $list_organization= $this->db->table('lab_organistaion')->select('*')->where('hospital_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($list_organization)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$list_organization;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function delete_lab_organization($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_lab_organistaion=$this->db->table('lab_organistaion')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_lab_organistaion)){
                                $data= $this->db->table('lab_organistaion')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no organization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization  Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function add_pharmacy_organization($data=''){
            $response=array('status'=>false,'statuscode'=>200,'message'=>'No input data');
            if($data!=''){
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
                if($user_id!='' && $access_token!=''){
                    $data['hospital_id']= $user_id;
                    $check_user= $this->check_user_detail($user_id, $access_token);//check user is valid
                    if($check_user['status']){
                        $insert_organization=$this->db->table('pharmacy_organization')->insert($data);
                        if($insert_organization){
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
        public function edit_pharmacy_organization($data=''){
            // var_dump($data);
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if (isset($data['id'])) {
                    $id=$data['id'];
                    unset($data['id']);
                }else{
                    $id='';
                }
                if ($user_id!='' && $access_token!=''){
                    $check_user_data= $this->check_user_detail($user_id, $access_token);
                    if($check_user_data['status']){
                            $data['hospital_id']= $user_id;
                        if ($id!=''){
                            $id_data= $this->db->table('pharmacy_organization')->select('*')->where('id',$id)->where('status',1)->get()->getResultArray();
                            if(!empty($id_data)){
                                $save_data= $this->db->table('pharmacy_organization')->where('id',$id)->update($data);
                                if($save_data){
                                    $response['status']=true;
                                    $response['statuscode']=200;
                                    $response['message']='Updated Successfully';
                                }else{
                                    $response['status']=false;
                                    $response['statuscode']=400;
                                    $response['message']='Not Updated';
                                }
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='ID Not Found in db';
                            }    
                        }else{
                            $response['status']=false;
                            $response['statuscode']=200;
                            $response['message']='No user data found';
                        }                         
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='id is empty';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='user id access token mising';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No input data found';
            }
            return $response;
        }
        public function list_pharmacy_organization($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if($user_id!="" && $access_token!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        //list family members
                        $pharmacy_organization= $this->db->table('pharmacy_organization')->select('*')->where('hospital_id',$user_id)->where('status',1)->get()->getResultArray();
                        if(!empty($pharmacy_organization)){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            $response['data']=$pharmacy_organization;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='No Data found';
                            $response['data']=array();
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='No user data found';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='Data missing';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No data found';
            }
            return $response;
        }
        public function delete_pharmacy_organization($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['status']=0;
                            $check_pharmacy_organization=$this->db->table('pharmacy_organization')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_pharmacy_organization)){
                                $data= $this->db->table('pharmacy_organization')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Deleted Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no organization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization  Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function add_hospital_doctor_assistance($data=''){
            // $user_id=$data['user_id'];
            // $access_id=$data['access_id'];
            $datetime= $this->current_datetime;
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
            if(isset($data['country_code'])){
                $country_code=$data['country_code'];
            }else{
                $country_code='';
            }
            if(isset($data['phone_number'])){
                $phone_number=$data['phone_number'];
            }else{
                $phone_number='';
            }
            if(isset($data['doctor_id'])){
                $doctor_id=$data['doctor_id'];
            }else{
                $doctor_id='';
            }
            if(isset($data['assistant_id'])){
                $assistant_id=$data['assistant_id'];
            }else{
                $assistant_id='';
            }
            //check user is valid
            $check_user_data=$this->check_user_detail($user_id,$access_token);
            if($check_user_data['status']){
                //check user data with country code and phone number
                $check_assistant_exist=$this->db->table('user')->select('*')->where('id',$assistant_id)->where('access_id',6)->get()->getRowArray();
                // $check_assistant_exist=$this->db->table('user')->select('*')->where('country_code',$country_code)->where('mobile',$phone_number)->get()->getRowArray();
                //var_dump($check_assistant_exist);
                if(!empty($check_assistant_exist)){
                    // var_dump($check_assistant_exist['access_id']);
                    $get_access_name=$this->get_access_name($check_assistant_exist['access_id']);
                    if($get_access_name=='Assistant' || $get_access_name=='assistant'){
                        $add_doctor_assistance['datetime']=$datetime;
                        $add_doctor_assistance['country_code']=$country_code;
                        $add_doctor_assistance['phone_number']=$phone_number;
                        $add_doctor_assistance['doctor_id']=$doctor_id;
                        $add_doctor_assistance['assistant_id']=$assistant_id;
                        $add_doctor_assistance['added_by']=$user_id;
                        //check assistance is already added or not
                        $check_assisant=$this->db->table('doctor_assistant')->select('*')->where('assistant_id',$assistant_id)->where('status','1')->get()->getRowArray();
                        // var_dump($assistant_id);
                        if(empty($check_assisant)){
                            $insert_data=$this->db->table('doctor_assistant')->insert($add_doctor_assistance);
                            if($insert_data){
                                $response['status']=true;
                                $response['statuscode']=200;
                                $response['message']='Success';
                            }else{
                                $response['status']=false;
                                $response['statuscode']=400;
                                $response['message']='Not inserted in db';
                            }
                        }else{
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='This person is already assistant';
                        }
                    }else{
                        $response['status']=false;
                        $response['statuscode']=200;
                        $response['message']='this user is not a assistant';
                    }
                }else{
                    $response['status']=false;
                    $response['statuscode']=200;
                    $response['message']='No assistant found';
                }
            }else{
                $response['status']=false;
                $response['statuscode']=200;
                $response['message']='No user found';
            }
            return $response;
        }
        public function set_call_status($data=''){
            if(!empty($data)){
                if(isset($data['user_id'])){
                    $user_id=$data['user_id'];
                    unset($data['user_id']);
                }else{
                    $user_id='';
                }
                if(isset($data['access_token'])){
                    $access_token=$data['access_token'];
                    unset($data['access_token']);
                }else{
                    $access_token='';
                }
                if(isset($data['slot_id'])){
                    $slot_id=$data['slot_id'];
                    unset($data['slot_id']);
                }else{
                    $slot_id='';
                }
                if(isset($data['call_status'])){
                    $call_status=$data['call_status'];
                }else{
                    $call_status='';
                }
                if($user_id!="" && $access_token!="" && $slot_id!=""){
                    //check user data is valid
                    $check_user= $this->check_user_detail($user_id, $access_token);
                    if($check_user['status']){
                        $update= $this->db->table('book_slot')->where('id',$slot_id)->update($data);
                        if($update){
                            $response['status']=true;
                            $response['statuscode']=200;
                            $response['message']='Success';
                            // $response['last_inserted_id']=$last_insert_id;
                        }else{
                            $response['status']=false;
                            $response['statuscode']=400;
                            $response['message']='Not updated in db';
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
        public function remove_lab_organization($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['working_status']=0;
                            $check_lab_organistaion=$this->db->table('lab_organistaion')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_lab_organistaion)){
                                $data= $this->db->table('lab_organistaion')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Removed Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no organization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization  Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
        public function remove_pharmacy_organization($data=''){
            if($data!=''){
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
                if(isset($data['id'])){
                    $id=$data['id'];
                }else{
                    $id='';
                }  
                //checking user_id
                if($user_id!="" && $access_token!=""){
                    //check user_id and access_token is valid or not
                    $check_user= $this->check_user_detail($user_id,$access_token);
                    //var_dump($check_user['data']);
                    if($check_user['status']==true){
                        if($id!=''){
                            //update  data
                            $update['working_status']=0;
                            $check_pharmacy_organization=$this->db->table('pharmacy_organization')->select('*')->where('id',$id)->where('status','1')->get()->getRowArray();
                            if(!empty($check_pharmacy_organization)){   
                                $data= $this->db->table('pharmacy_organization')->where('id',$id)->update($update);
                                if($data){
                                    $response['statuscode']=200;
                                    $response['status']=true;
                                    $response['message']="Removed Successfully";
                                }else{
                                    $response['statuscode']=200;
                                    $response['status']=false;
                                    $response['message']="no organization id found";
                                }
                            }else{
                                $response['statuscode']=200;
                                $response['status']=true;
                                $response['message']="no data in this id";
                            }
                        }else{
                            $response['statuscode']=200;
                            $response['status']=true;
                            $response['message']="organization  Id missing";
                        }
                    }else{
                        $response['statuscode']=200;
                        $response['status']=true;
                        $response['message']="user not found";  
                    }
                }else{
                    $response['statuscode']=200;
                    $response['status']=true;
                    $response['message']="user_id/access_token Missing";
                }
            }else{
                $response['statuscode']=200;
                $response['status']=true;
                $response['message']="no input data";
            }
            return $response;
        }
    }   
?>
