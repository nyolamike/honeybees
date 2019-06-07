<?php
    /* error reportring */
    error_reporting(E_ALL ^ E_WARNING);
    /*end error reporting*/

    /*cors */
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }
    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])){
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");      
        }   

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])){
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }

    }
    /*end cors */

    

    use Emarref\Jwt\Claim;
    $ERRORS = array();

    
    include("tools.php");
    include("mixer.php");
    include("db.php");

    //applications folder name
    define(IS_IN_PRODUCTION,false);
    define(GARDEN,tools_get_current_folder_name());
    define(BASE_URI, "/".GARDEN."/"."bee/");
    define(SERVER_NAME, (IS_IN_PRODUCTION ? "" : "localhost"));
    define(USER_NAME, (IS_IN_PRODUCTION ? "" : "root"));
    define(PASSWORD, (IS_IN_PRODUCTION ? "" : ""));
    define(SHOW_SQL_ON_ERRORS, true);
    define(APP_SECRET,"mysupersecuresecret");
    define(JWT_AUDIENCE,"mysuperapp");
    define(JWT_ISSUER,"mysuperapp");
    define(STRICT_HIVE,false);
    $JWT_ALGORITHM = new Emarref\Jwt\Algorithm\Hs256(APP_SECRET);
    $JWT_ENCRYPTION = Emarref\Jwt\Encryption\Factory::create($JWT_ALGORITHM);
    define(RI,0);//RESULTS INDEX
    define(EI,1);//ERROR INDEX
    define(SI,2);//STRUCTURE INDEX
    define(SEP,"__");//
    define(ANN,"_a");//attribute node name
    define(WNN,"_w");//where node name
    define(FNN,"_for");//for node name used in indicating the structure file name

    //nyd
    //implement transactions and rollback
    

    //get the headers
    $headers = apache_request_headers();
    $AUTHORISATION_TOKEN_STRING = "";
    $AUTHORISATION_TOKEN = null;
    if(isset($headers["Authorization"]) && stripos($headers["Authorization"],"Bearer ") > -1){
        $AUTHORISATION_TOKEN_STRING = trim(str_ireplace("Bearer ","",$headers["Authorization"]));
    }else if(isset($headers["authorization"]) && stripos($headers["authorization"],"Bearer ") > -1){
        $AUTHORISATION_TOKEN_STRING = trim(str_ireplace("Bearer ","",$headers["Authorization"]));
    }else if(isset($headers["AUTHORIZATION"]) && stripos($headers["authorization"],"Bearer ") > -1){
        $AUTHORISATION_TOKEN_STRING = trim(str_ireplace("Bearer ","",$headers["Authorization"]));
    }
    
    //routing
    $request_uri = $_SERVER["REQUEST_URI"];
    $method = "get";
    if($_SERVER["REQUEST_METHOD"] == "GET"){
        $method = "get";
    }else if($_SERVER["REQUEST_METHOD"] == "POST"){
        $method = "post";
    }else if($_SERVER["REQUEST_METHOD"] == "PUT"){
        $method = "put";
    }else if($_SERVER["REQUEST_METHOD"] == "DELETE"){
        $method = "delete";
    }
    $REQUESTED_ROUTE = trim(trim(strtolower(substr($request_uri,strlen(BASE_URI)))),"/");

    //there are two categories of resources here the protected
    //and non protected resoureces
    $IS_AUTHORIZATION_OK = false;
    if($AUTHORISATION_TOKEN_STRING == ""){
        //e.g register login
        $IS_AUTHORIZATION_OK = true;
    }else{

        //validate headers in order to access these resources
        //https://github.com/emarref/jwt
        $jwt = new Emarref\Jwt\Jwt();
        $AUTHORISATION_TOKEN = $jwt->deserialize($AUTHORISATION_TOKEN_STRING);
        $context = new Emarref\Jwt\Verification\Context($JWT_ENCRYPTION);
        $context->setAudience(JWT_AUDIENCE);
        $context->setIssuer(JWT_ISSUER);
        //verify a token's claims
        try {
            $jwt->verify($AUTHORISATION_TOKEN, $context);
            $IS_AUTHORIZATION_OK = true;
        } catch (Emarref\Jwt\Exception\VerificationException $e) {
            $IS_AUTHORIZATION_OK = false;
            echo $e->getMessage();
        }
    }

    if($IS_AUTHORIZATION_OK == true){
        $temp_postdata = file_get_contents("php://input");
        $temp = tools_jsonify($temp_postdata);
        $POSTED_DATA = $temp[0];
        $ERRORS = array_merge($ERRORS,$temp[1]);
        $RES = array("status"=>200);

        //the garden structure
        $garden_json = file_get_contents("_garden.json");
        $temp_garden_struct = tools_jsonify($garden_json);
        $GARDEN_STRUCTURE = $temp_garden_struct[0]; 
        $GARDEN_STRUCTURE["_for"] = "_garden.json";
        $ERRORS = array_merge($ERRORS,$temp_garden_struct[1]);

        //var_dump($GARDEN_STRUCTURE);

        //the first request will create this master db
        //config: username,password,servername,databasename,
        $config_ege = array(
            "username" => USER_NAME,
            "password" => PASSWORD,
            "servername" => SERVER_NAME,
            "databasename" => GARDEN,
            "show_sql_on_errors" => SHOW_SQL_ON_ERRORS
        );
        $temp_ensure_garden = tools_ensure_garden_exists($config_ege,$GARDEN_STRUCTURE);
        $ERRORS = array_merge($ERRORS,$temp_ensure_garden[1]);
        $GARDEN_CONNECTION = $temp_ensure_garden[0]; 
        //there is only one end point bee
        //and one method post
        $REQUESTED_ROUTE = "";
        if($REQUESTED_ROUTE == "" && $method == "post" ){ 
            $tools_gardern_res = tools_get_garden($GARDEN_CONNECTION,SHOW_SQL_ON_ERRORS,$GARDEN_STRUCTURE);
            $ERRORS = array_merge($ERRORS,$tools_gardern_res[1]);
            $GARDEN_STRUCTURE = $tools_gardern_res[2];
            if(count($ERRORS) == 0){ //only continue when we have no errors
                $GARDEN = $tools_gardern_res[0];
                $RES["garden"] = $GARDEN;
                //     foreach ($POSTED_DATA as $prop => $value) {
                //         if($prop=="_create"){
                //             $temp_process_res = tools_create($value);
                //             $ERRORS = array_merge($ERRORS,$temp_process_res[1]);
                //             $RES["_created"] = $temp_process_res[0];
                //         }
                //     }
                
                
                
                //the hive has to be part of the token
                //$databasename = $is_in_production ? "" : $db_name;

                //check if this garden exists if not create one
                //$errors = array_merge(db_run("CREATE DATABASE IF NOT EXISTS " . GARDEN,"")[1]);
            }
            $GARDEN_CONNECTION = null;
            tools_respond($RES,$ERRORS);
        }
        //close garden connection
        $GARDEN_CONNECTION = null;
    }
    

    //if request reached this point then it was not handled
    http_response_code(404);
    include('bees_404.php'); 
    die();
    
?>