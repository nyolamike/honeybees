<?php
function hive_run_update_password($post_nectoroid,$bee){
    global $BEE_GLOBALS;
    global $countries_list;
    $res = array(null, array(),$bee);

    //nyd 
    //validation

    //hash the password
    $old_password = $post_nectoroid["_f_password"]["old_password"];
    $raw_new_password = $post_nectoroid["_f_password"]["new_password"];
    $password = password_hash($raw_new_password, PASSWORD_DEFAULT);
    $email = $bee["BEE_USER"]["email"];
    $app_name = $bee["BEE_USER"]["tenant_of"];
    $user_id = 0;

    

    
    $found = false;
    $found_hive = null;
    foreach ($bee["BEE_GARDEN"]["hives"] as $hive_index => $hive_obj) {
        $stop = false;
        foreach ($hive_obj["hive_users"] as $hive_user_index => $hive_user_obj) {
            //tools_dumpx("bee hives: ",__FILE__,__LINE__,array($app_name,$hive_obj["hive_name"]) );
            if($app_name == $hive_obj["hive_name"] && $hive_user_obj["email"] == $email ){
                //tools_dumpx("hive_name: ",__FILE__,__LINE__,$hive_user_obj);
                if (password_verify($old_password, $hive_user_obj["password"])) {
                    $found_hive = $hive_obj;
                    $user_id = $hive_user_obj["user_id"];
                    $found = true;
                    $stop = true;
                    break;
                }
            }
        }
        if($stop == true){
            break;
        }
    }  
    if($found == false){
        $res[BEE_EI] = array("Business account not found");
        return $res;
    }
    

    //check if hive exists
    //we only do this if we have an open hive reistrtion policy
    $hive_name = $app_name; //BEE_GARDEN . "_" . tools_sanitise_name($app_name);
    if($bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
        //tools_dumpx("hive_name: ",__FILE__,__LINE__,$hive_name);
        $hive_exists = tools_exists($bee["BEE_GARDEN"],"hives","hive_name",$hive_name);
        if(!$hive_exists){
            $res[BEE_EI] = array("Unknown application name " . $app_name);
            return $res;
        }
        //we would then get a connection to this hive
        $hrgc_res = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
        $res[BEE_EI] = array_merge($res[BEE_EI],$hrgc_res[BEE_EI]);
        $connection = $hrgc_res[BEE_RI];
        $bee["BEE_HIVE_CONNECTION"] = $connection;
    }

    $hive_exists = tools_exists($bee["BEE_GARDEN"],"hives","hive_name",$hive_name);
    if(!$hive_exists){
        $res[BEE_EI] = array("Unknown business application name " . $app_name);
        return $res;
    }

    //update user password
    $cnx = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
    if(count($cnx[BEE_EI]) == 0 ){
        $connection = $cnx[BEE_RI];
        //get the user asking for this reset
        $unec = array(
            "users" => array(
                "_w" => array(
                    array(
                        array(
                            array("id","=",$user_id),
                            "AND",
                            array("tenant_of","=",$hive_name)
                        ),
                        "AND",
                        array("status","=","active")
                    )
                )
            )
        );
        $brg_res = bee_run_get($unec,$bee["BEE_HIVE_STRUCTURE"]["combs"],$connection);
        $res[BEE_EI] = array_merge($res[BEE_EI],$brg_res[BEE_EI]);
        if(count($brg_res[BEE_EI])==0){
            $users = $brg_res[BEE_RI]["users"];
            if(count($users)==0){
                array_push($res[BEE_EI],"Account not found or may not be active");
            }else{
                $user = $users[0];
                //tools_dump("user",__FILE__,__LINE__,$user);
                $whole_honey = array();
                //update users account
                $update_user_nectar = array(
                    "user" => array(
                        "password" => $password,
                        "_w"=>array(
                            array(
                                array("id","=",$user["id"]),
                                "AND",
                                array("tenant_of","=",$hive_name)
                            )
                        )
                    ) 
                );
                $bhu_res = bee_hive_update(
                    $update_user_nectar,
                    $bee["BEE_HIVE_STRUCTURE"]["combs"],
                    $connection,
                    0,
                    $whole_honey
                );
                $res[BEE_EI] = array_merge($res[BEE_EI],$bhu_res[BEE_EI]);
                //tools_dump("bhu_res",__FILE__,__LINE__,$bhu_res);
                //update the hive user
                if(count($bhu_res[BEE_EI])==0){//no errors
                    $whole_honey = array();
                    $update_user_nectar = array(
                        "hive_user" => array(
                            "password" => $password,
                            "_w"=>array(
                                array(
                                    array("user_id","=",$user["id"]),
                                    "AND",
                                    array("hive_name","=",$hive_name)
                                )
                            )
                        ) 
                    );
                    $bhu_res = bee_hive_update(
                        $update_user_nectar,
                        $bee["BEE_GARDEN_STRUCTURE"],
                        $bee["BEE_GARDEN_CONNECTION"],
                        0,
                        $whole_honey
                    );
                    //tools_dumpx("bhu_res: ",__FILE__,__LINE__,$bhu_res);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$bhu_res[BEE_EI]);
                }
            }
        }
        $connection = null;//self cloase this connection
    }else{
        $res[BEE_EI] = array("We could not connect to " . $hive_name);
    }
    
    $res[BEE_RI] = "OK";

    
    return $res;
}
?>