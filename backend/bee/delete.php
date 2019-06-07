<?php
    $node_name = "sections";
    $table_name = Inflect::singularize($node_name);
    $tools_res = tools_read($node_name,$query,$structure,$conn,"",array(),false,array());
    if(count($tools_res[1])==0){
            $query_form = array($node_name=>$query);
            tools_after_read($query_form,$tools_res); 
            //formulate sql to be exectuted
            $cols_sql = rtrim(trim($tools_res[0]["temp_cols_sql"]), ',');
            $where_sql = trim($tools_res[0]["temp_where_sql"]);
            $sql = "SELECT " . $cols_sql . " FROM " .  $table_name . " " . " " . $tools_res[0]["temp_inner_join_sql"];
            if(strlen($where_sql)>0){
                $sql = $sql . " WHERE " . $where_sql;
                echo $sql;
                exit(0);
            }
            $db_res = db_run(array("sql" => $sql,"conn" => $conn));
            $res[EI] = array_merge($res[EI],$db_res[EI]);
            //build the data into honey structure
            $rows = $db_res[RI]["data"];
            $honey = array();
            foreach ($rows as $row_index => $row_value) {
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
                                    //create an array then create and object array inside this one
                                    $honey_ref[$path_part] = array();
                                    $honey_ref = &$honey_ref[$path_part];
                                }else{
                                    $honey_ref[$path_part] = array(array());
                                    $honey_ref = &$honey_ref[$path_part][0];
                                }
                            }else{
                                //the key exists
                                //determine if its a collection or an object
                                $singular  = Inflect::singularize($path_part);
                                if($singular == $path_part){//then path_part was singular
                                    //get a refrence to this object
                                    $honey_ref = &$honey_ref[$path_part];
                                }else{
                                    //get the reference to the last element of the array
                                    $honey_ref = &$honey_ref[$path_part][count($honey_ref[$path_part])-1];
                                }
                            }
                        }                           
                    }
                }
            }

            //echo "<br/>^^^^^^^^^^^^^^<br/>";
            //var_dump($honey);
            //get the child queries at every node of the query
            //using the honey as parent refreneces for where clauses
            $chilren_paths = $tools_res[0]["temp_children"];
            for($c=0; $c < count($chilren_paths); $c++) {
                $chilren_path = $chilren_paths[$c];
                echo "<br/>processing: " . $chilren_path . "<br/>";
                $source_of_truth = $honey;
                $query_source = $query_form;
                //echo "<br/>";
                //var_dump($honey);
                //echo "<br/>";
                $former_ref_kind = "none";
                //echo $chilren_path . "<br/>";
                //desconstruct the path to the children
                $children_path_parts = explode("__",$chilren_path);
                for ($i=0; $i < count($children_path_parts); $i++) {
                    $children_path_part = $children_path_parts[$i];
                    //echo $children_path_part . "<br/>";
                    //detect if its the last part which indicates evaluation
                    if($i+1 == count($children_path_parts)){
                        $child_table_name  = Inflect::singularize($children_path_part);
                        //the source of truth has to contibute parent ids
                        $id_sql = array();
                        if($former_ref_kind == "array"){
                            $parent_table_name  = Inflect::singularize($children_path_parts[$i-1]);
                            foreach ($source_of_truth as $obj) {
                                if(count($id_sql)==0){
                                    array_push($id_sql,array(
                                        $parent_table_name . "_id",
                                        "=",
                                        $obj["id"]
                                    ));
                                }else{
                                    //get the former entry
                                    $entry = $id_sql[0];
                                    array_push($id_sql,array(
                                        $entry,
                                        "OR",
                                        array(
                                            $parent_table_name . "_id",
                                            "=",
                                            $obj["id"]
                                        )
                                    ));
                                }
                                
                            }
                        }elseif($this_is_an_object && $former_ref_kind == "object"){
                            $obj = $source_of_truth;
                            $id_sql = $id_sql . " " . $parent_table_name . "_id = " . $obj["id"] . " OR ";
                            if(count($id_sql)==0){
                                $id_sql[0] = array(
                                    $parent_table_name . "_id",
                                    "=",
                                    $obj["id"]
                                );
                            }else{
                                //get the former entry
                                $entry = $id_sql[0];
                                $id_sql[0] = array(
                                    $entry,
                                    "OR",
                                    array(
                                        $parent_table_name . "_id",
                                        "=",
                                        $obj["id"]
                                    )
                                );
                            }
                        }
                        //echo $id_sql . "<br/>";
                        //var_dump($id_sql);
                        //nyd
                        //get honey of these children
                        $child_node_name = $children_path_part;
                        $child_query = $query_source[$children_path_part];
                        $tools_children_res = tools_read($child_node_name,$child_query,$structure,$conn,"",array(),true,$id_sql); 

                        var_dump($tools_children_res);
                        //inject that honey into current honey structure

                        unset($source_of_truth);
                        //nyd
                        //some thing is missing here
                        continue;
                    }

                    //determine if path is a collection or an object
                    $singular  = Inflect::singularize($children_path_part);
                    if($singular == $children_path_part){
                        $this_is_an_object = true;
                        if($this_is_an_object && $former_ref_kind == "none"){
                            //the sot is a single object at this refrence
                            $obj = $source_of_truth[$children_path_part];
                            $source_of_truth = $obj;
                            $former_ref_kind = "object";
                            $query_source = $query_source[$children_path_part];
                        }elseif($this_is_an_object && $former_ref_kind == "array"){
                            //the sot is an array of objects
                            //where each object will contribute this path as the 
                            //new source of truth
                            $temp_sot = array();
                            foreach ($source_of_truth as $obj) {
                                $temp_ref = $obj[$children_path_part];
                                array_push($temp_sot,$temp_ref);
                            }
                            $source_of_truth = $temp_sot;
                            $former_ref_kind = "array";
                            $query_source = $query_source[$children_path_part];
                        }elseif($this_is_an_object && $former_ref_kind == "object"){
                            //the sot is an object which has an object that
                            //has to be the new source of truth
                            $obj = $source_of_truth[$children_path_part];
                            $source_of_truth = $obj;
                            $former_ref_kind = "object";
                            $query_source = $query_source[$children_path_part];
                        }
                    }else{
                        $this_is_an_array = true;
                        //this points to an array of objects
                        if($this_is_an_array && $former_ref_kind == "none"){
                            //the sot is an array of objects at this refrence
                            $temp_sot = array();
                            //var_dump($source_of_truth);
                            $temp_ref = $source_of_truth[$children_path_part];
                            foreach ($temp_ref as $obj) {
                                array_push($temp_sot,$obj);
                            }
                            $source_of_truth = $temp_sot;
                            $former_ref_kind = "array";
                            $query_source = $query_source[$children_path_part];
                        }elseif($this_is_an_array && $former_ref_kind == "object"){
                            //the sot is an object which has an array that
                            //has objects that are to be the new source of truth
                            $temp_sot = array();
                            $temp_ref = $source_of_truth[$children_path_part];
                            foreach ($temp_ref as $obj) {
                                array_push($temp_sot,$obj);
                            }
                            $source_of_truth = $temp_sot;
                            $former_ref_kind = "array";
                            $query_source = $query_source[$children_path_part];
                        }elseif($this_is_an_array && $former_ref_kind == "array"){
                            //the sot is an array of objects where each  has an array that
                            //has to be the new source of truth
                            $temp_sot = array();
                            foreach ($source_of_truth as $obj) {
                                $inner_array = $obj[$children_path_part];
                                foreach ($inner_array as $obj_sot) {
                                    array_push($temp_sot,$obj_sot);
                                }
                            }
                            $source_of_truth = $temp_sot;
                            $former_ref_kind = "array";
                            $query_source = $query_source[$children_path_part];
                        }
                    }
                }
                
            }
            //echo "<br/>^^^^^^^^^^^^^^<br/>";

            $res[RI] = array($sql,$rows,$honey,$query_form,$chilren_paths);
            $structure =  $tools_res[2];
            array_push($res,$structure);//the 
        }else{
            $res[EI] = $tools_res[1];
        }
        return $res;
    }

?>