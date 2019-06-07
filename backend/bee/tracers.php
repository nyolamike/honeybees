<?php
    function segmentation_run_rm($nectoroid,$structure,$connection){
        $res = array(array(),array(),$structure);
        //go through the entire nectorid processing
        //node by node on the root
        foreach ($nectoroid as $root_node_name => $root_node) {
            if(tools_startsWith($root_node_name,"_")){
                continue;
            }
            $config = array(
                "path" => "",
                "node_name" => $root_node_name,
                "parents_w" => array(),
                "hive_structure" => $structure,
                "children" => array(),
                "is_child_segmentation_run" => false
            );
            $srp_res = segmentation_run_process($root_node,$config,$connection);
            $res[2] = $srp_res[2];//structure
            $res[BEE_RI][$root_node_name] = $srp_res[BEE_RI];
            $res[BEE_EI] = array_merge($res[BEE_EI],$srp_res[BEE_EI]);
            //var_dump($srp_res);
        }
        return $res;
    }

    function segmentation_run_process_rm($nectoroid,$config,$connection){
        $res = array(array(
            "temp_sections_sql" => "",
            "temp_children" => array(),
            "temp_inner_join_sql" => "",
            "temp_where_sql" => ""
        ),array());

        //tools_dumpx("params",__FILE__,__LINE__,array($nectoroid,$config));

        //variables
        $node = $nectoroid;
        $path = $config["path"];
        $node_name = $config["node_name"];
        $parents_w = (isset($config["parents_w"]))?$config["parents_w"]:array();
        $hive_structure = $config["hive_structure"];
        $res[BEE_RI]["temp_children"] = (isset($config["children"]))?$config["children"]:array();
        $is_child_segmentation_run = isset($config["is_child_segmentation_run"]);

 
        //the current path becomes
        $path = (strlen($path) == 0 ) ? ($node_name) : ($path . BEE_SEP .$node_name);
        //get the name of the comb
        $comb_name = Inflect::singularize($node_name);
        //_a must always be there if not it is inserted as the first attribute
        if(!array_key_exists(BEE_ANN,$node)){
            $node = array_merge(array("_a"=>array()),  $node); 
        }
        //if the parents_where is not empty
        //and this node has no _w then its injected in
        if(count($parents_w) > 0 && !array_key_exists(BEE_WNN,$node)){
            $node[BEE_WNN] = array();
        }

        //tools_dumpx("prepared node",__FILE__,__LINE__,$node);

        //process the node
        foreach ($node as $node_key => $node_key_value) {
            //echo $node_name . " <br/>";
            if($node_key == BEE_ANN){
                //string * means get everything
                //array() empty array means delete all my attributes after every thing
                $sra_res = segmentation_run_a($node_key_value,array(
                    "node_name" => $node_name,
                    "hive_structure" => $hive_structure,
                    "path" => $path
                ),$connection);
                $res[BEE_RI]["temp_sections_sql"] = $sra_res[BEE_RI];
                $res[BEE_EI] = array_merge($res[BEE_EI], $sra_res[BEE_EI]);
                $hive_structure = $sra_res[2];
                if(count($sra_res[BEE_EI])>0){ //dont continue if we have errors with _a processing
                    return $res;
                }
                continue;
            }

            if($node_key == BEE_WNN){
                $where_array = array_merge($node_key_value,$parents_w);
                //tools_dumpx("where_array",__FILE__,__LINE__,$where_array);
                $srw_res = segmentation_run_w($where_array,$node_name,$node,$hive_structure);
                $res[BEE_RI]["temp_where_sql"] = $srw_res[BEE_RI];
                $res[BEE_EI] = array_merge($res[BEE_EI], $srw_res[BEE_EI]);
                $hive_structure = $srw_res[2];
                $res[2] = $hive_structure;
                if(count($srw_res[BEE_EI])>0){ //dont continue if we have errors with _w processing
                    return $res;
                }
                //tools_dumpx("srw_res",__FILE__,__LINE__,$srw_res[BEE_RI]);
                continue;
            }


            //if execution reaches here it means this is a child node or parent node

            //detect parent
            //you must be singular and have parent_id column in this table
            $singular  = Inflect::singularize($node_key);
            $parent_id_exists = array_key_exists($singular."_id", $hive_structure[$comb_name]);
            if($node_key == $singular && $parent_id_exists ){
                $s_res = segmentation_run_process($node_key_value,array(
                    "path" => $path,
                    "node_name" => $node_key,
                    "hive_structure" => $hive_structure,
                    "parents_w" => array(),
                    "children" => $res[BEE_RI]["temp_children"],
                    "is_child_segmentation_run" => $is_child_segmentation_run
                ),$connection);
                //tools_dump("parent segmentation",__FILE__,__LINE__,$s_res[BEE_RI]);
                $res[BEE_RI]["temp_sections_sql"] = $res[BEE_RI]["temp_sections_sql"] . " " . $s_res[BEE_RI]["temp_sections_sql"];
                $res[BEE_EI] = array_merge($res[BEE_EI], $s_res[BEE_EI]);
                $hive_structure = $s_res[2];
                $res[BEE_RI]["temp_children"] =  $s_res[BEE_RI]["temp_children"];
                $res[BEE_RI]["temp_inner_join_sql"] = " INNER JOIN ".$singular." ON ".$comb_name.".".$singular."_id=".$singular.".id " . $s_res[BEE_RI]["temp_inner_join_sql"];
                continue;
            }


            //detect children
            //singular must be a table in the structure with table_id as parent column
            $plural  = Inflect::pluralize($node_key);
            $child_id_exists = array_key_exists($comb_name."_id", $hive_structure[$singular]);
            if($node_key == $plural && $child_id_exists ){
                //echo "foo";
                array_push($res[BEE_RI]["temp_children"],$path.BEE_SEP.$node_key);
                //var_dump($res[BEE_RI]["temp_children"]);
                $s_res = segmentation_run_process($node_key_value,array(
                    "path" => $path,
                    "node_name" => $node_key,
                    "hive_structure" => $hive_structure,
                    "parents_w" => array(),
                    "children" => $res[BEE_RI]["temp_children"],
                    "is_child_segmentation_run" => true
                ),$connection);
                $res[BEE_EI] = array_merge($res[BEE_EI], $s_res[BEE_EI]);
                $hive_structure = $s_res[2];
                $res[BEE_RI]["temp_children"] =  $s_res[BEE_RI]["temp_children"];
                //var_dump($res[BEE_RI]["temp_children"]);
                continue;
            }


            //if we have reached this far our query is wrong here
            //we can raise an error
            array_push($res[BEE_EI],"Invalid path: " . $path.BEE_SEP.$node_key);

        }
     
        //tools_dumpx("seg for child",__FILE__,__LINE__,$res);
        
        if(count($res[BEE_EI])>0){
            $res[BEE_RI] = null; //nullify results if there are any errors
        }
        array_push($res,$hive_structure);
        return $res;
    }

   
    
    



    function hive_after_segmentation_run_rm2($segmentation_run_res,$nectoroid,$structure,$connection){
        $res = array(null,array(),$structure);
        $sr_res = $segmentation_run_res;
        //tools_dumpx("hive segmentation results",__FILE__,__LINE__,$sr_res[BEE_RI]);
        $res[BEE_EI] = array_merge($res[BEE_EI],$sr_res[BEE_EI]);
        $res[2] = $sr_res[2];//the structure
        if(count($sr_res[BEE_EI]) == 0){//when we dont have any errors
            $sr_res = sqllization_run($sr_res[BEE_RI]);
            tools_dump("@11 sqllization_run res: ",__FILE__,__LINE__,$sr_res[BEE_RI]);
            //convert these queries into raw honey
            $pr_res = production_run($sr_res[BEE_RI],$connection);
            tools_dump("@12 production_run res: ",__FILE__,__LINE__,$pr_res[BEE_RI]);
            $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
            if(count($sr_res[BEE_EI]) == 0){//when we dont have any errors
                $pr_res = packaging_run_rm2($pr_res[BEE_RI],$nectoroid,$structure,$connection);
                $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
                $res[BEE_RI] = $pr_res[BEE_RI];
            }
        }
        return $res;
    }

    function  packaging_run_rm2($raw_honeys,$nectoroid,$structure,$connection){
        //tools_dump("testing honey",__FILE__,__LINE__,$raw_honeys);
        $res = array(array(),array());
        $honey = array();
        foreach ($raw_honeys as $raw_honey_index => $raw_honey_group) {
            //the raw_honey_group contains
            //raw_honey, paths_to_clean, children
            $raw_honey = $raw_honey_group["raw_honey"];
            foreach ($raw_honey as $row_index => $row_value) {
                $next_index = true;
                //tools_dump("row value",__FILE__,__LINE__,$row_value);
                foreach ($row_value as $path_to => $path_value) {
                    if(preg_match('/^\d+$/', $path_to)){
                        continue;
                    }else{
                        //desconstruct the path to the value
                        $path_parts = explode("__",$path_to); 

                        //walk through the honey
                        $honey_ref = &$honey;
                        for ($i=0; $i < count($path_parts); $i++) { 
                            $path_part = $path_parts[$i];
                            //detect if its the last part which indicates value
                            if($i+1 == count($path_parts)){
                                //nyd
                                //consult the structure to know the data type to 
                                //use to render the value

                                //nyd
                                //insert value
                                $honey_ref[$path_part] = $path_value;
                                unset($honey_ref); //http://php.net/manual/en/language.references.arent.php
                                continue;
                            }
                            if(!array_key_exists($path_part,$honey_ref)){
                                //determine if its a collection or an object
                                $singular  = Inflect::singularize($path_part);
                                if($singular == $path_part){//then path_part was singular
                                    $honey_ref[$path_part] = array();
                                    $honey_ref = &$honey_ref[$path_part];
                                }else{
                                    //create an array then create and object array inside this one
                                    $honey_ref[$path_part] = array(array());
                                    $honey_ref = &$honey_ref[$path_part][0];
                                    $next_index = false;
                                }
                            }else{
                                //the key exists
                                //determine if its a collection or an object
                                $singular  = Inflect::singularize($path_part);
                                if($singular == $path_part){//then path_part was singular
                                    //get a refrence to this object
                                    $honey_ref = &$honey_ref[$path_part];
                                }else{
                                    if($next_index){
                                        //echo "inserting a new object <br/>";
                                        //insert a new object and get a reference
                                        array_push($honey_ref[$path_part],array());
                                        $honey_ref = &$honey_ref[$path_part][count($honey_ref[$path_part])-1];
                                        $next_index = false;
                                    }else{
                                        //get the reference to the last element of the array
                                        $honey_ref = &$honey_ref[$path_part][count($honey_ref[$path_part])-1];
                                    }
                                }
                            }
                        }                           
                    }
                }
            } 
            tools_dump("@13 made child honey res: ",__FILE__,__LINE__,$honey);
            //process the children of this path_route
            //get the child queries at every node of the query
            //using the honey as parent refreneces for where clauses
            $child_paths = $raw_honey_group["children"];
            tools_dump("@13.1 child_paths: ",__FILE__,__LINE__,$child_paths);
            tools_dump("@13.2 nectoroid: ",__FILE__,__LINE__,$nectoroid);
            if(count($child_paths)==0){
                //the honey produced here is returned just as has been made
                tools_dump("No children here ",__FILE__,__LINE__,"w0000w");
            }else{
                tools_dump("i have gone to processes more children ",__FILE__,__LINE__,"mooom");
                $prc_res = packaging_run_children_rm($child_paths,$honey,$nectoroid,$structure,$connection);
                //at this point this is empty, it will not help us
            }
        }    
        tools_dump("@14 honey:",__FILE__,__LINE__,$honey);
        $res[BEE_RI] = $honey;
        return $res;
    }
    
?>