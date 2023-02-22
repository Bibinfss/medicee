<?php

    namespace App\Controllers;
    use App\Models\Homemodel;
    use App\Libraries\Ciqrcode;

    class Home extends BaseController
    {
        public function __construct() {
            $this->homemodel=new Homemodel();
            $this->input = \Config\Services::request();
            $this->ciqrcode=new ciqrcode;
        }
        public function index()
        {
            return view('welcome_message');
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

        public function register(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->register($data);
            }else{
                $result= $this->homemodel->register($res);
            }
            echo json_encode($result);
        }
        
        public function verify_otp(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->verify_otp($data);
            }else{
                $result= $this->homemodel->verify_otp($res);
            }
            echo json_encode($result);
        }
        
        public function fill_profile(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                //check user id valid
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);
                //var_dump($check_user);
                //$result= $this->homemodel->fill_profile($data);
                $input_data=$data;
            }else{
                //$result= $this->homemodel->fill_profile($res);
                //var_dump($res);
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
                $input_data=$res;
            }
            //check user is a valid user
            if($check_user['status']){
                if(isset($_FILES['profile_pic'])){
                    
                    //var_dump($_FILES['profile_pic']);
                    if($_FILES['profile_pic']['name']!=''){
                        //var_dump('not empty');
                        $profile_pic=$this->request->getFile('profile_pic');
                        //$file->getName();
                        // Returns 'jpg' (WITHOUT the period)
                        $ext = $profile_pic->guessExtension();
                        //set new file name
                        $new_file_name='IMG_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $profile_folder_path='uploads/profile_pic';
                        $file_upload_status=false;
                         if(file_exists($profile_folder_path)){
                             //var_dump('yes available');
                             if($profile_pic->move($profile_folder_path,$new_file_name)){
                                $input_data['profile_pic_path']=$profile_folder_path.'/'.$new_file_name;
                                $file_upload_status=true; 
                             }
                         }else{
                             //var_dump('not available');
                             //create folder and upload
                             if(mkdir($profile_folder_path,0755,true)){
                                 if($profile_pic->move($profile_folder_path,$new_file_name)){
                                    $input_data['profile_pic_path']=$profile_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                 }
                             }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                             }
                         }
                         if($file_upload_status==true){
                            //save all data
                            $result= $this->homemodel->fill_profile($input_data);
                        }else{
                           $result['status']=false;
                           $result['statuscode']=400;
                           $result['message']='Profile pic not uploaded'; 
                        }
                    }else{
                        //image not in the input
                        //save all data
                        $input_data['profile_pic_path']='uploads/profile_pic/profile.svg';
                        $result= $this->homemodel->fill_profile($input_data);
                    }
                    
                }else{
                    //var_dump('nnn');
                    //image not in the input
                    //save all data
                    $input_data['profile_pic_path']='';
                    $result= $this->homemodel->fill_profile($input_data);
                }
                
                 
            }else{
                $result['status']=false;
                $result['statuscode']=200;
                $result['message']='No user data found';
            }
            echo json_encode($result);
        }
        
        // public function add_employee_basic_details(){}
        
        public function add_employee_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->add_employee_experience($data);
                $document_file= isset($_FILES['experience_document']) ? $_FILES['experience_document'] : '';
                $user_data=$data;
            }else{
                $result= $this->homemodel->add_employee_experience($res);
                $document_file=isset($_FILES['experience_document']) ? $_FILES['experience_document'] : '';
                $user_data=$res;
            }
            if($result['status']){
                //call upload_experience_documnet function
                $result= $this->upload_experience_document($result['last_inserted_id'],$document_file);
            }
            //get all document
            $get_all_documents= $this->homemodel->get_employee_experience($user_data);
            $result['data']=$get_all_documents['data'];
            echo json_encode($result);
        }
        
        public function upload_experience_document($id='',$document_file=''){
            if(!empty($id) && !empty($document_file)){
                //var_dump($document_file);
                if($document_file['name']!=''){
                    //var_dump('ssss');
                    $file=$this->request->getFile('experience_document');
                    $ext= $file->guessExtension(); 
                    //var_dump($profile_pic);
                    $new_file_name='FILE_'.date('YmdHis').'.'.$ext;
                    //check folder exist or not
                    $folder_path='uploads/experience';
                    $upload_status=false;
                    if(file_exists($folder_path)){
                        if($file->move($folder_path,$new_file_name)){
                             $upload_status=true;   
                        }
                    }else{
                        //create folder
                        if(mkdir($folder_path,0755,true)){
                            if($file->move($folder_path,$new_file_name)){
                                $upload_status=true;
                            }
                        }else{
                            $result['status']=false;
                            $result['statuscode']=400;
                            $result['messge']='Folder not created'; 
                        }
                    }
                    if($upload_status){
                        //var_dump('sssss',);
                        $employee_document['experience_documents']=$folder_path.'/'.$new_file_name;
                        $result= $this->homemodel->update_experience_document($id,$employee_document);
                    }else{
                        $result['status']=false;
                        $result['statuscode']=400;
                        $result['messge']='File not uploaded'; 
                    }
                }else{
                    $result['status']=false;
                    $result['statuscode']=200;
                    $result['messge']='Image is empty'; 
                }
            }else{
                $result['status']=false;
                $result['statuscode']=200;
                $result['messge']='Image is empty';
            }
            return $result;
        }
        
        public function add_employee_qualification(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->add_employee_qualification($data);
                $document_file= isset($_FILES['qualification_document']) ? $_FILES['qualification_document'] : '';
                $user_data=$data;
            }else{
                $result= $this->homemodel->add_employee_qualification($res);
                $document_file=isset($_FILES['qualification_document']) ? $_FILES['qualification_document'] : '';
                $user_data=$res;
            }
            if($result['status']){
                //call upload_experience_documnet function
                $result= $this->upload_qualification_document($result['last_inserted_id'],$document_file);
            }
            //get all document
            $get_all_documents= $this->homemodel->get_employee_qualification($user_data);
            $result['data']=$get_all_documents['data'];
            echo json_encode($result);
        }
        
        public function upload_qualification_document($id='',$document_file=''){
            if(!empty($id) && !empty($document_file)){
                //var_dump($document_file);
                if($document_file['name']!=''){
                    //var_dump('ssss');
                    $file=$this->request->getFile('qualification_document');
                    $ext= $file->guessExtension(); 
                    //var_dump($profile_pic);
                    $new_file_name='FILE_'.date('YmdHis').'.'.$ext;
                    //check folder exist or not
                    $folder_path='uploads/qualification';
                    $upload_status=false;
                    if(file_exists($folder_path)){
                        if($file->move($folder_path,$new_file_name)){
                             $upload_status=true;   
                        }
                    }else{
                        //create folder
                        if(mkdir($folder_path,0755,true)){
                            if($file->move($folder_path,$new_file_name)){
                                $upload_status=true;
                            }
                        }else{
                            $result['status']=false;
                            $result['statuscode']=400;
                            $result['messge']='Folder not created'; 
                        }
                    }
                    if($upload_status){
                        //var_dump('sssss',);
                        $employee_document['upload_documents']=$folder_path.'/'.$new_file_name;
                        $result= $this->homemodel->update_qualification_document($id,$employee_document);
                    }else{
                        $result['status']=false;
                        $result['statuscode']=400;
                        $result['messge']='File not uploaded'; 
                    }
                }else{
                    $result['status']=false;
                    $result['statuscode']=200;
                    $result['messge']='Image is empty'; 
                }
            }else{
                $result['status']=false;
                $result['statuscode']=200;
                $result['messge']='Image is empty';
            }
            return $result;
        }
        
        public function get_employee_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->get_employee_experience($data);
            }else{
                $result= $this->homemodel->get_employee_experience($res);
            }
            echo json_encode($result);
        }
        public function get_employee_qualification(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->get_employee_qualification($data);
            }else{
                $result= $this->homemodel->get_employee_qualification($res);
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
        
        public function add_doctor_consulting_fee() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->add_doctor_consulting_fee($data);
            }else{
                $result= $this->homemodel->add_doctor_consulting_fee($res);
            }
            echo json_encode($result);
        }
        public function add_branch_employee_consulting_fee() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->add_branch_employee_consulting_fee($data);
            }else{
                $result= $this->homemodel->add_branch_employee_consulting_fee($res);
            }
            echo json_encode($result);
        }
        public function add_doctor_slot() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->add_doctor_slot($data);
            }else{
                $result= $this->homemodel->add_doctor_slot($res);
            }
            echo json_encode($result);
        }
        public function get_access_data() {
            $result= $this->homemodel->get_access_data();
            echo json_encode($result);
        }
        public function update_admin_verification_accept_status() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->update_admin_verification_accept_status($data);
            }else{
                $result= $this->homemodel->update_admin_verification_accept_status($res);
            }
            echo json_encode($result);
        }
        public function update_admin_verification_reject_status() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->update_admin_verification_reject_status($data);
            }else{
                $result= $this->homemodel->update_admin_verification_reject_status($res);
            }
            echo json_encode($result);
        }
        
        public function get_doctor_slot_based_on_date() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->get_doctor_slot_based_on_date($data);
            }else{
                $result= $this->homemodel->get_doctor_slot_based_on_date($res);
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
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->get_bank_details($data);
            }else{
                $result=$this->homemodel->get_bank_details($res);
            }
            echo json_encode($result);
        }
        
        public function resend_otp(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->resend_otp($data);
            }else{
                $result=$this->homemodel->resend_otp($res);
            }
            echo json_encode($result);
        }
        
        public function add_user_symptoms(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_user_symptoms($data);
            }else{
                $result=$this->homemodel->add_user_symptoms($res);
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
                $result=$this->homemodel->get_all_currency($data);
            }else{
                $result=$this->homemodel->get_all_currency($res);
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
    
        public function book_doctor_slot(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->book_doctor_slot($data);
            }else{
                $result=$this->homemodel->book_doctor_slot($res);
            }
            echo json_encode($result); 
        }
        
        public function fill_patient_profile(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);   //check user id valid
                $input_data=$data;
                $user_data=$check_user['data'];
            }else{
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);   //check user id valid
                $input_data=$res;
                $user_data=$check_user['data'];
            }
            if($check_user['status']){
                $result=$this->homemodel->fill_patient_profile($input_data,$user_data);
            }else{
                $result['status']=true;
                $result['statuscode']=200;
                $result['message']='No user data found';
            }
            echo json_encode($result); 
        }
        
        public function fill_patient_profile111(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                //check user id valid
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);
                $input_data=$data;
                $user_data=$check_user['data'];
            }else{
                //var_dump($res['user_id'],$res['access_token']);
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
                $input_data=$res;
                $user_data=$check_user['data'];
                //$result=$this->homemodel->fill_patient_profile($res);
            }
            //var_dump($check_user);
            if($check_user['status']){
                //var_dump("success");
                $file_upload_status=false;
                if(isset($_FILES['profile_pic'])){
                    //var_dump('ssss');
                    if($_FILES['profile_pic']['name']!=''){
                        //var_dump("file is selected");
                        $profile_pic=$this->request->getFile('profile_pic');
                        //$file->getName();
                        // Returns 'jpg' (WITHOUT the period)
                        $ext = $profile_pic->guessExtension();
                        //set new file name
                        $new_file_name='IMG_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $profile_folder_path='uploads/patient_profile_pic';
                        
                         if(file_exists($profile_folder_path)){
                             //var_dump('yes available');
                             if($profile_pic->move($profile_folder_path,$new_file_name)){
                                $input_data['profile_pic_path']=$profile_folder_path.'/'.$new_file_name;
                                $file_upload_status=true; 
                             }
                         }else{
                             //var_dump('not available');
                             //create folder and upload
                             if(mkdir($profile_folder_path,0755,true)){
                                 if($profile_pic->move($profile_folder_path,$new_file_name)){
                                    $input_data['profile_pic_path']=$profile_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                 }
                             }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                             }
                         }
                    }else{
                        //var_dump("file is not selected");
                        $input_data['profile_pic_path']='';
                    }
                }else{
                    //var_dump("no file");
                    $input_data['profile_pic_path']='';
                }
                
                //save the data 
                //var_dump($input_data['profile_pic_path'],$file_upload_status);
                if($file_upload_status){
                    $result= $this->homemodel->fill_patient_profile($input_data, $user_data);
                }else{
                    $result= $this->homemodel->fill_patient_profile($input_data, $user_data);
                }
            }else{
                //var_dump("no data found");
                $result['status']=true;
                $result['statuscode']=200;
                $result['message']='No user data found';
            }
            if($result['status']){
                //var_dump('sss');
                //generate qr_code
                $generate_qr_code_path= $this->qr_code_for_family_members($result['data']);
                $save_qr_code_path=$this->homemodel->save_family_member_qr_code($generate_qr_code_path,$result['data']['id']);
                //$result['data']=$save_qr_code_path['data'];
                
            }
            unset($result['data']);
            echo json_encode($result); 
        }
        
        public function get_doctor_basic_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->get_doctor_basic_details($data);
            }else{
                $result=$this->homemodel->get_doctor_basic_details($res);
            }
            echo json_encode($result); 
        }
        
        public function add_family_members(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                //check user id valid
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);
                $input_data=$data;
                $user_data=$check_user['data'];
            }else{
                //var_dump($res['user_id'],$res['access_token']);
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
                $input_data=$res;
                $user_data=$check_user['data'];
                //$result=$this->homemodel->add_family_members($res);
            }
            // var_dump($check_user);
            // exit();
            //var_dump($check_user);
            if($check_user['status']){
                //var_dump("success");
                $file_upload_status=false;
                if(isset($_FILES['profile_pic'])){
                    //var_dump($_FILES['profile_pic']);
                    if($_FILES['profile_pic']['name']!=''){
                        //var_dump('ssss');
                        //var_dump("file is selected");
                        $profile_pic=$this->request->getFile('profile_pic');
                        //var_dump($profile_pic);
                        //$file->getName();
                        // Returns 'jpg' (WITHOUT the period)
                        $ext = $profile_pic->guessExtension();
                        //set new file name
                        $new_file_name='IMG_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $profile_folder_path='uploads/family_member_profile_pic';
                        //creating new folder.
                        if(file_exists($profile_folder_path)){
                            //var_dump('path exist');
                             //var_dump('yes available');
                             if($profile_pic->move($profile_folder_path,$new_file_name)){
                                $input_data['profile_pic_path']=$profile_folder_path.'/'.$new_file_name;
                                $file_upload_status=true; 
                             }
                         }else{
                           // var_dump('path not exist');
                             //var_dump('not available');
                             //create folder and upload
                             if(mkdir($profile_folder_path,0755,true)){
                                 if($profile_pic->move($profile_folder_path,$new_file_name)){
                                    $input_data['profile_pic_path']=$profile_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                 }
                                 }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                             }
                         }
                    }else{
                        //var_dump("file is not selected");
                        $input_data['profile_pic_path']='';
                    }
                }else{
                    //var_dump("no file");
                    $input_data['profile_pic_path']='';
                }
                
                //save the data 
                //var_dump($input_data, $user_data);
                //exit();
                if($file_upload_status){
                    $result= $this->homemodel->add_family_members($input_data, $user_data);
                }else{
                    $result= $this->homemodel->add_family_members($input_data, $user_data);
                }
            }else{
                //var_dump("no data found");
                $result['status']=true;
                $result['statuscode']=200;
                $result['message']='No user data found';
            }
            
            if($result['status']){
                //var_dump('sss');
                //generate qr_code
                $generate_qr_code_path= $this->qr_code_for_family_members($result['data']);
                $save_qr_code_path=$this->homemodel->save_family_member_qr_code($generate_qr_code_path,$result['data']['id']);
                //$result['data']=$save_qr_code_path['data'];
                
            }
            unset($result['data']);
            echo json_encode($result); 
        }
        
        public function get_family_members(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->get_family_members($data);
            }else{
                $result=$this->homemodel->get_family_members($res);
            }
            echo json_encode($result); 
        }
        public function get_family_members_data_by_id(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->get_family_members_data_by_id($data);
            }else{
                $result=$this->homemodel->get_family_members_data_by_id($res);
            }
            echo json_encode($result); 
        }
        public function edit_family_members_data(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            //upload profile pic file
            $file_upload_status=false;
            $profile_pic_path='';
            if(isset($_FILES['profile_pic'])){
                
                if($_FILES['profile_pic']['name']!=''){
                    //var_dump($_FILES['profile_pic']);
                    $profile_pic=$this->request->getFile('profile_pic');
                        $ext = $profile_pic->guessExtension();
                        //set new file name
                        $new_file_name='IMG_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $profile_folder_path='uploads/profile_pic';
                        
                         if(file_exists($profile_folder_path)){
                             //var_dump('yes available');
                             if($profile_pic->move($profile_folder_path,$new_file_name)){
                                $profile_pic_path=$profile_folder_path.'/'.$new_file_name;
                                $file_upload_status=true; 
                             }
                         }else{
                             //var_dump('not available');
                             //create folder and upload
                             if(mkdir($profile_folder_path,0755,true)){
                                 if($profile_pic->move($profile_folder_path,$new_file_name)){
                                    $profile_pic_path=$profile_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                 }
                             }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                             }
                         }
                }else{
                    //var_dump('image not picked');
                    $file_upload_status=true; //to save the other datas to server
                }
            }else{
                //var_dump('nnnn');
                $file_upload_status=true; //to save the other datas to server
            }
            //var_dump($file_upload_status,$profile_pic_path);
            if($file_upload_status){
                if(isset($data)){
                    //generate qr_code
                    //$generate_qr_code=$this->qr_code_for_edit_family_members($data);
                    $data['profile_pic_path']=$profile_pic_path;
                    $result=$this->homemodel->edit_family_members_data($data);
                }else{
                    //generate qr_code
                    //$generate_qr_code=$this->qr_code_for_edit_family_members($res);
                    $res['profile_pic_path']=$profile_pic_path;
                    $result=$this->homemodel->edit_family_members_data($res);
                }
            }else{
               $result['status']=false;
                $result['statuscode']=400;
                $result['message']='File is not uploaded to the server'; 
            }
            
            if($result['status']){
                //var_dump('sss');
                //generate qr_code
                $generate_qr_code_path= $this->qr_code_for_family_members($result['data']);
                $save_qr_code_path=$this->homemodel->save_family_member_qr_code($generate_qr_code_path,$result['data']['id']);
                $result['data']=$save_qr_code_path['data'];
            }
            echo json_encode($result); 
        }
        public function qr_code_for_family_members($data=''){
            //var_dump($data);
            $username='Username: '.$data['username'];
            $email='Email ID: '.$data['email_id'];
            $dob='DOB: '.$data['dob'];
            $gender='Gender: '.$data['gender'];
            $blood_group='Blood group: '.$data['blood_group'];
            $height='Height '.$data['height'];
            $weight='Weight '.$data['weight'];
            $qr_code_data=$username.', '.$dob.', '.$gender;
            
            $generate_qrcode=$this->generate_qrcode($qr_code_data);
            return $generate_qrcode;
            
        }
        public function generate_qrcode($data='')
        {
            $qr_code_image_format="qr_code_".date('YmdHis');
            //$hex_data   = bin2hex($qr_code_image_format);
            $save_name  = $qr_code_image_format . '.png';

            /* QR Code File Directory Initialize */
            $dir = 'uploads/qrcode/';
            if (! file_exists($dir)) {
                mkdir($dir, 0775, true);
            }

            /* QR Configuration  */
            $config['cacheable']    = true;
            $config['imagedir']     = $dir;
            $config['quality']      = true;
            $config['size']         = '1024';
            $config['black']        = [255, 255, 255];
            $config['white']        = [255, 255, 255];
            $this->ciqrcode->initialize($config);

            /* QR Data  */
            $params['data']     = $data;
            $params['level']    = 'L';
            $params['size']     = 10;
            $params['savename'] = FCPATH . $config['imagedir'] . $save_name;

            $this->ciqrcode->generate($params);
            return $dir.$save_name;
        }
        
        public function get_doctor_career_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->get_doctor_career_details($data);
            }else{
                $result=$this->homemodel->get_doctor_career_details($res);
            }
            echo json_encode($result); 
        }
        
        public function file_upload(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                //check user id valid
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);
                $input=$data;
            }else{
                //var_dump($res['user_id'],$res['access_token']);
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
                $input=$res;
            }
            $file_upload_status=false;
            $file_path='';
            // var_dump($check_user);
            if($check_user['status']){
                // var_dump("success");
                
                if(isset($_FILES['file'])){
                    // var_dump('ssss');
                    if($_FILES['file']['name']!=''){
                        //var_dump("file is selected");
                        $file=$this->request->getFile('file');
                        //$file->getName();
                        // Returns 'jpg' (WITHOUT the period)
                        $ext = $file->guessExtension();
                        //set new file name
                        $new_file_name='FILE_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $file_folder_path='uploads/files/'.$input['user_id'];
                        
                         if(file_exists($file_folder_path)){
                             //var_dump('yes available');
                             if($file->move($file_folder_path,$new_file_name)){
                                $file_path=$file_folder_path.'/'.$new_file_name;
                                $file_upload_status=true;
                             }
                         }else{
                             //var_dump('not available');
                             //create folder and upload
                             if(mkdir($file_folder_path,0755,true)){
                                 if($file->move($file_folder_path,$new_file_name)){
                                    $file_path=$file_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                 }
                             }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                                $result['file_path']='';
                             }
                         }
                    }
                }
                //var_dump($file_path, $file_upload_status);
                if($file_upload_status){
                    $result['status']=true;
                    $result['statuscode']=200;
                    $result['message']='Success';
                    $result['file_path']=$file_path;
                }else{
                    $result['status']=false;
                    $result['statuscode']=400;
                    $result['message']='File not uploaded';
                    $result['file_path']='';
                }
            }else{
                $result['status']=false;
                $result['statuscode']=200;
                $result['message']='No user found';
                $result['file_path']='';
            }
            echo json_encode($result);
        }
        
        public function add_doctor_basic_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_doctor_basic_details($data);
            }else{
                $result=$this->homemodel->add_doctor_basic_details($res);
            }
            echo json_encode($result);
        }
        public function doctor_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_details($data);
            }else{
                $result=$this->homemodel->doctor_details($res);
            }
            echo json_encode($result); 
        }
        public function qualification_document_upload(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                //check user id valid
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);
                $input=$data;
            }else{
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
                $input=$res;
            }
            $file_upload_status=false;
            $upload_documents='';
            if($check_user['status']){
                if(isset($_FILES['file'])){
                    if($_FILES['file']['name']!=''){
                        $file=$this->request->getFile('file');
                        // Returns 'jpg' (WITHOUT the period)
                        $ext = $file->guessExtension();
                        //set new file name
                        $new_file_name='FILE_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $file_folder_path='uploads/qualification/'.$input['user_id'];
                        if(file_exists($file_folder_path)){
                            if($file->move($file_folder_path,$new_file_name)){
                                $upload_documents=$file_folder_path.'/'.$new_file_name;
                                $file_upload_status=true;
                            }
                        }else{
                            //create folder and upload
                            if(mkdir($file_folder_path,0755,true)){
                                if($file->move($file_folder_path,$new_file_name)){
                                    $upload_documents=$file_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                }
                            }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                                $result['upload_documents']='';
                            }
                        }
                    }
                }
                if($file_upload_status){
                    $result['status']=true;
                    $result['statuscode']=200;
                    $result['message']='Success';
                    $result['upload_documents']=$upload_documents;
                }else{
                    $result['status']=false;
                    $result['statuscode']=400;
                    $result['message']='File not uploaded';
                    $result['upload_documents']='';
                }
            }else{
                $result['status']=false;
                $result['statuscode']=200;
                $result['message']='No user found';
                $result['upload_documents']='';
            }
        echo json_encode($result);
        }
        public function update_employee_qualification(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->update_employee_qualification($data);
            }else{
                $result=$this->homemodel->update_employee_qualification($res);
            }
            echo json_encode($result);
        }
        public function update_employee_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->update_employee_experience($data);
            }else{
                $result=$this->homemodel->update_employee_experience($res);
            }
            echo json_encode($result);
        }

        //reshma

        public function update_family_medicalhistory(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->update_family_medicalhistory($data);
            }else{
                $result=$this->homemodel->update_family_medicalhistory($res);
            }
            echo json_encode($result);
        }
        public function add_family_doctor(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_family_doctor($data);
            }else{
                $result=$this->homemodel->add_family_doctor($res);
            }
            echo json_encode($result);
        }
        public function request_refund(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->request_refund($data);
            }else{
                $result=$this->homemodel->request_refund($res);
            }
            echo json_encode($result);
        }
        public function add_favourite_doctor(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_favourite_doctor($data);
            }else{
                $result=$this->homemodel->add_favourite_doctor($res);
            }
            echo json_encode($result);
        }
        public function list_family_members(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_family_members($data);
            }else{
                $result=$this->homemodel->list_family_members($res);
            }
            echo json_encode($result);
        }
        public function show_family_doctor(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->show_family_doctor($data);
            }else{
                $result=$this->homemodel->show_family_doctor($res);
            }
            echo json_encode($result);
        }
        public function show_favourite_doctors(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->show_favourite_doctors($data);
            }else{
                $result=$this->homemodel->show_favourite_doctors($res);
            }
            echo json_encode($result);
        }
        public function family_doctor_count(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->family_doctor_count($data);
            }else{
                $result=$this->homemodel->family_doctor_count($res);
            }
            echo json_encode($result);
        }
        public function datewise_booked_slot(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->datewise_booked_slot($data);
            }else{
                $result=$this->homemodel->datewise_booked_slot($res);
            }
            echo json_encode($result);
        }
        public function patient_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->patient_details($data);
            }else{
                $result=$this->homemodel->patient_details($res);
            }
            echo json_encode($result); 
        }
        public function list_all_hospitals(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_all_hospitals($data);
            }else{
                $result=$this->homemodel->list_all_hospitals($res);
            }
            echo json_encode($result); 
        }
        public function add_doctor_assistance(){
            $data= json_decode(file_get_contents("php://input"),true);
            // var_dump($data);7
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_doctor_assistance($data);
            }else{
                $result=$this->homemodel->add_doctor_assistance($res);
            }
            echo json_encode($result); 
        }
        public function delete_qualification(){
            $data= json_decode(file_get_contents("php://input"),true);
            //var_dump($data);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->delete_qualification($data);
            }else{
                $result=$this->homemodel->delete_qualification($res);
            }
            echo json_encode($result);
        }
        public function delete_specialization(){
            $data= json_decode(file_get_contents("php://input"),true);
            //var_dump($data);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->delete_specialization($data);
            }else{
                $result=$this->homemodel->delete_specialization($res);
            }
            echo json_encode($result);
        }
        public function delete_work_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            //var_dump($data);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->delete_work_experience($data);
            }else{
                $result=$this->homemodel->delete_work_experience($res);
            }
            echo json_encode($result);
        }
        public function list_all_doctor_assistance(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_all_doctor_assistance($data);
            }else{
                $result=$this->homemodel->list_all_doctor_assistance($res);
            }
            echo json_encode($result);
        }
        public function remove_doctor_assistance(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            // var_dump($data);
            if(isset($data)){
                $result=$this->homemodel->remove_doctor_assistance($data);
            }else{
                $result=$this->homemodel->remove_doctor_assistance($res);
            }
            echo json_encode($result);
        }
        public function delete_family_member(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            // var_dump($data);
            if(isset($data)){
                $result=$this->homemodel->delete_family_member($data);
            }else{
                $result=$this->homemodel->delete_family_member($res);
            }
            echo json_encode($result);
        }
        public function delete_favourite_doctor(){
            $data=json_decode(file_get_contents("php://input"),true);
            $res=$this->input->getpost();
            // var_dump($data);
            if(isset($data)){
                $result=$this->homemodel->delete_favourite_doctor($data);
            }else{
                $result=$this->homemodel->delete_favourite_doctor($res);
            }
            echo json_encode($result);
        }
        public function list_family_members_data_by_id(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_family_members_data_by_id($data);
            }else{
                $result=$this->homemodel->list_family_members_data_by_id($res);
            }
            echo json_encode($result); 
        }
        public function add_user_medical_history(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_user_medical_history($data);
            }else{
                $result=$this->homemodel->add_user_medical_history($res);
            }
            echo json_encode($result);
        }
        public function add_banner_list(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_banner_list($data);
            }else{
                $result=$this->homemodel->add_banner_list($res);
            }
            echo json_encode($result); 
        }
        public function list_all_bannerlist(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_all_bannerlist($data);
            }else{
                $result=$this->homemodel->list_all_bannerlist($res);
            }
            echo json_encode($result); 
        }
        public function list_all_doctors(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_all_doctors($data);
            }else{
                $result=$this->homemodel->list_all_doctors($res);
            }
            echo json_encode($result); 
        }
        public function doctor_available_slots(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_available_slots($data);
            }else{
                $result=$this->homemodel->doctor_available_slots($res);
            }
            echo json_encode($result);
        }
        public function edit_patient_profile(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->edit_patient_profile($data);
            }else{
                $result=$this->homemodel->edit_patient_profile($res);
            }
            echo json_encode($result);
        }
        public function datewise_patient_book_slot(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->datewise_patient_book_slot($data);
            }else{
                $result=$this->homemodel->datewise_patient_book_slot($res);
            }
            echo json_encode($result); 
        }
        public function upload_doctor_medical_history(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->upload_doctor_medical_history($data);
            }else{
                $result=$this->homemodel->upload_doctor_medical_history($res);
            }
            echo json_encode($result);
        }
        public function list_doctor_medical_history(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_doctor_medical_history($data);
            }else{
                $result=$this->homemodel->list_doctor_medical_history($res);
            }
            echo json_encode($result);
        }
        public function patient_book_slot_history(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->patient_book_slot_history($data);
            }else{
                $result=$this->homemodel->patient_book_slot_history($res);
            }
            echo json_encode($result); 
        }
        public function otp(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->otp($data);
            }else{
                $result= $this->homemodel->otp($res);
            }
            echo json_encode($result);
        }
        public function add_lab_refferals(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_lab_refferals($data);
            }else{
                $result=$this->homemodel->add_lab_refferals($res);
            }
            echo json_encode($result); 
        }
        public function get_default_image(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->get_default_image($data);
            }else{
                $result=$this->homemodel->get_default_image($res);
            }
            echo json_encode($result); 
        }
        public function list_all_specialization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_all_specialization($data);
            }else{
                $result=$this->homemodel->list_all_specialization($res);
            }
            echo json_encode($result); 
        }
        public function default_files(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                //check user id valid
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);
                $input=$data;
            }else{
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
                $input=$res;
            }
            $file_upload_status=false;
            $file_path='';
            if($check_user['status']){
                if(isset($_FILES['file'])){
                    if($_FILES['file']['name']!=''){
                        $file=$this->request->getFile('file');
                        // Returns 'jpg' (WITHOUT the period)
                        $ext = $file->guessExtension();
                        //set new file name
                        $new_file_name='FILE_'.date('YmdHis').'.'.$ext;
                        //check folder exist or not
                        $file_folder_path='uploads/default';
                        if(file_exists($file_folder_path)){
                            if($file->move($file_folder_path,$new_file_name)){
                                $file_path=$file_folder_path.'/'.$new_file_name;
                                $file_upload_status=true;
                            }
                        }else{
                            //create folder and upload
                            if(mkdir($file_folder_path,0755,true)){
                                if($file->move($file_folder_path,$new_file_name)){
                                    $file_path=$file_folder_path.'/'.$new_file_name;
                                    $file_upload_status=true;
                                }
                            }else{
                                //folder not created error 
                                $result['status']=false;
                                $result['statuscode']=400;
                                $result['message']='Folder not created';
                                $result['file_path']='';
                            }
                        }
                    }
                }
                if($file_upload_status){
                    $result['status']=true;
                    $result['statuscode']=200;
                    $result['message']='Success';
                    $result['file_path']=$file_path;
                }else{
                    $result['status']=false;
                    $result['statuscode']=400;
                    $result['message']='File not uploaded';
                    $result['file_path']='';
                }
            }else{
                $result['status']=false;
                $result['statuscode']=200;
                $result['message']='No user found';
                $result['file_path']='';
            }
            echo json_encode($result);
        }
        public function family_doctor_history(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->family_doctor_history($data);
            }else{
                $result=$this->homemodel->family_doctor_history($res);
            }
            echo json_encode($result); 
        }
        public function doctor_upcomming_appoinments(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_upcomming_appoinments($data);
            }else{
                $result=$this->homemodel->doctor_upcomming_appoinments($res);
            }
            echo json_encode($result); 
        }
        public function doctor_new_appoinments(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_new_appoinments($data);
            }else{
                $result=$this->homemodel->doctor_new_appoinments($res);
            }
            echo json_encode($result); 
        }
        public function all_patient_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $inp_dat=$data;
            }else{
                $inp_dat=$res;
            }
            $patient_details=$this->homemodel->patient_details($inp_dat);
            $near_by_hospital=$this->homemodel->locationwise_limited_hospitals($inp_dat);
            $near_by_pharmacy=$this->homemodel->locationwise_limited_pharmacy($inp_dat);
            $banner_list=$this->homemodel->list_all_bannerlist($inp_dat);
            $specialization_list=$this->homemodel->list_all_specialization($inp_dat);
            if($patient_details['status']==true){
                $response['patient_details']=$patient_details['data'];
            }
            if($near_by_hospital['status']==true){
                $response['near_by_hospital']=$near_by_hospital['data'];
            }
            if($near_by_pharmacy['status']==true){
                $response['near_by_pharmacy']=$near_by_pharmacy['data'];
            }
            if($banner_list['status']==true){
                $response['banner_list']=$banner_list['data'];
            }
            if($specialization_list['status']==true){
                $response['specialization_list']=$specialization_list['data'];
            }
            // var_dump($response['near_by_hospital']);
            if(isset($response)){
                $result['status']=true;
                $result['statuscode']=200;
            }else{
                $result['status']=false;
                $result['statuscode']=400;
            }
            if(isset($response['patient_details'])){
                $result['patient_details']=$response['patient_details'];
            }else{
                $result['patient_details']=[];
            }
            if(isset($response['near_by_hospital'])){
                $result['near_by_hospital']=$response['near_by_hospital'];
            }else{
                $result['near_by_hospital']=[];
            }
            if(isset($response['near_by_pharmacy'])){
                $result['near_by_pharmacy']=$response['near_by_pharmacy'];
            }else{
                $result['near_by_pharmacy']=[];
            }
            if(isset($response['specialization_list'])){
                $result['specialization_list']=$response['specialization_list'];
            }else{
                $result['specialization_list']=[];
            }
            if(isset($response['banner_list'])){
                $result['banner_list']=$response['banner_list'];
            }else{
                $result['banner_list']=[];
            }
            $result['patient_message']=$patient_details['message'];
            $result['near_by_hospital_message']=$near_by_hospital['message'];
            $result['near_by_pharmacy_message']=$near_by_pharmacy['message'];
            $result['banner_list_message']=$banner_list['message'];
            $result['specialization_list_message']=$specialization_list['message'];
            echo json_encode($result); 
        }
        public function list_favourite_family_doctors(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $inp_dat=$data;
            }else{
                $inp_dat=$res;
            }
            $family_doctor=$this->homemodel->show_family_doctor($inp_dat);
            $favourite_doctor=$this->homemodel->show_favourite_doctors($inp_dat);
            if($family_doctor['status']==true){
                $response['family_doctor']=$family_doctor['data'];
            }
            if($favourite_doctor['status']==true){
                $response['favourite_doctor']=$favourite_doctor['data'];
            }
            if(isset($response)){
                $result['status']=true;
                $result['statuscode']=200;
            }else{
                $result['status']=false;
                $result['statuscode']=400;
            }
            if(isset($response['family_doctor'])){
                $result['family_doctor']=$response['family_doctor'];
            }else{
                $result['family_doctor']=[];
            }
            if(isset($response['favourite_doctor'])){
                $result['favourite_doctor']=$response['favourite_doctor'];
            }else{
                $result['favourite_doctor']=[];
            }
            $result['family_doctor_message']=$family_doctor['message'];
            $result['favourite_doctor_message']=$favourite_doctor['message'];
            echo json_encode($result);
        }
        public function doctors_my_appoinment() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->doctors_my_appoinment($data);
            }else{
                $result= $this->homemodel->doctors_my_appoinment($res);
            }
            echo json_encode($result);
        }
        public function doctor_available_status(){
            $data= json_decode(file_get_contents("php://input"),true);
            //var_dump($data);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_available_status($data);
            }else{
                $result=$this->homemodel->doctor_available_status($res);
            }
            echo json_encode($result);
        }
        public function list_doctor_available_status(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_doctor_available_status($data);
            }else{
                $result=$this->homemodel->list_doctor_available_status($res);
            }
            echo json_encode($result);
        }
        public function doctors_appoinment_history() {
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result= $this->homemodel->doctors_appoinment_history($data);
            }else{
                $result= $this->homemodel->doctors_appoinment_history($res);
            }
            echo json_encode($result);
        }
        public function list_all_doctors_filter(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_all_doctors_filter($data);
            }else{
                $result=$this->homemodel->list_all_doctors_filter($res);
            }
            echo json_encode($result); 
        }
        public function delete_medical_history(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->delete_medical_history($data);
            }else{
                $result=$this->homemodel->delete_medical_history($res);
            }
            echo json_encode($result); 
        }
        public function doctor_profile_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_profile_details($data);
            }else{
                $result=$this->homemodel->doctor_profile_details($res);
            }
            echo json_encode($result); 
        }
        public function list_all_symptoms(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_all_symptoms($data);
            }else{
                $result=$this->homemodel->list_all_symptoms($res);
            }
            echo json_encode($result); 
        }
        public function slot_booking(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->slot_booking($data);
            }else{
                $result=$this->homemodel->slot_booking($res);
            }
            echo json_encode($result); 
        }
        public function doctor_available_slot_details(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->doctor_available_slot_details($data);
            }else{
                $result=$this->homemodel->doctor_available_slot_details($res);
            }
            echo json_encode($result);
        }
        public function hospitalwise_doctor_list(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->hospitalwise_doctor_list($data);
            }else{
                $result=$this->homemodel->hospitalwise_doctor_list($res);
            }
            echo json_encode($result);
        }
        public function delete_doctor_slot(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->delete_doctor_slot($data);
            }else{
                $result=$this->homemodel->delete_doctor_slot($res);
            }
            echo json_encode($result); 
        }
        public function list_generate_medical_report(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_generate_medical_report($data);
            }else{
                $result=$this->homemodel->list_generate_medical_report($res);
            }
            echo json_encode($result);
        }
        public function patient_feedback(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->patient_feedback($data);
            }else{
                $result=$this->homemodel->patient_feedback($res);
            }
            echo json_encode($result);
        }
        public function list_patient_feedback(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_patient_feedback($data);
            }else{
                $result=$this->homemodel->list_patient_feedback($res);
            }
            echo json_encode($result);
        }
        public function generate_medical_report(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->generate_medical_report($data);
            }else{
                $result=$this->homemodel->generate_medical_report($res);
            }
            echo json_encode($result);
        }
        public function add_emergency_contact(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_emergency_contact($data);
            }else{
                $result=$this->homemodel->add_emergency_contact($res);
            }
            echo json_encode($result);
        }
        public function list_emergency_contact(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_emergency_contact($data);
            }else{
                $result=$this->homemodel->list_emergency_contact($res);
            }
            echo json_encode($result);
        }
        public function delete_emergency_contact(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->delete_emergency_contact($data);
            }else{
                $result=$this->homemodel->delete_emergency_contact($res);
            }
            echo json_encode($result);
        }
        public function patient_profile_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $check_user= $this->homemodel->check_user_detail($data['user_id'],$data['access_token']);   //check user id valid
                $input_data=$data;
                $user_data=$check_user['data'];
            }else{
                $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);   //check user id valid
                $input_data=$res;
                $user_data=$check_user['data'];
            }
            if($check_user['status']){
                $result=$this->homemodel->patient_profile_details($input_data,$user_data);
            }else{
                $result['status']=true;
                $result['statuscode']=200;
                $result['message']='No user data found';
            }
            echo json_encode($result); 
        }
        public function show_doctor_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->show_doctor_details($data);
            }else{
                $result=$this->homemodel->show_doctor_details($res);
            }
            echo json_encode($result);
        }
        public function show_pharmacy_details(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->show_pharmacy_details($data);
            }else{
                $result=$this->homemodel->show_pharmacy_details($res);
            }
            echo json_encode($result);
        }
        public function add_case_history(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            // var_dump($res);
            if(isset($data)){
                $result=$this->homemodel->add_case_history($data);
            }else{
                $result=$this->homemodel->add_case_history($res);
            }
            echo json_encode($result); 
        }
        public function list_case_history(){
             $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_case_history($data);
            }else{
                $result=$this->homemodel->list_case_history($res);
            }
            echo json_encode($result);
        }
        public function list_lab_test(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->list_lab_test($data);
            }else{
                $result=$this->homemodel->list_lab_test($res);
            }
            echo json_encode($result);
        }
        public function edit_employee_qualification(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_employee_qualification($data);
            }else{
                $result=$this->homemodel->edit_employee_qualification($res);
            }
            echo json_encode($result);
        }
        public function edit_employee_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_employee_experience($data);
            }else{
                $result=$this->homemodel->edit_employee_experience($res);
            }
            echo json_encode($result);
        }
        public function update_doctor_prescription(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->update_doctor_prescription($data);
            }else{
                $result=$this->homemodel->update_doctor_prescription($res);
            }
            echo json_encode($result);
        }
        public function list_medicine_dosage(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_medicine_dosage($data);
            }else{
                $result=$this->homemodel->list_medicine_dosage($res);
            }
            echo json_encode($result);
        }
        public function list_medicine_time(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_medicine_time($data);
            }else{
                $result=$this->homemodel->list_medicine_time($res);
            }
            echo json_encode($result);
        }
        public function updates_doctor_assistant_status(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->updates_doctor_assistant_status($data);
            }else{
                $result=$this->homemodel->updates_doctor_assistant_status($res);
            }
            echo json_encode($result);
        }
        public function add_medicine(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_medicine($data);
            }else{
                $result=$this->homemodel->add_medicine($res);
            }
            echo json_encode($result);
        }
        public function my_doctor_list(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->my_doctor_list($data);
            }else{
                $result=$this->homemodel->my_doctor_list($res);
            }
            echo json_encode($result);
        }
        public function search_medicine(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->search_medicine($data);
            }else{
                $result=$this->homemodel->search_medicine($res);
            }
            echo json_encode($result);
        }
        public function show_patient_stickers(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->show_patient_stickers($data);
            }else{
                $result=$this->homemodel->show_patient_stickers($res);
            }
            echo json_encode($result);
        }
        public function service_feedback(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->service_feedback($data);
            }else{
                $result=$this->homemodel->service_feedback($res);
            }
            echo json_encode($result);
        }
        public function add_organisation_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_organisation_experience($data);
            }else{
                $result=$this->homemodel->add_organisation_experience($res);
            }
            echo json_encode($result);
        }
        public function list_organisation_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_organisation_experience($data);
            }else{
                $result=$this->homemodel->list_organisation_experience($res);
            }
            echo json_encode($result);
        }
        public function delete_organisation_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->delete_organisation_experience($data);
            }else{
                $result=$this->homemodel->delete_organisation_experience($res);
            }
            echo json_encode($result);
        }
        public function edit_work_experience(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_work_experience($data);
            }else{
                $result=$this->homemodel->edit_work_experience($res);
            }
            echo json_encode($result);
        }
        public function add_doctor_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_doctor_organization($data);
            }else{
                $result=$this->homemodel->add_doctor_organization($res);
            }
            echo json_encode($result);
        }
        public function edit_doctor_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_doctor_organization($data);
            }else{
                $result=$this->homemodel->edit_doctor_organization($res);
            }
            echo json_encode($result);
        } 
        public function list_doctor_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_doctor_organization($data);
            }else{
                $result=$this->homemodel->list_doctor_organization($res);
            }
            echo json_encode($result);
        } 
        public function delete_doctor_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->delete_doctor_organization($data);
            }else{
                $result=$this->homemodel->delete_doctor_organization($res);
            }
            echo json_encode($result);
        } 
        public function add_organization_bank(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_organization_bank($data);
            }else{
                $result=$this->homemodel->add_organization_bank($res);
            }
            echo json_encode($result);
        }
        public function edit_organization_bank(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_organization_bank($data);
            }else{
                $result=$this->homemodel->edit_organization_bank($res);
            }
            echo json_encode($result);
        } 
        public function list_organization_bank(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_organization_bank($data);
            }else{
                $result=$this->homemodel->list_organization_bank($res);
            }
            echo json_encode($result);
        } 
        public function delete_organization_bank(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->delete_organization_bank($data);
            }else{
                $result=$this->homemodel->delete_organization_bank($res);
            }
            echo json_encode($result);
        } 
        public function add_lab_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_lab_organization($data);
            }else{
                $result=$this->homemodel->add_lab_organization($res);
            }
            echo json_encode($result);
        }
        public function edit_lab_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_lab_organization($data);
            }else{
                $result=$this->homemodel->edit_lab_organization($res);
            }
            echo json_encode($result);
        }
        public function list_lab_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_lab_organization($data);
            }else{
                $result=$this->homemodel->list_lab_organization($res);
            }
            echo json_encode($result);
        }
        public function delete_lab_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->delete_lab_organization($data);
            }else{
                $result=$this->homemodel->delete_lab_organization($res);
            }
            echo json_encode($result);
        }
        public function add_pharmacy_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->add_pharmacy_organization($data);
            }else{
                $result=$this->homemodel->add_pharmacy_organization($res);
            }
            echo json_encode($result);
        }
        public function edit_pharmacy_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->edit_pharmacy_organization($data);
            }else{
                $result=$this->homemodel->edit_pharmacy_organization($res);
            }
            echo json_encode($result);
        }
        public function list_pharmacy_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->list_pharmacy_organization($data);
            }else{
                $result=$this->homemodel->list_pharmacy_organization($res);
            }
            echo json_encode($result);
        }
        public function delete_pharmacy_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            $res= $this->input->getpost();
            if(isset($data)){
                $result=$this->homemodel->delete_pharmacy_organization($data);
            }else{
                $result=$this->homemodel->delete_pharmacy_organization($res);
            }
            echo json_encode($result);
        }
        public function add_hospital_doctor_assistance(){
            $data= json_decode(file_get_contents("php://input"),true);
            // var_dump($data);7
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->add_hospital_doctor_assistance($data);
            }else{
                $result=$this->homemodel->add_hospital_doctor_assistance($res);
            }
            echo json_encode($result); 
        }
        public function set_call_status(){
            $data= json_decode(file_get_contents("php://input"),true);
            // var_dump($data);7
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->set_call_status($data);
            }else{
                $result=$this->homemodel->set_call_status($res);
            }
            echo json_encode($result); 
        }
        public function remove_lab_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            // var_dump($data);7
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->remove_lab_organization($data);
            }else{
                $result=$this->homemodel->remove_lab_organization($res);
            }
            echo json_encode($result); 
        }
        public function remove_pharmacy_organization(){
            $data= json_decode(file_get_contents("php://input"),true);
            // var_dump($data);7
            $res= $this->input->getPost();
            if(isset($data)){
                $result=$this->homemodel->remove_pharmacy_organization($data);
            }else{
                $result=$this->homemodel->remove_pharmacy_organization($res);
            }
            echo json_encode($result); 
        }
    }
?>
