<?php
function bee_run_handle_put($res,$bee,$postdata=null,$current_honey=null){
    global $BEE_GLOBALS;
    global $countries_list;
    global $bee_show_sql;
    
    //check if there is a file upload
    if(count($_FILES) > 0){
        $bhru_res = bee_hive_run_uploads($bee);
        $res[BEE_EI] = array_merge($res[BEE_EI],$bhru_res[BEE_EI]);
        $res[BEE_RI] = $bhru_res[BEE_RI];
    }else{
        if($postdata == null){
            //do a normal file processing
            $temp_postdata = file_get_contents("php://input");
            //tools_dumpx("temp_postdata",__FILE__,__LINE__,$temp_postdata);
            $tsji_res = tools_suck_json_into($temp_postdata, array());
            $res[BEE_EI] = array_merge($tsji_res[BEE_EI],$res[BEE_EI]);
            if(count($res[BEE_EI])==0){//no errors
                $postdata = $tsji_res[BEE_RI];
            }
        }

        if(array_key_exists("_sql",$postdata)){
            $bee_show_sql = true;
        }

        if(array_key_exists("_f_password",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
            if(isset($bee["BEE_USER"])){
                //tools_dumpx("here: ",__FILE__,__LINE__,true);
                $whole_honey = array();
                $reset_nector = array(
                    "_f_password" => $postdata["_f_password"]
                );
                $BEE_GLOBALS["is_password_call"] = true;
                //tools_dumpx("swett place a",__FILE__,__LINE__,true);
                $brrh_res = hive_run_update_password($reset_nector,$bee);
                //tools_dumpx("swett place a",__FILE__,__LINE__,true);
                $BEE_GLOBALS["is_password_call"] = false;
                $whole_honey["_f_password"] = "OK";
                $res[BEE_RI] = $whole_honey;
                $res[BEE_EI] = array_merge($res[BEE_EI],$brrh_res[BEE_EI]);
                //perform a login to get a new token
                //if there were no errors
                if(count($brrh_res[BEE_EI])==0){
                    //get in the current state of the garden
                    $hrgg_res = hive_run_get_garden($bee["BEE_GARDEN_STRUCTURE"],$bee["BEE_GARDEN_CONNECTION"]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$hrgg_res[BEE_EI]); 
                    $BEE_GARDEN_STRUCTURE = $hrgg_res[2];
                    $BEE_GARDEN = $hrgg_res[BEE_RI];
                    $bee["BEE_GARDEN"] = $BEE_GARDEN;
                    //the login 
                    //it has to be the only thing in its request
                    $login_nector = array(
                        "_f_login" => array(
                            "email" => $bee["BEE_USER"]["email"],
                            "password" =>  $postdata["_f_password"]["new_password"]
                        )
                    );
                    $BEE_GLOBALS["is_login_call"] = true;
                    //tools_dumpx("swett place a",__FILE__,__LINE__,$login_nector);
                    $hrl_res = bee_hive_run_login($login_nector, $bee);
                    $BEE_GLOBALS["is_login_call"] = false; $hrl_res[BEE_RI];
                    $res[BEE_RI] = array(
                        "_f_password" => $hrl_res[BEE_RI]["_f_login"]
                    );
                    $res[BEE_EI] = array_merge($res[BEE_EI],$hrl_res[BEE_EI]); 
                }
            }else{
                array_push($res[BEE_EI],"sorry you must be logged in to access _f_password, !zzzz... ");
            }
        }elseif(array_key_exists("_f_password",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == false){
            array_push($res[BEE_EI],"sorry _f_password operation is not available, !zzzz... ");
        }else{
            //authorise
            $bsv_res = bee_security_authorise(
                $bee["BEE_USER"],
                $postdata,
                $bee["BEE_HIVE_STRUCTURE"]["combs"],
                false, //create
                false, //read
                true, //update
                false //delete
            );
            $res[BEE_EI] = array_merge($res[BEE_EI],$bsv_res[BEE_EI]);
            if(count($res[BEE_EI])==0){//no errors
                //tools_dumpx("postdata",__FILE__,__LINE__,[$res,$postdata,$current_honey]);
                $brp_res = bee_run_update($postdata,$bee,$bee["BEE_USER"]["id"],$current_honey);
                //tools_dumpx("brp_res put ",__FILE__,__LINE__,$brp_res);
                $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
                $res[BEE_RI] = $brp_res[BEE_RI];
            }
        }  
    }
    return $res;
}
?>