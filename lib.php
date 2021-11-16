<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: *");
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/adminlib.php');


function siyavula_get_user_token($siyavula_config, $client_ip){
    global $USER, $PAGE, $CFG;
    

    $data = array(
        'name' => $siyavula_config->client_name,
        'password' => $siyavula_config->client_password,
        'theme' => 'responsive',
        'region' => $siyavula_config->client_region,
        'curriculum' => $siyavula_config->client_curriculum,
        'client_ip' => $client_ip
    );
    
    $api_route  = $siyavula_config->url_base."api/siyavula/v1/get-token";
    $payload = json_encode($data);

    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavula_config->url_base."api/siyavula/v1/get-token",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $payload,
    ));
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = json_decode($response);

    $name = __FUNCTION__;

    if(($siyavula_config->debug_enabled == 1 || $CFG->debugdisplay == 1) && $USER->id != 0){
        siyavula_debug_message( $name, $api_route, $payload, $response, $httpcode);
    }
    
    curl_close($curl);
    return $response->token;
}

function siyavula_get_external_user_token($siyavula_config, $client_ip, $token, $userid = 0){
    global $USER, $CFG;

    $curl = curl_init();
    
    //Check verify user exitis in siyav
    if($userid == 0) {
        $email = $USER->email;
    }
    else {
        $user = core_user::get_user($userid);
        $email = $user->email;
    }
    
    $api_route = $siyavula_config->url_base."api/siyavula/v1/user/".$email.'/token';

    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavula_config->url_base."api/siyavula/v1/user/".$email.'/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array('JWT: '.$token),
    ));

    $payload = $token;
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = json_decode($response);
    
    $name = __FUNCTION__;

    if(($siyavula_config->debug_enabled == 1 || $CFG->debugdisplay == 1) && $USER->id != 0){
        siyavula_debug_message( $name, $api_route, $payload, $response, $httpcode);
    }
    
    curl_close($curl);

    if(isset($response->errors)){
        return siyavula_create_user($siyavula_config, $token);
    }else{
        return $response;
    }
}

function siyavula_create_user($siyavula_config, $token){

    global $USER, $CFG;

    $data = array(
        'external_user_id' => $USER->email,
        "role" => "Learner",
        "name" => $USER->firstname,
        "surname" => $USER->lastname,
        "password" => "123456",
        "grade" => isset($USER->profile['grade']) ? $USER->profile['Grade'] : 1,
        "country" => $USER->country != '' ? $USER->country : $siyavula_config->client_region,
        "curriculum" => isset($USER->profile['curriculum']) ? $USER->profile['Grade'] : $siyavula_config->client_curriculum,
        'email' => $USER->email,
        'dialling_code' => '27',
        'telephone' =>  $USER->phone1
    );
    
    $payload = json_encode($data);

    $curl = curl_init();
  
    $api_route = $siyavula_config->url_base."api/siyavula/v1/user";
  
    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavula_config->url_base."api/siyavula/v1/user",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => array('JWT: '.$token),
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = json_decode($response);
    
    $name = __FUNCTION__;

    if(($siyavula_config->debug_enabled == 1 || $CFG->debugdisplay == 1) && $USER->id != 0){
        siyavula_debug_message( $name, $api_route, $payload, $response, $httpcode);
    }
    
    curl_close($curl);
    return $response;
}

function siyavula_debug_message($name_function, $api_route, $payload, $response, $httpcode){
  
    global $CFG;
    
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $payload_array = json_decode($payload);

    $payloadname         = 'Name :'.$payload_array->name;
    $payloadpassword     = 'Password : '.$payload_array->password;
    $payloadregion       = 'Region : '.$payload_array->region;
    $payloadcurriculum   = 'Curriculum : '.$payload_array->curriculum;
    $payloadip           = 'Client ip : '.$payload_array->client_ip;
    $payloadtheme        = 'Theme : '.$payload_array->theme;
    
    $siyavula_config = get_config('filter_siyavula');
    
    $function            = '<strong>'.get_string('function_name','filter_siyavula').'</strong> '.$name_function;
    $apiroute            = '<strong>'.get_string('api_call','filter_siyavula').'</strong> '.$api_route;
    
    if(empty($response->token)){
        $message = get_string('message_debug', 'filter_siyavula');
    }else{
        $message = $response->token;
    }

    if(isset($response->errors)){
        $errors = $response->errors[0]->code.' - '.$response->errors[0]->message;
    }else if($httpcode == 0){
        $errors = get_string('client_header','filter_siyavula');
    }
 
    $print_debuginfo = '<div class="alert alert-danger" role="alert">
                             <span><strong>'.get_string('info_filter', 'filter_siyavula').'</strong></span>'.$message.' <br>
                            '.$function.'<br>
                            '.$apiroute.'<br>
                              <span><strong>'.get_string('info_payload','filter_siyavula').'</strong></span> <br>
                            '.$payload.'<br>
                            '.$payloadname.'<br>
                            '.$payloadpassword.'<br>
                            '.$payloadregion.'<br>
                            '.$payloadcurriculum.'<br>
                            '.$payloadip.'<br>
                            '.$payloadtheme.'<br>
                            <span><strong>'.get_string('info_code_response','filter_siyavula').'</strong></span> <br>
                            '.$httpcode.'<br>
                            <span><strong>'.get_string('info_message_response','filter_siyavula').'</strong></span> <br>
                            '.$errors.'<br>
                        </div>';
    
    $print_token = '<div class="alert alert-danger" role="alert">
                            <span><strong>'.get_string('token', 'filter_siyavula').'</strong></span>' . $message.' <br>
                    </div>';
                    
    $print_token = '<div class="alert alert-danger" role="alert">
                            <span><strong>'.get_string('token', 'filter_siyavula').'</strong></span>' . $message.' <br>
                    </div>';
    
    echo $print_debuginfo;
    echo $print_token;


    /*error_reporting(E_ALL); // NOT FOR PRODUCTION SERVERS!
    @ini_set('display_errors', '1');    // NOT FOR PRODUCTION SERVERS!
    $CFG->debug = (E_ALL | E_STRICT);   // === DEBUG_DEVELOPER - NOT FOR PRODUCTION SERVERS!
    $CFG->debugdisplay = 1;            // NOT FOR PRODUCTION SERVERS!*/
}

function validate_params($data){
    
    global $CFG, $PAGE;

    saved_data($data);
    
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $siyavula_config = get_config('filter_siyavula');
    
    $message = '';
    $success  = '';
    
    if($siyavula_config->url_base != "https://www.siyavula.com/"){
        $message ='<span>'.get_string('urlbasesuccesserror', 'filter_siyavula').'</span><br>';
    }else{
        $success  = '<span>'.get_string('urlbasesuccess', 'filter_siyavula').'</span><br>';
    }
    
    
    $get_token = siyavula_get_user_token($siyavula_config,$client_ip);
    
    if($get_token == NULL){
        $message .= '<span>'.get_string('token_error', 'filter_siyavula').'</span><br>';
    }else{
        $success  .= '<span>'.get_string('token_generated', 'filter_siyavula').'</span><br>';
    }
    
    
    $external_token = siyavula_get_external_user_token($siyavula_config, $client_ip, $get_token, $userid = 0);
    if($external_token->token == NULL){
        $message .= '<span>'.get_string('token_externalerror','filter_siyavula').'</span><br>';
    }else{
        $success  .= '<span>'.get_string('token_externalgenerated','filter_siyavula').'</span><br>';
    }
    
    if($PAGE->pagetype == 'admin-setting-filtersettingsiyavula'){
        if($message != NULL){
            redirect($PAGE->url,$message,null,\core\output\notification::NOTIFY_ERROR);
        }else{
            //saved_data($data);
            redirect($PAGE->url,$success,null,\core\output\notification::NOTIFY_INFO);
        }
    }
    
}

function saved_data($data){
    $newdata = (array)$data;
    unset($newdata['section']);
    unset($newdata['action']);
    unset($newdata['sesskey']);
    unset($newdata['return']);
    
    foreach($newdata as $name => $value){
        $name = str_replace('s_filter_siyavula_','',$name);
        set_config($name,$value,'filter_siyavula');
    }
}

function get_list_users($siyavula_config,$token){

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavula_config->url_base."api/siyavula/v1/users",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array('JWT: '.$token),
    ));

    $response = curl_exec($curl);
    $response = json_decode($response);
    
    curl_close($curl);
    return $response;
}

function test_get_external_user_token($siyavula_config, $client_ip, $token, $email){
    global $USER, $CFG;
    
    $curl = curl_init();
    
    $api_route = $siyavula_config->url_base."api/siyavula/v1/user/".$email.'/token';

    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavula_config->url_base."api/siyavula/v1/user/".$email.'/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array('JWT: '.$token),
    ));

    $payload = $token;
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = json_decode($response);
    
    $name = __FUNCTION__;

    if(($siyavula_config->debug_enabled == 1 || $CFG->debugdisplay == 1) && $USER->id != 0){
        siyavula_debug_message( $name, $api_route, $payload, $response, $httpcode);
    }
    
    curl_close($curl);

    if(isset($response->errors)){
        return siyavula_create_user($siyavula_config, $token);
    }else{
        return $response;
    }
}