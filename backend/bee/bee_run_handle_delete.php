<?php
function bee_run_handle_delete($res,$bee,$postdata=null){
    global $BEE_GLOBALS;
    global $countries_list;
    global $bee_show_sql;
    

    if($postdata == null){
        //do a normal file processing
        $temp_postdata = file_get_contents("php://input");
        //tools_dumpx("temp_postdata: ",__FILE__,__LINE__,$temp_postdata);
        $tsji_res = tools_suck_json_into($temp_postdata, array());
        $res[BEE_EI] = array_merge($tsji_res[BEE_EI],$res[BEE_EI]);
        if(count($res[BEE_EI])==0){//no errors
            $postdata = $tsji_res[BEE_RI];
        }
    }

    //check if we can return all the sqls
    if(array_key_exists("_sql",$postdata)){
        $bee_show_sql = true;
    }


    //authorise
    $bsv_res = bee_security_authorise(
        $bee["BEE_USER"],
        $postdata,
        $bee["BEE_HIVE_STRUCTURE"]["combs"],
        false, //create
        false, //read
        false, //update
        true //delete
    );
    $res[BEE_EI] = array_merge($res[BEE_EI],$bsv_res[BEE_EI]);
    if(count($res[BEE_EI])==0){//no errors
        //tools_dumpx("postdata",__FILE__,__LINE__,$postdata);
        $brd_res = bee_run_delete($postdata,$bee,$bee["BEE_USER"]["id"]);
        //tools_dumpx("brd_res delete ",__FILE__,__LINE__,$brd_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$brd_res[BEE_EI]);
        $res[BEE_RI] = $brd_res[BEE_RI];
    }
    return $res;
}
?>