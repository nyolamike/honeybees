<?php
    function mixer_interprete_attributes($table_name_s,$atts,$structure,$conn,$path){
        $errors = array();
        //make singular the table name
        $table_name = Inflect::singularize($table_name_s);

        //preprocess attributes
        $cols = array();
        if(is_string($atts)){
            $atts = trim(strtolower($atts));
            if($atts != "*"){
                $atts = explode(" ",$atts);
            }
        }
        
        if(is_array($atts)){
           
            foreach ($atts as $column_query) {
                //nyd
                //interprete the column query
                $temp_col = $column_query;
                array_push($cols,$temp_col);
            }
        }

        //if table does not exist then it will be created on this connection
        //when the setting of STRICT_HIVE == false;
        if(!array_key_exists($table_name,$structure) && STRICT_HIVE == false ){
            //create this table
            $colmuns = db_definition_to_cols($cols);
            $db_res = db_ct($conn, $table_name, $colmuns[0],SHOW_SQL_ON_ERRORS);
            $errors = array_merge($errors, $db_res[1]);
            
            //add this table to current structure and save it
            $structure[$table_name] = array();
            foreach ($cols as $col_nem) {
                $structure[$table_name][$col_nem] = db_default_column_def();
            }
            file_put_contents($structure["_for"], json_encode($structure));

            //nyd
            //implement transactions and rollback
        }
        if(!array_key_exists($table_name,$structure) && STRICT_HIVE == true ){
            //we have an error here because the table doesnot exist and we cannot create one
            array_push($errors,"Comb " . $table_name . " does not exist");
            return array("",$errors,$structure);
        }

        //this if comes after checking the existence of the table above
        $table_cols = $structure[$table_name];
        if($atts == "*" || (is_array($atts) && count($atts) == 1 && trim($atts[0]) == "*" )  ){
            $cols = array("id");
            //get all the cols for this table including the id
            foreach ($table_cols as $col_name => $col_def) {
                array_push($cols,$col_name);
            }
        }
        //if its empty
        if(count($atts) == 0){
            //this means you want to get all the attributes and then delete them 
            //when processing is done
            $cols = array("id");
            //get all the cols for this table including the id
            foreach ($table_cols as $col_name => $col_def) {
                array_push($cols,$col_name);
            }
            //nyd
            //indicate that these attributes/columns will need to be deleted 
            //after processing and actually delete them from the results
        }

        //the column must be part of the structure
        //if not create it and alter table when STRICT_HIVE == false
        $sql = " ";
        foreach ($cols as $col_name) {
            if(tools_startsWith($col_name,"_")){
                continue;
            }
            if(!array_key_exists($col_name,$table_cols) && $col_name != "id" && STRICT_HIVE == false ){
                //alter table here and structure
                $colm_sql = db_vcn($col_name,100); 
                $db_res = db_ac($conn,$table_name, $colm_sql);
                $errors = array_merge($errors, $db_res[1]);
                //if there were any errors we cannot continue
                if(count($db_res[1]) > 0){
                    return array(null,$errors,$structure);
                }
                //add this column to the structure
                $structure[$table_name][$col_name] = db_default_column_def();
                file_put_contents($structure["_for"], json_encode($structure));
            }
            $temp_path_to = $path . "__" . $col_name;
            $sql = $sql . " " . $table_name . "." . $col_name . " as ". $temp_path_to .",";
        }
        return array($sql,$errors,$structure);
    }


    function mixer_interprete_where($node,$node_name,$where_array,$structure,$connection,$path){
        $errors = array();
        $sql = "";
        $singular  = Inflect::singularize($node_name);
        for ($i=0 ; $i < count($where_array); $i++ ) { 
            $sql = $sql . " " . mixer_remix($where_array[$i],$singular);
        }
        //var_dump($sql);
        
        return array($sql,$errors,$structure);
    }
    
    function mixer_remix($config,$singular){
        $left = $config[0];
        $condition = trim($config[1]);
        $right = $config[2];
        $sql = "";
        if($condition == "="){
            //left side =  right side
            $val_left = (is_array($left))? mixer_remix($config) : ("" . $singular . "." . $left);
            $val_right = (is_array($right))? mixer_remix($right) : ("" . $singular . "." . $right);
            $sql = $sql . " " . $val_left . " = " . $right;
        }
        return $sql;
    }

    function mixer_construct_selection_sql($table_name,$sql_segments){
        $sections_sql = rtrim(trim($sql_segments[0]["temp_cols_sql"]), ',');
        $where_sql = trim($sql_segments[0]["temp_where_sql"]);
        $inner_joins_sql = $sql_segments[0]["temp_inner_join_sql"];
        $sql = "SELECT " . $cols_sql . " FROM " .  $table_name . " " . $inner_joins_sql . " ";
        if(strlen($where_sql)>0){
            $sql = $sql . " WHERE " . $where_sql;
            echo $sql;
            exit(0);
        }
        return $sql;
    }

    function mixer_construct_honey($rows){
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
        return $honey;
    }

?>