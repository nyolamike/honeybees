<?php
//this will activate the account
function hive_run_activate_hive($post_nectoroid,$bee,$defaults=null){
    $res = array(null, array());
    //db name
    $hive_name = BEE_GARDEN . "_" . tools_sanitise_name($post_nectoroid["_f_activate"]["app_name"]);
    //code
    $code = $post_nectoroid["_f_activate"]["code"];
    //hive_id
    $hive_id =intval($post_nectoroid["_f_activate"]["hive_id"]);
    //nyd 
    //validation

    //tools_dumpx("hives: ",__FILE__,__LINE__,array($hive_name, $code, $hive_id));
    $found = false;
    $found_hive = null;
    foreach ($bee["BEE_GARDEN"]["hives"] as $hive_index => $hive_obj) {
        if(
            $hive_obj["hive_name"] == $hive_name &&
            $hive_obj["code"] == $code &&
            $hive_obj["id"] == $hive_id &&
            $hive_obj["status"]  == "pending"
        ){
            $found_hive = $hive_obj;
            $found = true;
            break;
        }
    }  
    if($found == false){
        $res[BEE_EI] = array("Business: " . $post_nectoroid["_f_activate"]["app_name"] . " is not set for activation");
        return $res;
    }
    //tools_dumpx("found_hive: ",__FILE__,__LINE__,$found_hive);

    $update_hive_nectar = array(
        "hive" => array(
            "code" => "",
            "status" => "active",
            "_w"=>array(
                array("id","=",$hive_id)
            )
        ) 
    );
    //tools_dumpx("update_hive_nectar: ",__FILE__,__LINE__,$update_hive_nectar);
    $whole_honey = array();
    $bhu_res = bee_hive_update(
        $update_hive_nectar,
        $bee["BEE_GARDEN_STRUCTURE"],
        $bee["BEE_GARDEN_CONNECTION"],
        0,
        $whole_honey
    );
    $res[BEE_EI] = array_merge($res[BEE_EI],$bhu_res[BEE_EI]);
    if(count($bhu_res[BEE_EI])==0){
        //get connection to this hive
        $cnx = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
        if(count($cnx[BEE_EI]) == 0 ){
            $connection = $cnx[BEE_RI];
            $whole_honey = array();
            //update users account
            $update_user_nectar = array(
                "user" => array(
                    "code" => "",
                    "status" => "active",
                    "_w"=>array(
                        array(
                            array(
                                array(
                                    array("tenant_of","=",$hive_name),
                                    "AND",
                                    array("code","=",$code)
                                ),
                                "AND",
                                array("is_owner","=",1)
                            ),
                            "AND",
                            array("status","=","pending")
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
            $res[BEE_EI] = array("We could not connect to " . $post_nectoroid["_f_activate"]["app_name"]);
        }
    } 
    return $res;
}
?>