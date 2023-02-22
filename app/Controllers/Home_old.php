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
                //var_dump('ssss');
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
    
    public function add_employee_basic_details(){
        
    }
    
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
    
//     public function save_country(){
//        $data=json_decode(file_get_contents("php://input"),true);
//        $res= $this->input->getpost();
//        if(isset($data)){
//        // var_dump($data);
//            $result=$this->homemodel->save_country($data);
//        }else{
//            $result=$this->homemodel->save_country($res);
//        }
//        echo json_encode($result);
//    }
//    public function get_all_country(){
//        $data=json_decode(file_get_contents("php://input"),true);
//        $res=$this->input->getpost();
//        if(isset($data)){
//            $result=$this->homemodel->get_all_country($data);
//        }else{
//            $result=$this->homemodel->get_all_country($res);
//        }
//        echo json_encode($result);
//    }
    
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
            var_dump($res['user_id'],$res['access_token']);
            $check_user= $this->homemodel->check_user_detail($res['user_id'],$res['access_token']);
            $input_data=$res;
            $user_data=$check_user['data'];
            //$result=$this->homemodel->add_family_members($res);
        }
    }
    
}
