<?php

    //construct an sql query from these segements
    function sqllization_run($sql_segments){
        $sqls = array();
        
        foreach ($sql_segments as $root_node_name => $segmentation) {
            if($segmentation == "_at"){//extractionstaff
                continue;
            }
           
            //check if we have an _nx situation
            if(array_key_exists("temp_n",$segmentation) && count($segmentation["temp_n"]) > 0){
                //tools_dumpx("sqllization_run temp_n",__FILE__,__LINE__,$segmentation["temp_n"]);
                
                //nyd
                //walk backwards to include code to generate paths to clean
                //and delete this if below
                if(!isset($segmentation["paths_to_clean"])){
                    $segmentation["paths_to_clean"] = array();
                }
                $nres = array(
                    "sql" => "",
                    "paths_to_clean" => $segmentation["paths_to_clean"],
                    "children" => $segmentation["temp_children"],
                    "hash" => $segmentation["temp_hash"],
                    "_n" => array()
                );
                $nx = $segmentation["temp_n"];
                foreach ($nx as $nkey => $segmentation_n) {
                    $srobject = array();
                    $srobject[$root_node_name] = $segmentation_n;
                    $sr_res = sqllization_run($srobject);
                    //tools_dumpx("sqllization_run sr_res",__FILE__,__LINE__,$sr_res);
                    $nres["_n"][$nkey] = $sr_res[BEE_RI];
                }
                array_push($sqls,$nres);
                continue;
            }

            $comb_name = Inflect::singularize($root_node_name);
            $sections_sql = rtrim(trim($segmentation["temp_sections_sql"]), ',');
            $where_sql = trim($segmentation["temp_where_sql"]);
            $inner_joins_sql = $segmentation["temp_inner_join_sql"];
            $sql = "SELECT " . $sections_sql . " FROM " .  $comb_name . " " . $inner_joins_sql . " ";
            if(BEE_SUDO_DELETE){
                if(strlen($where_sql)>0){
                    $where_sql = " (".$comb_name.".is_deleted = 0) AND (" . $where_sql . ")";
                }else{
                    $where_sql = " (".$comb_name.".is_deleted = 0)";
                }
            }
            if(strlen($where_sql)>0){
                $sql = $sql . " WHERE " . $where_sql;
                //tools_dump("sql for " . $root_node_name,__FILE__,__LINE__,$sql);
            }
            //having
            if(array_key_exists("temp_having_sql",$segmentation)){
                $hvsql = $segmentation["temp_having_sql"];
                if(strlen($hvsql)>0){
                    $sql = $sql . " HAVING  " . $hvsql  . " ";
                }
            }
            //groub by
            if(array_key_exists("temp_groupby_sql",$segmentation)){
                $gb = $segmentation["temp_groupby_sql"];
                $gb = trim(trim($gb),",");
                if(strlen($gb)>0){
                    $sql = $sql . " GROUP BY " . $gb;
                }
            }
            //order by
            if(array_key_exists("temp_orderby_sql",$segmentation)){
                $ob = $segmentation["temp_orderby_sql"];
                if($ob != null && is_array($ob)){
                    $sqlx = trim(trim($ob["sql"]),",");
                    $sql = $sql . " ORDER BY " . $sqlx . " " . $ob["kind"];
                }
            }
            //pagination and limit
            if(array_key_exists("temp_limit_sql",$segmentation)){
                $lmt = $segmentation["temp_limit_sql"];
                if($lmt != null && is_array($lmt)){
                    $sql = $sql . " LIMIT  " . $lmt["limit"];
                    if($lmt["offset"] != null){
                        $sql = $sql . " OFFSET  " . $lmt["offset"];
                    }
                }
            }
            
            //tools_dump("sql for ",__FILE__,__LINE__,$sql);
            //nyd
            //walk backwards to include code to generate paths to clean
            //and delete this if below
            if(!isset($segmentation["paths_to_clean"])){
                $segmentation["paths_to_clean"] = array();
            }
            array_push($sqls,array(
                "sql" => $sql,
                "paths_to_clean" => $segmentation["paths_to_clean"],
                "children" => $segmentation["temp_children"],
                "hash" => $segmentation["temp_hash"],
                "_n" => array()
            ));
        }
        return  array($sqls,array());
    }

    function bee_sqllization_fx_run($fx_node,$parent_comb_name,$structure){
        global $BEE_HIVE_STRUCTURE;
        global $BEE_HIVE_CONNECTION;
        $res = array(null,array());
        $fxsql = "";
        foreach ($fx_node as $fx_node_key => $fx_node_value) {
            if(tools_startsWith($fx_node_key,"_diff")){
                //we want to get the difference between items here
                $diff_param_a = null;
                $diff_param_b = null;
                $diff_sql = "";
                foreach ($fx_node_value as $diff_node_key => $diff_node_value) {
                    if(tools_startsWith($diff_node_key,"_sum_") || tools_startsWith($diff_node_key,"_suma_") || tools_startsWith($diff_node_key,"_sumb_")){
                        $_sum_key = "";
                        if(tools_startsWith($diff_node_key,"_sum_")){
                            $_sum_key = "_sum_";
                        }
                        if(tools_startsWith($diff_node_key,"_suma_")){
                            $_sum_key = "_suma_";
                        }
                        if(tools_startsWith($diff_node_key,"_sumb_")){
                            $_sum_key = "_sumb_";
                        }
                        $temp_len = strlen($_sum_key);
                        $sum_node_name = substr($diff_node_key,$temp_len);
                        $sum_comb_name  = Inflect::singularize($sum_node_name);
                        $sum_section = "";
                        $sum_where = "";
                        $sum_inner_join = "";
                        if(is_array($diff_node_value)){

                            //nyd
                            //this inner join hack enables us to connect to paret combs
                            //well i dont think this is a solid implementation of
                            //this whole sqlisation of this $diff_node_value
                            //this was just quit snicky adition on 2019 04 03 by me, nyola mike
                            $config = array(
                                "path" => "",
                                "node_name" => $sum_comb_name,
                                "parents_w" => array(),
                                "hive_structure" => $BEE_HIVE_STRUCTURE["combs"],
                                "children" => array(),
                                "is_child_segmentation_run" => false
                            );
                            $srp_fx_res = segmentation_run_process($diff_node_value,$config,$BEE_HIVE_CONNECTION);
                            //tools_dump("srp_fx_res",__FILE__,__LINE__,$srp_fx_res);
                            $res[BEE_EI] = array_merge($res[BEE_EI],$srp_fx_res[BEE_EI]);
                            $sum_inner_join = $srp_fx_res[BEE_RI]["temp_inner_join_sql"];
                            if(count($res[BEE_EI]) > 0){
                                return $res;
                            }

                            //get the _a
                            if(array_key_exists("_a",$diff_node_value)){
                                $sum_section = $diff_node_value["_a"];
                            }else{
                                //unforgivable error
                                array_push($res[BEE_EI],"_a missing in fx at " . $diff_node_key);
                                return $res;
                            }
                            //get the where _w
                            if(array_key_exists("_w",$diff_node_value)){
                                $sum_where_array = $diff_node_value["_w"];
                                $srw_res = segmentation_run_w($sum_where_array,$sum_comb_name,null,$structure);
                                //tools_dump("srw_res",__FILE__,__LINE__,$srw_res);
                                $res[BEE_EI] = array_merge($res[BEE_EI],$srw_res[BEE_EI]);
                                $sum_where = " ".$sum_comb_name."." . $parent_comb_name . "_id = " . $parent_comb_name . ".id ";
                                $sum_where = " (".$srw_res[BEE_RI] . ") AND (" . $sum_where . ")" ;
                                if(BEE_SUDO_DELETE){
                                    $sum_where = " (".$sum_comb_name.".is_deleted = 0) AND (" . $sum_where . ")";
                                }
                            }else{
                                $sum_where = " ".$sum_comb_name."." . $parent_comb_name . "_id = " . $parent_comb_name . ".id ";
                                if(BEE_SUDO_DELETE){
                                    $sum_where = " (".$sum_comb_name.".is_deleted = 0) AND (" . $sum_where . ")";
                                }
                            }
                        }else{
                            $sum_section = $diff_node_value;
                            $sum_where = " ".$sum_comb_name."." . $parent_comb_name . "_id = " . $parent_comb_name . ".id ";
                            if(BEE_SUDO_DELETE){
                                $sum_where = " (".$sum_comb_name.".is_deleted = 0) AND (" . $sum_where . ")";
                            }
                        }
                        $sum_sql = "SELECT SUM(".$sum_comb_name.".".$sum_section.") FROM " . $sum_comb_name . "";
                        if(strlen($sum_inner_join)>0){
                            $sum_sql = $sum_sql  . "  " . $sum_inner_join;
                        }
                        if(strlen($sum_where)>0){
                            $sum_sql = $sum_sql  . " _fx_WHERE " . $sum_where;
                        }

                        if($diff_param_a==null){
                            $diff_param_a = $sum_sql;
                        }elseif($diff_param_b==null){
                            $diff_param_b = $sum_sql;
                        }
                        //tools_dumpx("sum_sql",__FILE__,__LINE__,$sum_sql);
                        continue;
                    }
                }  
                $diff_sql = "IFNULL((" . $diff_param_a . "),0)-IFNULL((".$diff_param_b."),0)";
                $fxsql = $fxsql . " " . $diff_sql;
            }
        }
        $res[BEE_RI] = $fxsql;
        return $res;
    }

    /*
        quantity: {
            _diff:{
                _sum_stockin_items: "quantity",
                _sum_stockout_items:"quantity"
            }
        }
    */ 
    function bee_sqllization_fxc_update($parent_comb_name,$fx_node,$structure,$user_id, $connection){
        $res = array(array(),array());
        foreach ($fx_node as $fx_node_key => $fx_node_value) {
            $res[BEE_RI][$fx_node_key] = null;
            $fxsql = "UPDATE " . $parent_comb_name . " SET `" . $fx_node_key . "` = ";
            $where_sql = "";
            foreach ($fx_node_value as $fx_item_key => $fx_item_value) {
                if(tools_startsWith($fx_item_key,"_diff")){
                    //we want to get the difference between items here
                    $diff_param_a = null;
                    $diff_param_b = null;
                    $diff_sql = "";
                    foreach ($fx_item_value as $diff_node_key => $diff_node_value) {
                        if(tools_startsWith($diff_node_key,"_sum_")){
                            $temp_len = strlen("_sum_");
                            $sum_node_name = substr($diff_node_key,$temp_len);
                            $sum_comb_name  = Inflect::singularize($sum_node_name);
                            $sum_section = "";
                            $sum_where = "";
                            if(is_array($diff_node_value)){
                                //get the _a
                                if(array_key_exists("_a",$diff_node_value)){
                                    $sum_section = $diff_node_value["_a"];
                                }else{
                                    //unforgivable error
                                    array_push($res[BEE_EI],"_a missing in fx at " . $diff_node_key);
                                    return $res;
                                }
                                //get the where _w
                                if(array_key_exists("_w",$diff_node_value)){
                                    $sum_where_array = $diff_node_value["_w"];
                                    $srw_res = segmentation_run_w($sum_where_array,$sum_comb_name,null,$structure);
                                    //tools_dump("srw_res",__FILE__,__LINE__,$srw_res);
                                    $res[BEE_EI] = array_merge($res[BEE_EI],$srw_res[BEE_EI]);
                                    $sum_where = " ".$sum_comb_name."." . $parent_comb_name . "_id = " . $parent_comb_name . ".id ";
                                    $sum_where = " (".$srw_res[BEE_RI] . ") AND (" . $sum_where . ")" ;
                                    if(BEE_SUDO_DELETE){
                                        $sum_where = " (".$sum_comb_name.".is_deleted = 0) AND (" . $sum_where . ")";
                                    }
                                }else{
                                    $sum_where = " ".$sum_comb_name."." . $parent_comb_name . "_id = " . $parent_comb_name . ".id ";
                                    if(BEE_SUDO_DELETE){
                                        $sum_where = " (".$sum_comb_name.".is_deleted = 0) AND (" . $sum_where . ")";
                                    }
                                }
                            }else{
                                $sum_section = $diff_node_value;
                                $sum_where = " ".$sum_comb_name."." . $parent_comb_name . "_id = " . $parent_comb_name . ".id ";
                                if(BEE_SUDO_DELETE){
                                    $sum_where = " (".$sum_comb_name.".is_deleted = 0) AND (" . $sum_where . ")";
                                }
                            }
                            $sum_sql = "SELECT SUM(".$sum_comb_name.".".$sum_section.") FROM " . $sum_comb_name . "";
                            if(strlen($sum_where)>0){
                                $sum_sql = $sum_sql  . " _fx_WHERE " . $sum_where;
                            }
    
                            if($diff_param_a==null){
                                $diff_param_a = $sum_sql;
                            }elseif($diff_param_b==null){
                                $diff_param_b = $sum_sql;
                            }
                            //tools_dumpx("sum_sql",__FILE__,__LINE__,$sum_sql);
                            continue;
                        }
                    }  
                    $diff_sql = "( IFNULL((" . $diff_param_a . "),0)-IFNULL((".$diff_param_b."),0) )";
                    $fxsql = $fxsql . " " . $diff_sql . ", ";
                }
            }
            $fxsql   .= " `time_last_modified` = ".time().",";
            $fxsql   .= " `last_modified_by` = ".$user_id." ";
            $fxsql = $fxsql . ((strlen($where_sql)>0)?" WHERE " . $where_sql: "");
            //tools_dump("fxsql: ",__FILE__,__LINE__,$fxsql);

            $hr_res = hive_run($fxsql,$connection);
            //tools_dumpx("hr_res",__FILE__,__LINE__,$hr_res);
            $res[BEE_EI] = array_merge($res[BEE_EI],$hr_res[BEE_EI]);
            $res[BEE_RI][$fx_node_key] = $hr_res[BEE_RI]["num"];
            //tools_dumpx("res data: ",__FILE__,__LINE__,$res[BEE_RI][$fx_node_key]);
        }
        return $res;
    }
?>