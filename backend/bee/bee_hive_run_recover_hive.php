<?php
function hive_run_recover_hive($post_nectoroid,$bee){
    global $BEE_GLOBALS;
    global $countries_list;
    $res = array(null, array());
    
    //email
    $email = $post_nectoroid["_f_recover"]["email"];
    //nyd 
    //validation

    //tools_dumpx("hives: ",__FILE__,__LINE__,$bee["BEE_GARDEN"]["hives"]);
    $found = false;
    $found_records = array();
    foreach ($bee["BEE_GARDEN"]["hives"] as $hive_index => $hive_obj) {
        foreach ($hive_obj["hive_users"] as $hive_user_index => $hive_user_obj) {
            if($hive_user_obj["email"] == $email){
                array_push($found_records,array(
                    "hive_name" => $hive_user_obj["hive_name"],
                    "hive_id" => $hive_obj["id"],
                    "user_id" => $hive_user_obj["user_id"],
                    "code" => ""
                ));
                $found = true;
            }
        }
    }  
    if($found == false){
        $res[BEE_EI] = array("No account found for this email");
        return $res;
    }
    
    //tools_dumpx("found_hive: ",__FILE__,__LINE__,$found_hive);
    //connect to users db and setup a code for password recovery
    for ($i=0; $i < count($found_records) ; $i++) { 
        $found_record = $found_records[$i];
        $hive_name = $found_record["hive_name"];
        $cnx = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
        if(count($cnx[BEE_EI]) == 0 ){
            $connection = $cnx[BEE_RI];
            $whole_honey = array();
            $code = tool_code();
            $found_records[$i]["code"] = $code;
            //update users account
            $update_user_nectar = array(
                "user" => array(
                    "code" => $code,
                    "_w"=>array(
                        array(
                            array("id","=",$found_record["user_id"]),
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
            $connection = null;//self cloase this connection
        }else{
            $res[BEE_EI] = array("We could not connect to " . $hive_name);
        }
    }
    $res[BEE_RI] = $found_records;
    return $res;
}
?>