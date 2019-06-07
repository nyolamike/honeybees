<?php
function hive_run_reset_hive($post_nectoroid,$bee){
    global $BEE_GLOBALS;
    global $countries_list;
    $res = array(null, array());
    
    //email
    $hive_name = $post_nectoroid["_f_reset"]["hive_name"];
    $user_id = intval($post_nectoroid["_f_reset"]["user_id"]);
    $code = $post_nectoroid["_f_reset"]["code"];
    $raw_password = $post_nectoroid["_f_reset"]["password"];
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    //nyd 
    //validation

    // tools_dumpx("data",__FILE__,__LINE__,array(
    //     $hive_name,
    //     $user_id,
    //     $code,
    //     $raw_password,
    //     $password
    // ));
    $found = false;
    $found_hive = null;
    foreach ($bee["BEE_GARDEN"]["hives"] as $hive_index => $hive_obj) {
        $stop = false;
        foreach ($hive_obj["hive_users"] as $hive_user_index => $hive_user_obj) {
            if( intval($hive_user_obj["user_id"]) == $user_id  && 
                intval($hive_user_obj["hive_name"]) == $hive_name){
                $found = true;
                $stop = true;
                break;
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
                        array("code","=",$code)
                    )
                )
            )
        );
        $brg_res = bee_run_get($unec,$bee["BEE_HIVE_STRUCTURE"]["combs"],$connection);
        $res[BEE_EI] = array_merge($res[BEE_EI],$brg_res[BEE_EI]);
        if(count($brg_res[BEE_EI])==0){
            $users = $brg_res[BEE_RI]["users"];
            if(count($users)==0){
                array_push($res[BEE_EI],"Account not found or did not request for a password reset");
            }else{
                $user = $users[0];
                //tools_dump("user",__FILE__,__LINE__,$user);
                $whole_honey = array();
                //update users account
                $update_user_nectar = array(
                    "user" => array(
                        "code" => "",
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