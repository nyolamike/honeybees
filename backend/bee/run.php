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
        exit();
    }
    /*end cors */

    require __DIR__ . '/vendor/autoload.php';
    use Emarref\Jwt\Claim;

    //load layers
    include("tools.php"); //utility layer
    include("Inflect.php"); //pluralisation layer
    include("countries_data.php"); //countries data
    include("bee_security.php"); //security layer
    include("hive.php"); //database layer

    include("bee_hive_run_register_hive.php"); //hive registration
    include("bee_hive_run_activate_hive.php"); //hive activation
    include("bee_hive_run_login.php"); //hive login
    include("bee_hive_run_recover_hive.php");// hive recovery
    include("bee_hive_run_reset_hive.php");// hive reset
    include("bee_hive_run_update_password.php");// hive reset

    include("segmentation.php"); //interpretation layer
    include("sqllization.php"); //interpretation layer
    include("production.php"); //production layer
    include("packaging.php"); //production layer

    include("tracers.php"); //debuging layer
    include("mailer.php"); //communiction layer

    include("bee_run_handle_token.php"); //request layer
    include("bee_run_handle_get.php"); //request layer
    include("bee_run_handle_post.php"); //request layer
    include("bee_run_handle_put.php"); //request layer
    include("bee_run_handle_update.php"); //request layer
    include("bee_run_handle_delete.php"); //request layer

    $bee_show_sql =  false;
    $bee_sql_backet = array(
        "selects" => array(),
        "inserts" => array(),
        "updates" => array(),
        "deletes" => array(),
        "other" => array()
    );
    define("BEE_IS_IN_PRODUCTION",false);
    define("BEE_BASE_URI", "/".GARDEN."/"."bee/");
    define("BEE_SERVER_NAME", (BEE_IS_IN_PRODUCTION ? "mysql_srv" : "mysql_srv"));
    define("BEE_USER_NAME", (BEE_IS_IN_PRODUCTION ? "root" : "root"));
    define("BEE_PASSWORD", (BEE_IS_IN_PRODUCTION ? "mysql-2015" : "mysql-2015"));
    define("BEE_SHOW_SQL_ON_ERRORS", true);
    define("BEE_APP_SECRET","mysupersecuresecret");
    define("BEE_JWT_AUDIENCE","mysuperapp");
    define("BEE_JWT_ISSUER","mysuperapp");
    define("BEE_STRICT_HIVE",false);
    $BEE_JWT_ALGORITHM = new Emarref\Jwt\Algorithm\Hs256(BEE_APP_SECRET);
    //$BEE_JWT_ALGORITHM = new Emarref\Jwt\Algorithm\Rs256(BEE_APP_SECRET);
    $BEE_JWT_ENCRYPTION = Emarref\Jwt\Encryption\Factory::create($BEE_JWT_ALGORITHM);
    define("BEE_RI",0);//RESULTS INDEX
    define("BEE_EI",1);//ERROR INDEX
    define("BEE_SI",2);//STRUCTURE INDEX
    define("BEE_SEP","__");//
    define("BEE_ANN","_a");//attribute node name
    define("BEE_WNN","_w");//where node name
    define("BEE_FNN","_for");//for node name used in indicating the structure file name
    define("BEE_GARDEN_STUCTURE_FILE_NAME","bee/_garden.json");
    define("BEE_HIVE_STUCTURE_FILE_NAME","_hive.json");
    define("BEE_DEFAULT_PASSWORD","qwerty");
    $BEE_GLOBALS = array(
        "is_login_call" => false
    );
    $BEE_ERRORS = array();
    $xt_order = array();

    //get the hive of the application
    $tjx_res = tools_jsonify(file_get_contents(BEE_HIVE_STUCTURE_FILE_NAME));
    $BEE_HIVE_STRUCTURE = $tjx_res[0];
    $BEE_ERRORS = array_merge($BEE_ERRORS,$tjx_res[BEE_EI]);
    define("BEE_HIVE_OF_A",$BEE_HIVE_STRUCTURE["hive_of_a"]);
    $BEE_HIVE_CONNECTION = null;
    if(isset($BEE_HIVE_STRUCTURE["app_name"])){
        define('BEE_GARDEN',$BEE_HIVE_STRUCTURE["app_name"]);
    }else{
        define('BEE_GARDEN',tools_get_app_folder_name());
    }

    //the garden structure
    //every hive will have its structure e.g _hive.json
    //but this is the structure of the master hive
    $tj_res = tools_jsonify(file_get_contents(BEE_GARDEN_STUCTURE_FILE_NAME));
    $BEE_GARDEN_STRUCTURE = $tj_res[0]; 
    $BEE_ERRORS = array_merge($BEE_ERRORS,$tj_res[BEE_EI]);

    $hrege_res = hive_run_ensure_garden_exists($BEE_GARDEN_STRUCTURE);
    $BEE_ERRORS = array_merge($BEE_ERRORS,$hrege_res[BEE_EI]);
    $BEE_GARDEN_CONNECTION = $hrege_res[BEE_RI];
    $BEE_GARDEN = null;
    //get in the current state of the garden
    if(count($BEE_ERRORS)==0){
        $hrgg_res = hive_run_get_garden($BEE_GARDEN_STRUCTURE,$BEE_GARDEN_CONNECTION);
        $BEE_ERRORS = array_merge($BEE_ERRORS,$hrgg_res[BEE_EI]);
        $BEE_GARDEN_STRUCTURE = $hrgg_res[2];
        //tools_reply($hrgg_res[BEE_RI],$BEE_ERRORS,array($BEE_GARDEN_CONNECTION));
        $BEE_GARDEN = $hrgg_res[BEE_RI];
        //tools_dumpx("BEE_GARDEN: ",__FILE__,__LINE__,$BEE_GARDEN);
    }
    define(BEE_ENFORCE_RELATIONSHIPS,false); //nyd get value from hive structure
    $BEE = array(
        "BEE_HIVE_STRUCTURE" => $BEE_HIVE_STRUCTURE,
        "BEE_GARDEN_STRUCTURE" => $BEE_GARDEN_STRUCTURE,
        "BEE_GARDEN_CONNECTION" => $BEE_GARDEN_CONNECTION,
        "BEE_HIVE_CONNECTION" => null,
        "BEE_GARDEN" => $BEE_GARDEN,
        "BEE_ERRORS" => $BEE_ERRORS,
        "BEE_JWT_ENCRYPTION" => $BEE_JWT_ENCRYPTION,
        "BEE_USER" => array("id"=>0)
    );
    define(BEE_SUDO_DELETE,$BEE_HIVE_STRUCTURE["sudo_delete"]);

    function bee_run_register_hive($registration_nector,$bee){
        //$hrrh_res = hive_run_register_hive($registration_nector, $bee);
        //tools_dumpx("part a: ",__FILE__,__LINE__,$bee);
        if(array_key_exists("is_registration_offline",$bee["BEE_HIVE_STRUCTURE"]) && 
            $bee["BEE_HIVE_STRUCTURE"]["is_registration_offline"] == true){
            $sendEmail = false;
            $hrrh_res = hive_run_register_hive($register_nector,$bee,array(
                "code" => "",
                "status" => "active",
                "is_owner" => 1
            ));
        }else{
            $hrrh_res = hive_run_register_hive($register_nector,$bee);
        }
        return $hrrh_res;
    }

    function bee_run_post($nectoroid,$bee,$user_id){
        global $BEE_GLOBALS;
        global $countries_list;
        $res = array(null,array(),null);

        
        
        //tools_dumpx("here in post",__FILE__,__LINE__,$nectoroid);

        //go through the entire nectorid processing
        //node by node on the root
        $whole_honey = array();
        foreach ($nectoroid as $root_node_name => $root_node) {
            
            if(tools_startsWith($root_node_name,"_")){
                //conditions
                if(tools_startsWith($root_node_name,"_if")){
                    $whole_honey[$root_node_name] = $root_node;
                }else{
                    continue;
                }
            }
            //tools_dumpx("here in post foreach loop",__FILE__,__LINE__,$root_node);
            //nyd
            //check if user is authorised to post data here
            $nector = array();
            $nector[$root_node_name] = $root_node;
            $brp_res = bee_hive_post(
                $nector,
                $bee["BEE_HIVE_STRUCTURE"]["combs"],
                $bee["BEE_HIVE_CONNECTION"],
                $bee["BEE_USER"]["id"],
                $whole_honey
            );
            //tools_dumpx("here brp_res",__FILE__,__LINE__,$brp_res);
            $whole_honey[$root_node_name] = $brp_res[BEE_RI][$root_node_name];
            $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
            
            if( count($brp_res[BEE_EI]) == 0 && 
                ($root_node_name == "user" || $root_node_name == "users") && 
                (!isset($BEE_GLOBALS["is_register_call"]))
            ){
                //need to add these people as hive users
                if($root_node_name == "user"){
                    $password = "";
                    if(isset($root_node["password"])){
                        $password = password_hash($root_node["password"], PASSWORD_DEFAULT);
                    }elseif(isset($root_node["_encrypt_password"])){
                        $password = password_hash($root_node["_encrypt_password"], PASSWORD_DEFAULT);
                    }
                    
                    //was a single object
                    $hive_user_nector = array(
                        "hive_user" => array(
                            "hive_id" => $bee["BEE_HIVE_ID"], //cbh
                            "hive_name" => $bee["BEE_APP_NAME"],
                            "user_id" =>  $whole_honey["user"],
                            "email" => $root_node["email"],
                            "password" => $password
                        )
                    );
                    $brp_res3 = bee_hive_post($hive_user_nector,$bee["BEE_GARDEN_STRUCTURE"],$bee["BEE_GARDEN_CONNECTION"],$bee["BEE_USER"]["id"]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res3[BEE_EI]);
                }else{
                    //was many objects
                    $index = 0;
                    foreach ($root_node as $user_node_key => $user_node) {
                        $password = "";
                        if(isset($user_node["password"])){
                            $password = password_hash($user_node["password"], PASSWORD_DEFAULT);
                        }elseif(isset($user_node["_encrypt_password"])){
                            $password = password_hash($user_node["_encrypt_password"], PASSWORD_DEFAULT);
                        }
                        //was a single object
                        $hive_user_nector = array(
                            "hive_user" => array(
                                "hive_id" => $bee["BEE_HIVE_ID"], //cbh
                                "hive_name" => $bee["BEE_APP_NAME"],
                                "user_id" =>  $whole_honey["users"][$index],
                                "email" => $user_node["email"],
                                "password" => $password
                            )
                        );
                        $brp_res3 = bee_hive_post($hive_user_nector,$bee["BEE_GARDEN_STRUCTURE"],$bee["BEE_GARDEN_CONNECTION"],$bee["BEE_USER"]["id"]);
                        $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res3[BEE_EI]);
                        $index = $index + 1;
                    }
                }
            }
        }

        $res[BEE_RI] = $whole_honey;
        $res[2] = $bee;
        return $res; 
    }

    function bee_run_update($nectoroid,$bee,$user_id,$current_raw_honey=null){
        $res = array(null,array(),null);

        //go through the entire nectorid processing
        //node by node on the root
        $whole_honey = array();
        foreach ($nectoroid as $root_node_name => $root_node) {
            
            if(tools_startsWith($root_node_name,"_")){
                //tools_dumpx("iam here",__FILE__,__LINE__,1);
                //fx
                if(tools_startsWith($root_node_name,"_fxc_")){
                    $comb_namex = substr($root_node_name,strlen("_fxc_"));
                    $comb_name  = Inflect::singularize($comb_namex);
                    $bsfu_res = bee_sqllization_fxc_update($comb_name, $root_node, $bee["BEE_HIVE_STRUCTURE"]["combs"], $user_id, $bee["BEE_HIVE_CONNECTION"]);
                    $whole_honey[$root_node_name] = $bsfu_res[BEE_RI];
                    $res[BEE_EI] = array_merge($res[BEE_EI],$bsfu_res[BEE_EI]);
                }
                continue;
            }
            //tools_dumpx("here in post foreach loop",__FILE__,__LINE__,$root_node);
            //nyd
            //check if user is authorised to post data here

            $comb_name = Inflect::singularize($root_node_name);
            if($root_node_name == $comb_name){//single object
                $nector = array();
                $nector[$root_node_name] = $root_node;

                $brp_res = bee_hive_update(
                    $nector,
                    $bee["BEE_HIVE_STRUCTURE"]["combs"],
                    $bee["BEE_HIVE_CONNECTION"],
                    $bee["BEE_USER"]["id"],
                    $whole_honey
                );
                //tools_dumpx("here brp_res",__FILE__,__LINE__,$brp_res);
                $whole_honey[$root_node_name] = $brp_res[BEE_RI][$root_node_name];
                $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
            }else{//probably plural
                $updates = array();
                foreach ($root_node as $update_node ) {
                    $nector = array();
                    $nector[$root_node_name] = $update_node;
                    $brp_res = bee_hive_update(
                        $nector,
                        $bee["BEE_HIVE_STRUCTURE"]["combs"],
                        $bee["BEE_HIVE_CONNECTION"],
                        $bee["BEE_USER"]["id"],
                        $whole_honey,
                        $current_raw_honey
                    );
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
                    array_push($updates, $brp_res[BEE_RI][$root_node_name]);
                }
                $whole_honey[$root_node_name] = $updates;
            }
        }

        $res[BEE_RI] = $whole_honey;
        $res[2] = $bee;
        return $res; 
    }


    function bee_run_delete($nectoroid,$bee,$user_id){
        $res = array(null,array(),null);

        $is_restricted = false;
        if(isset($bee["BEE_HIVE_STRUCTURE"]["is_restricted"])){
            $is_restricted = $bee["BEE_HIVE_STRUCTURE"]["is_restricted"];
        }
        

        //go through the entire nectorid processing
        //node by node on the root
        $whole_honey = array();
        foreach ($nectoroid as $root_node_name => $root_node) {
            
            if(tools_startsWith($root_node_name,"_")){
                continue;
            }
            //tools_dumpx("here in post foreach loop",__FILE__,__LINE__,$root_node);
            //nyd
            //check if user is authorised to delete data here
            $nector = array();
            $nector[$root_node_name] = $root_node;
            
            $brp_res = bee_hive_delete(
                $nector,
                $bee["BEE_HIVE_STRUCTURE"]["combs"],
                $bee["BEE_HIVE_CONNECTION"],
                $bee["BEE_USER"]["id"],
                $is_restricted
            );
            
            //tools_dumpx("here brp_res",__FILE__,__LINE__,$brp_res);
            $whole_honey[$root_node_name] = $brp_res[BEE_RI][$root_node_name];
            $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
        }

        $res[BEE_RI] = $whole_honey;
        $res[2] = $bee;
        return $res; 
    }

    
    function bee_run_get($nectoroid,$structure,$connection){
        $res = array(null,array(),$structure);
        
        //tools_dump("@0 == ",__FILE__,__LINE__,$nectoroid);
        $sr_res = segmentation_run($nectoroid,$structure,$connection);
        //tools_dump("segmentation_run",__FILE__,__LINE__,$sr_res[BEE_RI]);
        $hasr_res = hive_after_segmentation_run($sr_res,$nectoroid,$structure,$connection);
        $res[BEE_RI] = $hasr_res[BEE_RI];
        $res[BEE_EI] = array_merge($res[BEE_EI],$hasr_res[BEE_EI]);
        $res[2] = $hasr_res[2];
        return $res; 
    }

    if(array_key_exists("drone_security_enabled",$BEE_HIVE_STRUCTURE)){
        $dse = $BEE_HIVE_STRUCTURE["drone_security_enabled"];
        define(BEE_DRONE_SECURITY_ENABLED,$dse);
    }


    //nyd
    //get the children_tree from the hive structure


    function bee_handle_requests($bee){
        global $BEE_GLOBALS;
        global $countries_list;
        $res = array(null,array(),null);
        $res[BEE_EI] = array_merge($bee["BEE_ERRORS"],$res[BEE_EI]);
        
        $brht_res = bee_run_handle_token($res,$bee);
        $res = $brht_res["res"];
        $bee = $brht_res["bee"];
        if(count($res[BEE_EI])>0){
            tools_reply($res[BEE_RI],$res[BEE_EI],array(
                $bee["BEE_GARDEN_CONNECTION"],
                $bee["BEE_HIVE_CONNECTION"]
            ));
            return 0;
        }

       
        if($_SERVER["REQUEST_METHOD"] == "GET"){
            $res = bee_run_handle_get($res,$bee,null);
        }else if($_SERVER["REQUEST_METHOD"] == "POST"){
            $res = bee_run_handle_post($res,$bee,null);
        }else if($_SERVER["REQUEST_METHOD"] == "PUT"){
            $res = bee_run_handle_put($res,$bee,null);
        }else if($_SERVER["REQUEST_METHOD"] == "UPDATE"){
            $res = bee_run_handle_update($res,$bee,null);
        }else if($_SERVER["REQUEST_METHOD"] == "DELETE"){
            $res = bee_run_handle_delete($res,$bee,null);
        }

        tools_reply($res[BEE_RI],$res[BEE_EI],array(
            $bee["BEE_GARDEN_CONNECTION"],
            $bee["BEE_HIVE_CONNECTION"]
        ));
    }
    
    

    //this is the last in this file
    //register my application
    //returns the connection to the hive
    if($BEE_HIVE_STRUCTURE["is_registration_public"] == false){
        $brrh_res = bee_run_register_hive(array(
            "_f_register" => $BEE_HIVE_STRUCTURE["_f_register"]
        ), $BEE);
        $BEE_HIVE_CONNECTION = $brrh_res[BEE_RI];
        $BEE["BEE_HIVE_CONNECTION"] = $BEE_HIVE_CONNECTION;
        
        
        

        //nyd
        //get in the current state of the garden only if there was creation of new
        //hive, the current code  below will run allways 
        if(count($BEE_ERRORS)==0){
            $hrgg_res = hive_run_get_garden($BEE_GARDEN_STRUCTURE,$BEE_GARDEN_CONNECTION);
            $BEE_ERRORS = array_merge($BEE_ERRORS,$hrgg_res[BEE_EI]);
            $GARDEN_STRUCTURE = $hrgg_res[2];
            //tools_reply($hrgg_res[BEE_RI],$BEE_ERRORS,array($BEE_GARDEN_CONNECTION));
            $BEE_GARDEN = $hrgg_res[BEE_RI];
            //roles,permissions, modules
            $security_nector = array(
                "roles" => array(
                    "role_permisiions" => array(),
                    "role_modules" => array()
                )
            );
            $brg_res = bee_run_get($security_nector,$BEE_HIVE_STRUCTURE["combs"],$BEE_HIVE_CONNECTION);
            $BEE_ERRORS = array_merge($BEE_ERRORS,$brg_res[BEE_EI]);
            $BEE_ROLES = $brg_res[BEE_RI]["roles"];
            //tools_dumpx("brg_res",__FILE__,__LINE__,$brg_res[BEE_RI]);

            $BEE = array(
                "BEE_ROLES" => $BEE_ROLES,
                "BEE_HIVE_STRUCTURE" => $BEE_HIVE_STRUCTURE,
                "BEE_GARDEN_STRUCTURE" => $GARDEN_STRUCTURE,
                "BEE_GARDEN_CONNECTION" => $BEE_GARDEN_CONNECTION,
                "BEE_HIVE_CONNECTION" => $BEE_HIVE_CONNECTION,
                "BEE_GARDEN" => $BEE_GARDEN,
                "BEE_ERRORS" => $BEE_ERRORS,
                "BEE_JWT_ENCRYPTION" => $BEE_JWT_ENCRYPTION,
                "BEE_USER" => array("id"=>0)
            );
        }    
    }

?>