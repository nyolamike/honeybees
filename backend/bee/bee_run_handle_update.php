<?php
function bee_run_handle_update($res,$bee,$postdata=null){
    global $BEE_GLOBALS;
    global $countries_list;
    
    $method = "put";
    $temp_postdata = file_get_contents("php://input");
    tools_dumpx("temp_postdata: ",__FILE__,__LINE__,$temp_postdata);

    return $res;
}
?>