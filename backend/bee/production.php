<?php

    //to convert sqls into raw honey
    function production_run($sqls,$connection){
        $res = array(array(),array());
        foreach ($sqls as $sql_index => $sql_group) {
            //check if we are executing at _nx
            if(array_key_exists("_n",$sql_group) && count($sql_group["_n"]) > 0){
                //tools_dumpx("production_run _n",__FILE__,__LINE__,$sql_group["_n"]);
                $nres = array(
                    "raw_honey" => array(),
                    "paths_to_clean" =>  $sql_group["paths_to_clean"],
                    "children" => $sql_group["children"],
                    "hash" => $sql_group["hash"],
                    "_n" => array()
                );
                $res[BEE_RI] = $nres;
                foreach ($sql_group["_n"] as $nsqlkey => $nsqls) {
                    $pr_res = production_run($nsqls,$connection);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
                    $res[BEE_RI]["_n"][$nsqlkey] = $pr_res[BEE_RI];//$hr_res[BEE_RI]["data"]
                }
                continue;
            }
            
            //the sql_group contains
            //sql, paths_to_clean, children
            $sql = $sql_group["sql"];
            //tools_dump("sql ",__FILE__,__LINE__,$sql);
            $hr_res = hive_run($sql,$connection);
            $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
            array_push($res[BEE_RI],array(
                "raw_honey" => $hr_res[BEE_RI]["data"],
                "paths_to_clean" =>  $sql_group["paths_to_clean"],
                "children" => $sql_group["children"],
                "hash" => $sql_group["hash"],
                "_n" => array()
            ));
        }
        return $res;
    }

    
    //to convert sqls into raw honey
    function production_post($sqls,$connection,$prev_res=array()){
        //tools_dump("production_post",__FILE__,__LINE__,$sqls);
        $res = array(array(),array());
        foreach ($sqls as $sql_index => $sql) {
            //tools_dump("prev_res",__FILE__,__LINE__,$prev_res);
            if(is_array($sql)){
                $res[BEE_RI][$sql_index] = array(); 
                foreach ($sql as $index => $cmd) {
                    //tools_dump("cmd",__FILE__,__LINE__,$cmd);
                    //nyd
                    //look for fk replacements if any
                    //the use of !== is deliberate
                    if (strpos($cmd, "'_fk_") !== false && strpos($cmd, "_kf_'") !== false ) {
                        $foreing_values  = tools_get_in_between_strings("_fk_", "_kf_", $cmd);
                        //tools_dumpx("goot",__FILE__,__LINE__,$foreing_values);
                        for ($i=0; $i < count($foreing_values); $i++) { 
                            $foreing_value = $foreing_values[$i];
                            if(strpos($foreing_value, "@") !== false){
                                //contains an index
                                $ky_indx = explode("@",$foreing_value);
                                $ky = $ky_indx[0];
                                $indx = intval($ky_indx[1]);
                                $val = $prev_res[$ky][$indx];
                                $search = "'_fk_".$foreing_value."_kf_'";
                                $cmd = str_replace($search,$val,$cmd);
                            }else{
                                $search = "'_fk_".$foreing_value."_kf_'";
                                $val = $prev_res[$foreing_value];
                                $cmd = str_replace($search,$val,$cmd);
                            }
                        }
                        //tools_dumpx("cmdx",__FILE__,__LINE__,$cmd);
                    }
                    // if(strpos($cmd, "client") !== false){
                    //     tools_dump("cmd",__FILE__,__LINE__,$cmd);
                    // }
                    $hr_res = hive_run($cmd,$connection);
                    //tools_dumpx("hr_res",__FILE__,__LINE__,$hr_res);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
                    array_push($res[BEE_RI][$sql_index],$hr_res[BEE_RI]);
                }
            }else{
                //nyd
                //look for fk replacements if any
                //the use of !== is deliberate
                if (strpos($sql, "'_fk_") !== false && strpos($sql, "_kf_'") !== false ) {
                    $foreing_values  = tools_get_in_between_strings("_fk_", "_kf_", $sql);
                    //tools_dumpx("goot",__FILE__,__LINE__,$foreing_values);
                    for ($i=0; $i < count($foreing_values); $i++) { 
                        $foreing_value = $foreing_values[$i];
                        if(strpos($foreing_value, "@") !== false){
                            //contains an index
                            $ky_indx = explode("@",$foreing_value);
                            $ky = $ky_indx[0];
                            $indx = intval($ky_indx[1]);
                            $val = $prev_res[$ky][$indx];
                            $search = "'_fk_".$foreing_value."_kf_'";
                            $sql = str_replace($search,$val,$sql);
                        }else{
                            //tools_dumpx("now res",__FILE__,__LINE__,$prev_res);
                            $search = "'_fk_".$foreing_value."_kf_'";
                            $val = $prev_res[$foreing_value];
                            $sql = str_replace($search,$val,$sql);
                        }
                    }
                }
                //tools_dump("sql",__FILE__,__LINE__,$sql);
                // if(strpos($sql, "client") !== false){
                //     tools_dump("sql",__FILE__,__LINE__,$sql);
                // }
                $hr_res = hive_run($sql,$connection);
                //tools_dumpx("hr_res",__FILE__,__LINE__,$hr_res);
                $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
                $res[BEE_RI][$sql_index] = $hr_res[BEE_RI];
            }
        }
        return $res;
    }

    //to convert sqls into raw honey
    function production_delete($sqls,$connection,$is_restricted=false){
        $res = array(array(),array());
        foreach ($sqls as $sql_index => $sql_group) {
            //tools_dumpx("sql_index",__FILE__,__LINE__,array($sql_index,$sql_group));
            //delete children first
            foreach ($sql_group["children_sqls"] as $sql) {
                $hr_res = hive_run($sql,$connection);
                $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
            }
            //delete this record
            $hr_res = hive_run($sql_group["sql"],$connection);
            //tools_dumpx("hr_res",__FILE__,__LINE__,$hr_res);
            $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
            $res[BEE_RI][$sql_index] = $hr_res[BEE_RI];
        }
        return $res;
    }


    //to convert sqls into raw honey
    function production_update($sqls,$connection,$prev_res=array(),$current_raw_honey=null){
        $res = array(array(),array());
        foreach ($sqls as $sql_index => $sql) {
            //tools_dump("prev_res",__FILE__,__LINE__,$prev_res);
            if(is_array($sql)){
                $res[BEE_RI][$sql_index] = array(); 
                foreach ($sql as $index => $cmd) {
                    //nyd
                    //look for fk replacements if any
                    //the use of !== is deliberate
                    if (strpos($cmd, "'_fk_") !== false && strpos($cmd, "_kf_'") !== false ) {
                        $foreing_values  = tools_get_in_between_strings("_fk_", "_kf_", $cmd);
                        tools_dumpx("goot array",__FILE__,__LINE__,[$foreing_values,$current_raw_honey]);
                        for ($i=0; $i < count($foreing_values); $i++) { 
                            $foreing_value = $foreing_values[$i];
                            if(strpos($foreing_value, "@") !== false){
                                //contains an index
                                $ky_indx = explode("@",$foreing_value);
                                $ky = $ky_indx[0];
                                $indx = intval($ky_indx[1]);
                                $val = $prev_res[$ky][$indx];
                                $search = "'_fk_".$foreing_value."_kf_'";
                                $cmd = str_replace($search,$val,$cmd);
                            }else{
                                $search = "'_fk_".$foreing_value."_kf_'";
                                $val = $prev_res[$foreing_value];
                                $cmd = str_replace($search,$val,$cmd);
                            }
                        }
                        //tools_dumpx("cmdx",__FILE__,__LINE__,$cmd);
                    }
                    $hr_res = hive_run($cmd,$connection);
                    //tools_dumpx("hr_res",__FILE__,__LINE__,$hr_res);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
                    array_push($res[BEE_RI][$sql_index],$hr_res[BEE_RI]);
                }
            }else{
                //nyd
                //look for fk replacements if any
                //the use of !== is deliberate
                if (strpos($sql, "'_fk_") !== false && strpos($sql, "_kf_'") !== false ) {
                    $foreing_values  = tools_get_in_between_strings("_fk_", "_kf_", $sql);
                    //tools_dumpx("goot array",__FILE__,__LINE__,[$foreing_values,$current_raw_honey]);
                    for ($i=0; $i < count($foreing_values); $i++) { 
                        $foreing_value = $foreing_values[$i];
                        if(tools_startsWith($foreing_value,"_julz")){
                            $original_foreing_value = $foreing_value;
                            //we gonna check in the curren honey
                            $julz = $current_raw_honey["julz"];
                            $foreing_value = str_replace("_julz.","",$foreing_value);
                            //tools_dumpx("goot array",__FILE__,__LINE__,[$foreing_value,$julz]);
                            $foreing_value_parts = explode("@",$foreing_value);
                            $cmd = $foreing_value_parts[0];
                            if($cmd == "_posts"){
                                $foreing_value = str_replace("_posts@","",$foreing_value);
                                $_posts = $julz["posts"];
                                //tools_dumpx("goot array",__FILE__,__LINE__,[$foreing_value,$_posts]);
                                $foreing_value_parts = explode(".",$foreing_value);
                                $post_index = intval($foreing_value_parts[0]);
                                $post_obj = $_posts[$post_index];
                                if(count($foreing_value_parts) > 2){
                                    array_push($res[BEE_EI],"No inplementation for _julz multiple value levels: consult the enginee developers");
                                }else{
                                    $value_prop = $foreing_value_parts[1];
                                    $val = $post_obj[$value_prop];
                                    $search = "'_fk_".$original_foreing_value."_kf_'";
                                    $sql = str_replace($search,$val,$sql);
                                }
                            }else{
                                array_push($res[BEE_EI],"No inplementation for _julz." . $cmd. ": consult the enginee developers");
                            }
                            continue;
                        }

                        if(strpos($foreing_value, "@") !== false){
                            //contains an index
                            $ky_indx = explode("@",$foreing_value);
                            $ky = $ky_indx[0];
                            $indx = intval($ky_indx[1]);
                            $val = $prev_res[$ky][$indx];
                            $search = "'_fk_".$foreing_value."_kf_'";
                            $sql = str_replace($search,$val,$sql);
                        }else{
                            //tools_dumpx("now res",__FILE__,__LINE__,$prev_res);
                            $search = "'_fk_".$foreing_value."_kf_'";
                            $val = $prev_res[$foreing_value];
                            $sql = str_replace($search,$val,$sql);
                        }
                    }
                }
                //tools_dumpx("sql",__FILE__,__LINE__,$sql);
                $hr_res = hive_run($sql,$connection);
                //tools_dumpx("hr_res",__FILE__,__LINE__,$hr_res);
                $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
                $res[BEE_RI][$sql_index] = $hr_res[BEE_RI];
            }
        }
        return $res;
    }

?>