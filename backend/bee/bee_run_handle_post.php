<?php
function bee_run_handle_post($res,$bee,$postdata=null){
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
        
        //check if we can return all the sqls
        if(array_key_exists("_sql",$postdata)){
            $bee_show_sql = true;
        }

        
        //julz
        if(array_key_exists("_julz",$postdata)){
            $whole_honey = array("julz"=>array());
            $julz = $postdata["_julz"];
            if(array_key_exists("_gets",$julz)){
                $whole_honey["julz"]["gets"] = array();
                //an array of get requets
                $gets = $julz["_gets"];
                foreach ($gets as $get_index => $get) {
                    $brhg_res = bee_run_handle_get($res,$bee,$get);
                    //tools_dumpx("julz get",__FILE__,__LINE__,$brhg_res);
                    $sub_res = tools_pack($brhg_res[BEE_RI],$brhg_res[BEE_EI]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brhg_res[BEE_EI]);
                    array_push($whole_honey["julz"]["gets"],$sub_res);
                }
            }
            if(array_key_exists("_posts",$julz)){
                //an array of post requets
                $whole_honey["julz"]["posts"] = array();
                //an array of get requets
                $posts = $julz["_posts"];
                foreach ($posts as $post_index => $post) {
                    $brhp_res = bee_run_handle_post($res,$bee,$post);
                    //tools_dumpx("julz get",__FILE__,__LINE__,$brhp_res);
                    $sub_res = tools_pack($brhp_res[BEE_RI],$brhp_res[BEE_EI]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brhp_res[BEE_EI]);
                    array_push($whole_honey["julz"]["posts"],$sub_res);
                }
            }
            if(array_key_exists("_puts",$julz)){
                //an array of update requets
                $whole_honey["julz"]["puts"] = array();
                //an array of get requets
                $puts = $julz["_puts"];
                foreach ($puts as $put_index => $put) {
                    $brhp_res = bee_run_handle_put($res,$bee,$put,$whole_honey);
                    //tools_dumpx("julz get",__FILE__,__LINE__,$brhp_res);
                    $sub_res = tools_pack($brhp_res[BEE_RI],$brhp_res[BEE_EI]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brhp_res[BEE_EI]);
                    array_push($whole_honey["julz"]["puts"],$sub_res);
                }
            }
            if(array_key_exists("_updates",$julz)){
                //an array of update requets
                $whole_honey["julz"]["updates"] = array();
                //an array of get requets
                $puts = $julz["_updates"];
                foreach ($puts as $put_index => $put) {
                    $brhp_res = bee_run_handle_put($res,$bee,$put,$whole_honey);
                    //tools_dumpx("julz get",__FILE__,__LINE__,$brhp_res);
                    $sub_res = tools_pack($brhp_res[BEE_RI],$brhp_res[BEE_EI]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brhp_res[BEE_EI]);
                    array_push($whole_honey["julz"]["updates"],$sub_res);
                }
            }
            if(array_key_exists("_deletes",$julz)){
                //an array of delete requets
                $whole_honey["julz"]["deletes"] = array();
                //an array of get requets
                $deletes = $julz["_deletes"];
                foreach ($deletes as $delete_index => $delete) {
                    $brhd_res = bee_run_handle_put($res,$bee,$delete,$whole_honey);
                    //tools_dumpx("julz get",__FILE__,__LINE__,$brhd_res);
                    $sub_res = tools_pack($brhd_res[BEE_RI],$brhd_res[BEE_EI]);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$brhd_res[BEE_EI]);
                    array_push($whole_honey["julz"]["deletes"],$sub_res);
                }
            }
            $res[BEE_RI] = $whole_honey;
        }elseif(array_key_exists("_f_login",$postdata)){
            //the login 
            //it has to be the only thing in its request
            $whole_honey = array();
            $login_nector = array(
                "_f_login" => $postdata["_f_login"]
            );
            $BEE_GLOBALS["is_login_call"] = true;
            $hrl_res = bee_hive_run_login($login_nector, $bee);
            $BEE_GLOBALS["is_login_call"] = false;
            $whole_honey["_f_login"] = $hrl_res[BEE_RI];
            $res[BEE_RI] = $whole_honey["_f_login"];
            $res[BEE_EI] = array_merge($res[BEE_EI],$hrl_res[BEE_EI]); 
        }elseif(array_key_exists("_f_register",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
            $whole_honey = array();
            $register_nector = array(
                "_f_register" => $postdata["_f_register"]
            );
            $BEE_GLOBALS["is_register_call"] = true;
            $code = tool_code();
            $brrh_res = null;
            $sendEmail = true;
            //tools_dumpx("part b: ",__FILE__,__LINE__,$bee);
            if(array_key_exists("is_registration_offline",$bee["BEE_HIVE_STRUCTURE"]) && 
                $bee["BEE_HIVE_STRUCTURE"]["is_registration_offline"] == true){
                $sendEmail = false;
                $brrh_res = hive_run_register_hive($register_nector,$bee,array(
                    "code" => "",
                    "status" => "active",
                    "is_owner" => 1
                ));
            }else{
                $brrh_res = hive_run_register_hive($register_nector,$bee,array(
                    "code" => $code,
                    "status" => "pending",
                    "is_owner" => 1
                ));
            }
            
            //tools_dumpx("brrh_res",__FILE__,__LINE__,$brrh_res);
            $bee["BEE_HIVE_CONNECTION"] = $brrh_res[BEE_RI];
            $BEE_GLOBALS["is_register_call"] = false;
            $whole_honey["_f_register"] = "OK";
            $res[BEE_RI] = $whole_honey;
            $res[BEE_EI] = array_merge($res[BEE_EI],$brrh_res[BEE_EI]);
            //send activation email 
            if(count($res[BEE_EI]) == 0 && $sendEmail == true){
                $linkObj = $bee["BEE_HIVE_STRUCTURE"]["activation_link"];
                $temp =  "h=" . $postdata["_f_register"]["app_name"] . "&i=" . $brrh_res[2]["hive_id"] . "&c=" . $code;
                $link = str_replace("---",$temp,$linkObj);
                mailer_send_verification_email(
                    $postdata["_f_register"]["email"],
                    $postdata["_f_register"]["name"], 
                    $link, 
                    $bee
                );
            }
        }elseif(array_key_exists("_f_register",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == false){
            array_push($res[BEE_EI],"sorry _f_register operation is a private operation, !zzzz... ");
        }elseif(array_key_exists("_f_activate",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
            $whole_honey = array();
            $activate_nector = array(
                "_f_activate" => $postdata["_f_activate"]
            );
            $BEE_GLOBALS["is_activate_call"] = true;
            $brrh_res = hive_run_activate_hive($activate_nector,$bee);
            //tools_dumpx("brrh_res",__FILE__,__LINE__,$brrh_res);
            $BEE_GLOBALS["is_activate_call"] = false;
            $whole_honey["_f_activate"] = "OK";
            $res[BEE_RI] = $whole_honey;
            $res[BEE_EI] = array_merge($res[BEE_EI],$brrh_res[BEE_EI]);
        }elseif(array_key_exists("_f_activate",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == false){
            array_push($res[BEE_EI],"sorry _f_activate operation is not available, !zzzz... ");
        }elseif(array_key_exists("_f_recover",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
            $whole_honey = array();
            $activate_nector = array(
                "_f_recover" => $postdata["_f_recover"]
            );
            $BEE_GLOBALS["is_recover_call"] = true;
            $brrh_res = hive_run_recover_hive($activate_nector,$bee);
            //tools_dumpx("brrh_res",__FILE__,__LINE__,$brrh_res);
            $BEE_GLOBALS["is_recover_call"] = false;
            $whole_honey["_f_recover"] = "OK";
            $res[BEE_RI] = $whole_honey;
            $res[BEE_EI] = array_merge($res[BEE_EI],$brrh_res[BEE_EI]);
            //tools_dump("brrh_res",__FILE__,__LINE__,$brrh_res[BEE_RI]);
            //send recovery email 
            if(count($res[BEE_EI]) == 0){
                $linkObj = $bee["BEE_HIVE_STRUCTURE"]["reset_link"];

                $links = array();
                foreach ($brrh_res[BEE_RI] as $index => $found_records) {
                    $temp =  "h=" . $found_records["hive_name"] . "&i=" . $found_records["user_id"] . "&c=" . $found_records["code"];
                    $link = str_replace("---",$temp,$linkObj);
                    array_push($links,array(
                        "title" => $found_records["hive_name"],
                        "link" => $link
                    ));
                }
                //tools_dumpx("links",__FILE__,__LINE__,$links);
                
                mailer_send_recovery_email(
                    $postdata["_f_recover"]["email"],
                    "USER", 
                    $links, 
                    $bee
                );
            }
        }elseif(array_key_exists("_f_recover",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == false){
            array_push($res[BEE_EI],"sorry _f_recover operation is not available, !zzzz... ");
        }elseif(array_key_exists("_f_reset",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
            $whole_honey = array();
            $reset_nector = array(
                "_f_reset" => $postdata["_f_reset"]
            );
            $BEE_GLOBALS["is_reset_call"] = true;
            $brrh_res = hive_run_reset_hive($reset_nector,$bee);
            //tools_dumpx("brrh_res",__FILE__,__LINE__,$brrh_res);
            $BEE_GLOBALS["is_reset_call"] = false;
            $whole_honey["_f_reset"] = "OK";
            $res[BEE_RI] = $whole_honey;
            $res[BEE_EI] = array_merge($res[BEE_EI],$brrh_res[BEE_EI]);
        }elseif(array_key_exists("_f_reset",$postdata) && $bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == false){
            array_push($res[BEE_EI],"sorry _f_reset operation is not available, !zzzz... ");
        }else{
            //authorise
            $bsv_res = bee_security_authorise(
                $bee["BEE_USER"],
                $postdata,
                $bee["BEE_HIVE_STRUCTURE"]["combs"],
                true, //create
                false, //read
                false, //update
                false //delete
            );
            $res[BEE_EI] = array_merge($res[BEE_EI],$bsv_res[BEE_EI]);
            if(count($res[BEE_EI])==0){//no errors
                //tools_dumpx("postdata",__FILE__,__LINE__,$postdata);
                $brp_res = bee_run_post($postdata,$bee,$bee["BEE_USER"]["id"]);
                //tools_dumpx("brp_res post ",__FILE__,__LINE__,$brp_res);
                $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
                $res[BEE_RI] = $brp_res[BEE_RI];
            }
        }
    }

    return $res;
}
?>