<?php
//this will create the apllications db
function hive_run_register_hive($post_nectoroid,$bee,$defaults=null){
    global $BEE_GLOBALS;
    global $countries_list;
    $res = array(null, array(),array(
        "hive_id" => 0,
        "hive_name" => ""
    ));
    if($defaults == null){
        $defaults = array(
            "code" => "",
            "status" => "active",
            "is_owner" => 1
        );
    }
    //tools_dumpx("defaultsxx: ",__FILE__,__LINE__,$defaults);
    //db name
    $hive_name = BEE_GARDEN . "_" . tools_sanitise_name($post_nectoroid["_f_register"]["app_name"]);
    //tools_dumpx("hive_name: ",__FILE__,__LINE__,$hive_name);
    //hash the password
    //adopted from
    //https://stackoverflow.com/questions/43864379/best-way-encrypt-password-php-in-2017
    //https://secure.php.net/manual/en/function.password-hash.php
    //https://secure.php.net/manual/en/function.password-verify.php
    /*
        $hash = '$2y$07$BCryptRequires22Chrcte/VlQH0piJtjXl.0t1XkA8pw9dMXTpOq';

        if (password_verify('rasmuslerdorf', $hash)) {
            echo 'Password is valid!';
        } else {
            echo 'Invalid password.';
        }
    */
    $password = password_hash($post_nectoroid["_f_register"]["password"], PASSWORD_DEFAULT);
    //nyd 
    //validation
    $nectoroid = array(
        "hive" => array(
            "name" => $post_nectoroid["_f_register"]["name"],
            "hive_name" => $hive_name,
            "email" => $post_nectoroid["_f_register"]["email"],
            "phone_number" => $post_nectoroid["_f_register"]["phone_number"],
            "country" => $post_nectoroid["_f_register"]["country"],
            "code" => $defaults["code"],
            "password" => $password,
            "status" => $defaults["status"]
        ) 
    );
    //tools_dumpx("nectoroid: ",__FILE__,__LINE__,$nectoroid);

    

    //nyd
    //check if hive already exist
    //if true return the connection to this hive
    $cnx = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
    if(count($cnx[BEE_EI]) == 0 ){
        //we have a valid connection, no need to recreate hive for this appliaction
        //just return the connection
        $connection = $cnx[BEE_RI];
        $res[BEE_RI] = $connection;
        return $res;
    }

    $hrgc_res = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,"",true);
    //tools_dump("hrgc_res: ",__FILE__,__LINE__,$hrgc_res);
    $res[BEE_EI] = array_merge($res[BEE_EI],$hrgc_res[BEE_EI]);
    $connection = $hrgc_res[BEE_RI];
    if(count($hrgc_res[BEE_EI]) == 0){
        $hrch_res = hive_run_create_hive($hive_name,$connection);
        //var_dump($hrch_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$hrch_res[BEE_EI]);
        //if there are no errors continue to create combs here
        if(count($hrch_res[BEE_EI]) == 0){
            //get the connection to the created hive
            //close connection to test db
            $connection = null;
            unset($connection);
            $hrgc_res = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
            $res[BEE_EI] = array_merge($res[BEE_EI],$hrgc_res[BEE_EI]);
            $connection = $hrgc_res[BEE_RI];
            if($hrch_res[BEE_RI]["hive_res"] == 1){ //if the hive has just been created
                $hive_combs = $bee["BEE_HIVE_STRUCTURE"]["combs"];
                foreach ($hive_combs as $comb_name => $sectures) {
                    if(tools_startsWith($comb_name,"_")){
                        continue;
                    }
                    $hrss_res = hive_run_secture_sqlization($sectures);
                    $sections_sqls = $hrss_res[BEE_RI];
                    $hrct_res =  hive_run_ct($connection,$comb_name, $sections_sqls);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$hrct_res[BEE_EI]);
                }
            }
        }
    }
    if(count($res[BEE_EI])>0){
        $res[BEE_RI] = false;
    }else{

        //post data into hive
        $brp_res = bee_hive_post($nectoroid,$bee["BEE_GARDEN_STRUCTURE"],$bee["BEE_GARDEN_CONNECTION"],0);
        //tools_dumpx("bee_hive_post ",__FILE__,__LINE__,$brp_res);
        $bee["BEE_HIVE_CONNECTION"] = $connection;
        $res[2]["hive_name"] = $hive_name;
        $hive_id = $brp_res[BEE_RI]["hive"];
        $res[2]["hive_id"] = $hive_id;
        
        //nyd
        //add all modules to this hive in the master db

        //add a super role
        $role_nector = array(
            "role" => array(
                "name" => "super role",
                "description" => "The user with all permissions"
            ),
            "role_modules" => array(),
            "role_permisiions" => array(),
            "user" => array(
                "name" => $post_nectoroid["_f_register"]["name"],
                "email" => $post_nectoroid["_f_register"]["email"],
                "code" => $defaults["code"],
                "tenant_of" => $hive_name,
                "is_owner" => $defaults["is_owner"],
                "password" => $password,
                "status" => $defaults["status"]
            ),
            "user_role" => array(
                "_fk_user_id" => "user",
                "_fk_role_id" => "role",
                "status" => "active"
            )
        );
        //register all modules of this system to this hive
        //since its the none public distribution kind
        $system_modules = $bee["BEE_GARDEN_STRUCTURE"]["_modules"];
        foreach ($system_modules as $system_module ) {
            $role_module = array(
                "_fk_role_id" => "role",
                "module_code" => $system_module["code"],
                "status" => "active" 
            );
            array_push($role_nector["role_modules"],$role_module);
        }
        //permissions
        //the super role has access to all system permissions
        $combs = $bee["BEE_HIVE_STRUCTURE"]["combs"];
        foreach ($combs as $combs_name => $combs_def) {
            if(tools_startsWith($combs_name,"_")){
                continue;
            }
            $role_permisiion = array(
                "_fk_role_id" => "role",
                "permission" => $combs_name,
                "can_create" => 1,
                "can_read" => 1,
                "can_update" => 1,
                "can_delete" => 1
            );
            array_push($role_nector["role_permisiions"],$role_permisiion);
        }
        $brp_res2 = bee_run_post($role_nector,$bee,0); //bee_hive_post($role_nector,$combs,$bee["BEE_HIVE_CONNECTION"],0);
        $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res2[BEE_EI]);
        //register hive user
        $user_id = $brp_res2[BEE_RI]["user"];
        $hive_user_nector = array(
            "hive_user" => array(
                "hive_id" => $hive_id,
                "hive_name" => $hive_name,
                "user_id" =>  $user_id,
                "email" => $post_nectoroid["_f_register"]["email"],
                "password" => $password
            )
        );
        $brp_res3 = bee_hive_post($hive_user_nector,$bee["BEE_GARDEN_STRUCTURE"],$bee["BEE_GARDEN_CONNECTION"],0);
        $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res3[BEE_EI]);
        //seeding
        if(array_key_exists("seeds",$bee["BEE_HIVE_STRUCTURE"])){
            $seeds = $bee["BEE_HIVE_STRUCTURE"]["seeds"];
            $brp_res3 = bee_run_post($seeds,$bee,0); //bee_hive_post($seeds,$combs,$bee["BEE_HIVE_CONNECTION"],0);
            $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res3[BEE_EI]);
        }
        
        
        $res[BEE_RI] = $connection;
    }
    //tools_dumpx("hive creation results: ",__FILE__,__LINE__,$res);
    
    return $res;
}
?>