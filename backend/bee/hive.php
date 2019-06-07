<?php

function hive_run_get_connection($username, $password,$servername="localhost",$databasename="",$is_test=false){
    try {
        if($is_test){
            $conn = new PDO("mysql:host=$servername", $username, $password);
        }else{
            $conn = new PDO("mysql:host=$servername;dbname=$databasename", $username, $password);
        }
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return array($conn,array());
    }catch(PDOException $e)
    {
        return array(null, array($e->getMessage()));
    }
}

//run config
//sql, hive name, show sql on error
function hive_run($sql,$connection){
    global $bee_show_sql;
    global $bee_sql_backet;

    $hive_response = array(
        array(
            "hive_res" => null,
            "id" => 0, 
            "data" => array()
        ),
        array()
    );
    try{  
        if(tools_startsWith($sql,"INSERT") == TRUE){
            
            //echo $sql;
            array_push($bee_sql_backet["inserts"],$sql);
            $res = $connection->exec($sql);
            //get the last insert id
            $liid = $connection->lastInsertId();
            $hive_response[BEE_RI]["id"] = $liid;
            $hive_response[BEE_RI]["hive_res"] = $res;
        }else if(tools_startsWith($sql,"SELECT") == TRUE){
            $ky = " WHERE ";
            //nyd
            //wrong position for where, should be shifted to a microposition
            // $where_pos = strpos($sql,$ky);
            // if(BEE_SUDO_DELETE && $where_pos > 0){
            //     $prt1 = substr($sql,0,$where_pos);
            //     $where_pos2 = $where_pos + strlen($ky);
            //     $prt2 = substr($sql,$where_pos2);
            //     $ky = " WHERE ( is_deleted = 0 ) AND ( ";
            //     $sql2 = $prt1 . $ky . $prt2 . " ) ";
            //     //tools_dumpx("SELECTs",__FILE__,__LINE__,array($prt1,$prt2,$sql,$sql2));
            //     $sql = $sql2;
            // }elseif(BEE_SUDO_DELETE){
            //     $ky = " WHERE  is_deleted = 0 ";
            //     $sql = $sql . $ky;
            // }
            //_fx_WHERE
            $sql = str_replace("_fx_WHERE","WHERE",$sql);
            // if(strpos($sql,"-")>0){
            //     tools_dump("sql ",__FILE__,__LINE__,$sql);
            // }
            //tools_dump("sql ",__FILE__,__LINE__,$sql);
            array_push($bee_sql_backet["selects"],$sql);
            $res = $connection->query($sql);
            $data = array();
            foreach ($res as $row) {
                array_push($data, $row);
            }
            $hive_response[BEE_RI]["hive_res"] = $res;
            $hive_response[BEE_RI]["data"] = $data;
        }else if(tools_startsWith($sql,"UPDATE") == TRUE){
            //tools_dumpx("UPDATE",__FILE__,__LINE__,$sql);
            //$res = $connection->exec($sql);
            //_fx_WHERE
            $sql = str_replace("_fx_WHERE","WHERE",$sql);
            //tools_dumpx("UPDATE",__FILE__,__LINE__,$sql);
            array_push($bee_sql_backet["updates"],$sql);
            $pdo_statment = $connection->prepare($sql);
            $res = $pdo_statment->execute();
            $hive_response[BEE_RI]["num"] = $pdo_statment->rowCount();
            $hive_response[BEE_RI]["hive_res"] = $res;
        }else if(tools_startsWith($sql,"DELETE") == TRUE){
            //tools_dumpx("DELETE",__FILE__,__LINE__,$sql);
            array_push($bee_sql_backet["deletes"],$sql);
            $pdo_statment = $connection->prepare($sql);
            $res = $pdo_statment->execute();
            $hive_response[BEE_RI]["num"] = $pdo_statment->rowCount();
            $hive_response[BEE_RI]["hive_res"] = $res;
        }else{
            array_push($bee_sql_backet["other"],$sql);
            $res = $connection->exec($sql);
            $hive_response[BEE_RI]["hive_res"] = $res;
        }
    }catch(PDOException $e){
        $msg = $e->getMessage() . " " . ((SHOW_SQL_ON_ERRORS == TRUE)? $sql : "");
        array_push($hive_response[BEE_EI], $msg);
    }catch(Exception $e){
        $msg = $e->getMessage() . " " . ((SHOW_SQL_ON_ERRORS == TRUE)? $sql : "");
        array_push($hive_response[BEE_EI], $msg);
    }
    return $hive_response;
}

function hive_run_create_hive($hive_name,$connection){
    $sql = "CREATE DATABASE IF NOT EXISTS " . $hive_name;
    $hr_res = hive_run($sql,$connection);
    return $hr_res;
}

function hive_run_inn($col_name,$num){ return tools_chain($col_name, "`{}` int(".$num.") NOT NULL"); }

function hive_run_fk($col_name){ return tools_chain($col_name, "`{}` int(30) NOT NULL"); }

function hive_run_inn_d($col_name,$num, $default){ 
    return tools_chain($col_name, "`{}` int(".$num.") NOT NULL DEFAULT '". $default. "'");
}

function hive_run_in($col_name,$num){ return tools_chain($col_name, "`{}` int(".$num.") NULL "); }

function hive_run_dnn($col_name){ return tools_chain($col_name, "`{}` DOUBLE NOT NULL DEFAULT '0.0' "); }

function hive_run_vcnn($col_name,$num){ return tools_chain($col_name, "`{}` varchar(".$num.") NOT NULL"); }

function hive_run_vcn($col_name,$num){ return tools_chain($col_name, "`{}` varchar(".$num.") NULL"); }

function hive_run_vcnn_d($col_name,$num,$default){ 
    return tools_chain($col_name, "`{}` varchar(".$num.") NOT NULL DEFAULT '".$default."' "); 
}

function hive_run_tn($col_name){ return tools_chain($col_name, "`{}` text NULL"); }

function hive_run_tnn($col_name){ return tools_chain($col_name, "`{}` text NOT NULL"); }

function hive_run_tsnn($col_name){ return tools_chain($col_name, "`{}` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP "); }

function hive_run_dtnn($col_name){ return tools_chain($col_name, "`{}`  DATE NOT NULL "); }

function hive_run_default_secture(){
    return array("tn");
}

//the process of converting section structures
//e.g ["vcnn",60],["vcnn",60]
//and converting them to temp sqls
//a secture is a section structure ["vcnn",60]
//sectures usually is an object from the app.json file
function hive_run_secture_sqlization($sectures){
    $section_sqls = array();
    foreach ($sectures as $section_name => $secture) {
        //e.g hidden fields flag like "_hidden":["code","password"]
        //must not be interpreted from here
        if(tools_startsWith($section_name,"_") || tools_startsWith($secture,"_")){
            continue;
        }
        //apply a default definition if no definition is found
        //this could happen when just the section name is provided
        //in this case the secture is the value while section_name is a numeric index
        //thats how php does its things
        if(is_numeric($section_name)){
            array_push($section_sqls,hive_run_tn($secture));
            continue;
        }

        if(tools_startsWith($secture[0],"inn")){ array_push($section_sqls,hive_run_inn($section_name,$secture[1])); continue;}
        if(tools_startsWith($secture[0],"fk")){ array_push($section_sqls,hive_run_fk($section_name)); continue;}
        if(tools_startsWith($secture[0],"inn_d")){ array_push($section_sqls,hive_run_inn_d($section_name,$secture[1],$secture[2])); continue;}
        if(tools_startsWith($secture[0],"in")){ array_push($section_sqls,hive_run_in($section_name,$secture[1])); continue;}
        if(tools_startsWith($secture[0],"dnn")){ array_push($section_sqls,hive_run_dnn($section_name)); continue;}
        if(tools_startsWith($secture[0],"vcnn")){ array_push($section_sqls,hive_run_vcnn($section_name,$secture[1])); continue;}
        if(tools_startsWith($secture[0],"vcn")){ array_push($section_sqls,hive_run_vcn($section_name,$secture[1])); continue;}
        if(tools_startsWith($secture[0],"vcnn_d")){ array_push($section_sqls,hive_run_vcnn_d($section_name,$secture[1],$secture[2]));  continue;}
        if(tools_startsWith($secture[0],"tn")){ array_push($section_sqls,hive_run_tn($section_name)); continue;}
        if(tools_startsWith($secture[0],"tnn")){ array_push($section_sqls,hive_run_tnn($section_name)); continue;}
        // if(count($secture)==0){
        //     tools_dump("secturesecture: ",__FILE__,__LINE__,[$secture,$section_name,$section_sqls]);
        // }
        if(tools_startsWith($secture[0],"tsnn")){ array_push($section_sqls,hive_run_tsnn($section_name)); continue;}
        if(tools_startsWith($secture[0],"dtnn")){ array_push($section_sqls,hive_run_dtnn($section_name)); continue;}
    }
    return array($section_sqls,array());
}



function hive_run_sql_ct($comb_name,$sections_sql){
    $sql = "CREATE TABLE IF NOT EXISTS `" . $comb_name . "` (";
    $sql = $sql . " `id` int(30) NOT NULL AUTO_INCREMENT,";
    for ($i=0; $i < count($sections_sql); $i++) { 
        $sql = $sql . " " . $sections_sql[$i] . ",";
    }
    $sql = $sql . " `time_inserted` int(30) NOT NULL,";
    $sql = $sql . " `inserted_by` varchar(100) DEFAULT NULL,";
    $sql = $sql . " `time_last_modified` int(30) NOT NULL,";
    $sql = $sql . " `last_modified_by` varchar(100) DEFAULT NULL,";
    $sql = $sql . " `is_deleted` int(30) NOT NULL DEFAULT 0,";
    $sql = $sql . " `guid` text NOT NULL,";
    $sql = $sql . " PRIMARY KEY (`id`) );";
    return array($sql,array());
}

function hive_run_ct($connection,$comb_name, $sections_sqls){
    $sql = hive_run_sql_ct($comb_name,$sections_sqls)[BEE_RI];
    $hr_res = hive_run($sql,$connection);
    return $hr_res;
}

//db add column
function hive_run_ac($connection,$comb_name,$sections_sql){
    $sql  = "ALTER ".$comb_name." ADD " . $sections_sql;
    $hr_res = hive_run($sql,$connection);
    return $hr_res;
}

function db_read_indexed($conn,$sql,$show_errors){
    $config = array(
        "sql" => $sql,
        "conn" => $conn,
        "show_sql_on_errors" => $show_errors
    );
    $db_res = db_run($config);
    if(count($db_res[1])==0){
        $indexed = array();
        $temp = $db_res[0]["data"];
        foreach ($temp as $index => $row) {
            $indexed[$row["id"]] = $row;
        }
        return array($indexed,array());
    }else{
        return array(null,$db_res[1]);
    }
}


//checkts if the garden for this application exists
//if not it creates it
//the garden id like the master db
//this will return the connection to the master hive/db/garden
//nyd
//we will need to create a caching solution here so that no every request
//that comes to the server actually has to chech for this
function hive_run_ensure_garden_exists($garden_structrue){
    $res = array(true,array());
    $hrgc_res = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,"",true);
    $res[BEE_EI] = array_merge($res[BEE_EI],$hrgc_res[BEE_EI]);
    $connection = $hrgc_res[BEE_RI];
    if(count($hrgc_res[BEE_EI]) == 0){
        $hrch_res = hive_run_create_hive(BEE_GARDEN,$connection);
        //var_dump($hrch_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$hrch_res[BEE_EI]);
        //if there are no errors continue to create combs here
        if(count($hrch_res[BEE_EI]) == 0){
            //get the connection to the created hive
            //close connection to test db
            $connection = null;
            unset($connection);
            $hrgc_res = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,BEE_GARDEN,false);
            $res[BEE_EI] = array_merge($res[BEE_EI],$hrgc_res[BEE_EI]);
            $connection = $hrgc_res[BEE_RI];
            if($hrch_res[BEE_RI]["hive_res"] == 1){ //if the hive has just been created
                foreach ($garden_structrue as $comb_name => $sectures) {
                    if(tools_startsWith($comb_name,"_")){
                        continue;
                    }
                    $hrss_res = hive_run_secture_sqlization($sectures);
                    $sections_sqls = $hrss_res[BEE_RI];
                    $hrct_res =  hive_run_ct($connection,$comb_name, $sections_sqls);
                    $res[BEE_EI] = array_merge($res[BEE_EI],$hrct_res[BEE_EI]);
                }
            }
            
        }
    }
    if(count($res[BEE_EI])>0){
        $res[BEE_RI] = false;
    }else{
        $res[BEE_RI] = $connection;
    }
    return $res;
}

//original query
/*"sections"  => array(
    "_a" => array(),
    "comb" => array(
        "hive" => array(
            "modules" => array(
                "hive" => array(
                    "modules" => array()  
                )
            )  
        )
    ),
    "section_fragiles" => array(
        "section" => array(
            "comb" => array(
                "hive" => array(
                    "modules" => array()  
                )
            ),
        )
    )
)*/

/*
"sections"  => array(
            "_a" => array(),
            "comb" => array(
                "hive" => array(
                    "modules" => array(
                        "hive" => array(
                            "modules" => array()  
                        )
                    )
                )
            ),
            "section_fragiles" => array(
            )
        )
*/
/*
"combs" => array(),
        "section_fragiles" => array(
            "section" => array(
                "comb" => array(
                    "hive" => array(
                        "modules" =>  array()
                    )
                )
            )
        )
*/
function hive_run_get_garden($structure,$connection){
    $res = array(null,array(),$structure);
    $nectoroid = array(
        "hives" => array(
            "combs" => array(
                "sections" => array(
                    "section_fragiles" => array()
                )
            ),
            "modules" => array(),
            "hive_users" => array()
        )
    );
    $sr_res = segmentation_run($nectoroid,$structure,$connection);
    //tools_dump("@1 segmentation_run res: ",__FILE__,__LINE__,$sr_res[BEE_RI]);
    $hasr_res = hive_after_segmentation_run($sr_res,$nectoroid,$structure,$connection);
    $res[BEE_RI] = $hasr_res[BEE_RI];
    $res[BEE_EI] = array_merge($res[BEE_EI],$hasr_res[BEE_EI]);
    $res[2] = $hasr_res[2];
    return $res; 
}

function hive_after_segmentation_run($segmentation_run_res,$nectoroid,$structure,$connection){
    global $xt_order;
    $res = array(null,array(),$structure);
    $sr_res = $segmentation_run_res;
    //tools_dumpx("hive segmentation results",__FILE__,__LINE__,$sr_res[BEE_RI]);
    $res[BEE_EI] = array_merge($res[BEE_EI],$sr_res[BEE_EI]);
    $res[2] = $sr_res[2];//the structure
    if(count($sr_res[BEE_EI]) == 0){//when we dont have any errors
        
        //remove any extraction instructons id any
        $xtu = array();
        if(array_key_exists("xtu",$sr_res[BEE_RI])){
            //tools_dump("xtu added",__FILE__,__LINE__,$sr_res[BEE_RI]);
            $xtu = $sr_res[BEE_RI]["xtu"];
            unset($sr_res[BEE_RI]["xtu"]);
            //tools_dump("xtu removed",__FILE__,__LINE__,$sr_res[BEE_RI]);
        }
        //remove any n extractions into values array
        $nxtva = array();
        if(array_key_exists("_nxtva_",$sr_res[BEE_RI])){
            //tools_dump("nxtva added",__FILE__,__LINE__,$sr_res[BEE_RI]);
            $nxtva = $sr_res[BEE_RI]["_nxtva_"];
            unset($sr_res[BEE_RI]["_nxtva_"]);
            //tools_dump("nxtva removed",__FILE__,__LINE__,$sr_res[BEE_RI]);
        }
        //remove any indexing into 
        $indexing = array();
        if(array_key_exists("_index_",$sr_res[BEE_RI])){
            //tools_dump("nxtva added",__FILE__,__LINE__,$sr_res[BEE_RI]);
            $indexing = $sr_res[BEE_RI]["_index_"];
            unset($sr_res[BEE_RI]["_index_"]);
            //tools_dump("nxtva removed",__FILE__,__LINE__,$sr_res[BEE_RI]);
        }
        if(array_key_exists("_indexk_",$sr_res[BEE_RI])){ //index and keep
            //tools_dump("nxtva added",__FILE__,__LINE__,$sr_res[BEE_RI]);
            $indexing = $sr_res[BEE_RI]["_indexk_"];
            unset($sr_res[BEE_RI]["_indexk_"]);
            //tools_dump("nxtva removed",__FILE__,__LINE__,$sr_res[BEE_RI]);
        }
        //remove any _tree_sum_ operations
        $tree_sums = array();
        if(array_key_exists("_tree_sum_",$sr_res[BEE_RI])){ //tree sum
            //tools_dump("nxtva added",__FILE__,__LINE__,$sr_res[BEE_RI]);
            $tree_sums = $sr_res[BEE_RI]["_tree_sum_"];
            unset($sr_res[BEE_RI]["_tree_sum_"]);
            //tools_dump("nxtva removed",__FILE__,__LINE__,$sr_res[BEE_RI]);
        }

        $sr_res = sqllization_run($sr_res[BEE_RI]);
        //tools_dump("@2 sqllization_run res: ",__FILE__,__LINE__,$sr_res[BEE_RI]);
        //convert these queries into raw honey
        $pr_res = production_run($sr_res[BEE_RI],$connection);
        //tools_dump("@3 production_run res: ",__FILE__,__LINE__,$pr_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
        if(count($pr_res[BEE_EI]) == 0){//when we dont have any errors
            $pr_res = packaging_run($pr_res[BEE_RI],$nectoroid,$structure,$connection);
            $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
            $res[BEE_RI] = $pr_res[BEE_RI];
        }
        //$node_name = str_replace("_xtu_","",$root_node_name);

        //process extractions of xtu
        foreach ($xt_order as $valueXt) {
            if($valueXt == "xtu"){
                if(!empty($xtu)){
                    foreach ($xtu as $xtu_key => $xtu_path) {
                        $singular_xtu_key = Inflect::singularize($xtu_key); 
        
                        $xtracted = array();
                        $xtu_path_parts = explode(".",$xtu_path);
                        $value_source = $res[BEE_RI];//a reference to extract the final value
                        $prev = "none"; //what is the nature of the previous
                        for ($i=0; $i < count($xtu_path_parts); $i++) { 
                            $xtu_path_part = $xtu_path_parts[$i];
                            $singular_xtu = Inflect::singularize($xtu_path_part); 
                            //detect the last path
                            if($i+1 == count($xtu_path_parts)){
                                if($singular_xtu == $xtu_path_part){
                                    //its an object
                                    if($prev == "none"){
                                        //the reference is as an object at this point
                                        //there is an object we are looking for here
                                        $temp_obj = $value_source[$xtu_path_part];
                                        $xtracted = $temp_obj;
                                    }elseif($prev == "object"){
                                        //the reference is as an object at this point
                                        //there is an object we are looking for here
                                        $temp_obj = $value_source[$xtu_path_part];
                                        $xtracted = $temp_obj;
                                    }elseif($prev == "array"){
                                        //reference is an array
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($value_source as $sovObj) {
                                            $sov = $sovObj[$xtu_path_part];
                                            if(!in_array($sov["id"],$temp_sov_keys)){
                                                array_push($temp_sov_keys,$sov["id"]);
                                                array_push($xtracted,$sov);
                                            }
                                        }
                                    }
                                }else{
                                    //its an array. period
                                    if($prev == "none"){
                                        //the reference is as an array of objects at his point
                                        //thevery object in the array has an attibute of the 
                                        //target which is what we are looking for
                                        //we are just beginig our travel
                                        $temp_array = $value_source[$xtu_path_part];
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($temp_array as $sov) {
                                            if(!in_array($sov["id"],$temp_sov_keys)){
                                                array_push($temp_sov_keys,$sov["id"]);
                                                array_push($xtracted,$sov);
                                            }
                                        }
                                        $prev = "none";
                                    }elseif($prev == "object"){
                                        //the refrence is an object, which has an array of these objects
                                        //at this location
                                        $temp_array = $value_source[$xtu_path_part];
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($temp_array as $sov) {
                                            if(!in_array($sov["id"],$temp_sov_keys)){
                                                array_push($temp_sov_keys,$sov["id"]);
                                                array_push($xtracted,$sov);
                                            }
                                        }
                                        $prev = "none";
                                    }elseif($prev == "array"){
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($value_source as $sovObj) {
                                            $answer_array = $sovObj[$xtu_path_part];
                                            //our answer is an array of objects
                                            //we take only unique object of this array
                                            foreach ($answer_array as $sov) {
                                                if(!in_array($sov["id"],$temp_sov_keys)){
                                                    array_push($temp_sov_keys,$sov["id"]);
                                                    array_push($xtracted,$sov);
                                                }
                                            }
                                        }
                                        $prev = "none";
                                    }
                                }
                                $prev = "none";
                                continue;
                            }
        
                            if($singular_xtu == $xtu_path_part){
                                //this node is an object
                                if($prev == "none"){
                                    //our travel has just began
                                    //the value source contains an object at this location
                                    $temp_array = $value_source[$xtu_path_part];
                                    //that object is the root to our values
                                    $value_source = $temp_array;
                                    $prev = "object";
                                }elseif($prev == "object"){
                                    //the current ref is an object which has an object at this location
                                    //that object is the root to sov
                                    $temp_array = $value_source[$xtu_path_part];
                                    //that object is the root to our values
                                    $value_source = $temp_array;
                                    $prev = "object";
                                }elseif($prev == "array"){
                                    //the refrence is an array of objects
                                    //each object contains an object at this location as the source
                                    //of values sov
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($value_source as $sovHolder) {
                                        $sov = $sovHolder[$xtu_path_part];
                                        if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sov["id"]);
                                            array_push($temp_sov,$sov);
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }
                            }else{
                                //this node is an array
                                if($prev == "none"){
                                    //we are just beginig our travel
                                    //the value source contains an array at this location
                                    $temp_array = $value_source[$xtu_path_part];
                                    //every object in this array is a potential source of the value
                                    //we are looking for
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($temp_array as $sov) {
                                        if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sov["id"]);
                                            array_push($temp_sov,$sov);
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }elseif($prev == "object"){
                                    //the refrence is an object and contains an array at this location
                                    //which has objects that are to become the sov
                                    $temp_array = $value_source[$xtu_path_part];
                                    //every object in this array is a potential source of the value
                                    //we are looking for
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($temp_array as $sov) {
                                        if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sov["id"]);
                                            array_push($temp_sov,$sov);
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }elseif($prev == "array"){
                                    //the refrence is an array of ojects and each object
                                    //contains an array at this location with a batch of 
                                    //child objects that are to be the sov
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($value_source as $sovHolder) {
                                        $temp_array = $sovHolder[$xtu_path_part];
                                        //every object in this array is a potential source of the value
                                        //we are looking for
                                        foreach ($temp_array as $sov) {
                                            if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                                array_push($temp_sov_keys,$sov["id"]);
                                                array_push($temp_sov,$sov);
                                            }
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }
                            }
                        }
                        
        
                        $res[BEE_RI][$xtu_key] = $xtracted;
                        unset($res[BEE_RI][$xtu_path_parts[0]]);//delete previous honey at this node
                    }
                }
            }//end if xtu

            if($valueXt == "_nxtva_"){
                if(!empty($nxtva)){
                    //nyd
                    //please correct this algorithm
                    foreach ($nxtva as $xtva_key => $xtva_path) {
                        //the node at $xtva_key contains n objects
                        $values = array();
                        $xtracted = array();
                        $xtu_path_parts = explode(".",$xtva_path);
                        //tools_dumpx("xtu_path_parts",__FILE__,__LINE__,$xtu_path_parts);
                        $nobjects = $res[BEE_RI][$xtva_key];
                        foreach ($nobjects as $nobject_key => $nobject_honey) {
                            $value_source = $nobject_honey;//a reference to extract the final value
                            $prev = "none"; //what is the nature of the previous
                            for ($i=0; $i < count($xtu_path_parts); $i++) { 
                                $xtu_path_part = $xtu_path_parts[$i];
                                $singular_xtu = Inflect::singularize($xtu_path_part); 
                                //detect the last path
                                if($i+1 == count($xtu_path_parts)){
                                    if($singular_xtu == $xtu_path_part){
                                        //format
                                        $is_int = false;
                                        $is_double = false;
                                        if(tools_startsWith($xtu_path_part, "_int_")){
                                            $xtu_path_part = substr($xtu_path_part,5);
                                            $is_int = true;
                                        }
                                        if(tools_startsWith($xtu_path_part, "_dbl_")){
                                            $xtu_path_part = substr($xtu_path_part,5);
                                            $is_double = true;
                                        }
                                        //its an object
                                        if($prev == "none"){
                                            //the reference is as an object at this point
                                            //there is an object we are looking for here
                                            //tools_dumpx("structure",__FILE__,__LINE__,array($xtu_path_part,$value_source));
                                            $temp_obj = $value_source[0][$xtu_path_part];
                                            $v = $temp_obj;
                                            if($is_int){ $v = intval($v); } 
                                            if($is_double){ $v = doubleval($v); }
                                            array_push($values,$v);
                                        }elseif($prev == "object"){
                                            //the reference is as an object at this point
                                            //there is an object we are looking for here
                                            $temp_obj = $value_source[0][$xtu_path_part];
                                            $v = $temp_obj;
                                            if($is_int){ $v = intval($v); } 
                                            if($is_double){ $v = doubleval($v); }
                                            array_push($values,$v);
                                        }elseif($prev == "array"){
                                            //reference is an array
                                            $temp_sov_keys = array(); //we dont need duplicates
                                            foreach ($value_source as $sovObj) {
                                                $sov = $sovObj[$xtu_path_part];
                                                $v = $sov;
                                                if($is_int){ $v = intval($v); } 
                                                if($is_double){ $v = doubleval($v); }
                                                array_push($values,$v);
                                            }
                                        }
                                    }else{
                                        //its an array. period
                                        if($prev == "none"){
                                            //the reference is as an array of objects at his point
                                            //thevery object in the array has an attibute of the 
                                            //target which is what we are looking for
                                            //we are just beginig our travel
                                            $temp_array = $value_source[$xtu_path_part];
                                            $temp_sov_keys = array(); //we dont need duplicates
                                            foreach ($temp_array as $sov) {
                                                $v = $sov;
                                                if($is_int){ $v = intval($v); } 
                                                if($is_double){ $v = doubleval($v); }
                                                array_push($values,$v);
                                            }
                                            $prev = "none";
                                        }elseif($prev == "object"){
                                            //the refrence is an object, which has an array of these objects
                                            //at this location
                                            $temp_array = $value_source[$xtu_path_part];
                                            $temp_sov_keys = array(); //we dont need duplicates
                                            foreach ($temp_array as $sov) {
                                                $v = $sov;
                                                if($is_int){ $v = intval($v); } 
                                                if($is_double){ $v = doubleval($v); }
                                                array_push($values,$v);
                                            }
                                            $prev = "none";
                                        }elseif($prev == "array"){
                                            $temp_sov_keys = array(); //we dont need duplicates
                                            foreach ($value_source as $sovObj) {
                                                $answer_array = $sovObj[$xtu_path_part];
                                                //our answer is an array of objects
                                                //we take only unique object of this array
                                                foreach ($answer_array as $sov) {
                                                    $v = $sov;
                                                    if($is_int){ $v = intval($v); } 
                                                    if($is_double){ $v = doubleval($v); }
                                                    array_push($values,$v);
                                                }
                                            }
                                            $prev = "none";
                                        }
                                    }
                                    $prev = "none";
                                    continue;
                                }
        
                                if($singular_xtu == $xtu_path_part){
                                    //this node is an object
                                    if($prev == "none"){
                                        //our travel has just began
                                        //the value source contains an object at this location
                                        $temp_array = $value_source[$xtu_path_part];
                                        //that object is the root to our values
                                        $value_source = $temp_array;
                                        $prev = "object";
                                    }elseif($prev == "object"){
                                        //the current ref is an object which has an object at this location
                                        //that object is the root to sov
                                        $temp_array = $value_source[$xtu_path_part];
                                        //that object is the root to our values
                                        $value_source = $temp_array;
                                        $prev = "object";
                                    }elseif($prev == "array"){
                                        //the refrence is an array of objects
                                        //each object contains an object at this location as the source
                                        //of values sov
                                        $temp_sov = array();
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($value_source as $sovHolder) {
                                            $sov = $sovHolder[$xtu_path_part];
                                            array_push($temp_sov,$sov);
                                        }
                                        //the refrence now becomes the new source of truth
                                        $value_source = $temp_sov;
                                        $prev = "array";//now out sov is an array of objects
                                    }
                                }else{
                                    //this node is an array
                                    if($prev == "none"){
                                        //we are just beginig our travel
                                        //the value source contains an array at this location
                                        $temp_array = $value_source[$xtu_path_part];
                                        //every object in this array is a potential source of the value
                                        //we are looking for
                                        $temp_sov = array();
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($temp_array as $sov) {
                                            array_push($temp_sov,$sov);
                                        }
                                        //the refrence now becomes the new source of truth
                                        $value_source = $temp_sov;
                                        $prev = "array";//now out sov is an array of objects
                                    }elseif($prev == "object"){
                                        //the refrence is an object and contains an array at this location
                                        //which has objects that are to become the sov
                                        $temp_array = $value_source[$xtu_path_part];
                                        //every object in this array is a potential source of the value
                                        //we are looking for
                                        $temp_sov = array();
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($temp_array as $sov) {
                                            array_push($temp_sov,$sov);
                                        }
                                        //the refrence now becomes the new source of truth
                                        $value_source = $temp_sov;
                                        $prev = "array";//now out sov is an array of objects
                                    }elseif($prev == "array"){
                                        //the refrence is an array of ojects and each object
                                        //contains an array at this location with a batch of 
                                        //child objects that are to be the sov
                                        $temp_sov = array();
                                        $temp_sov_keys = array(); //we dont need duplicates
                                        foreach ($value_source as $sovHolder) {
                                            $temp_array = $sovHolder[$xtu_path_part];
                                            //every object in this array is a potential source of the value
                                            //we are looking for
                                            foreach ($temp_array as $sov) {
                                                array_push($temp_sov,$sov);
                                            }
                                        }
                                        //the refrence now becomes the new source of truth
                                        $value_source = $temp_sov;
                                        $prev = "array";//now out sov is an array of objects
                                    }
                                }
                            }
                        }
                        $res[BEE_RI][$xtva_key] = $values;
                    }
                }
            }//end if _nxtva_

            if($valueXt == "_index_" || $valueXt == "_indexk_"){
                
                //process indexing
                if(!empty($indexing)){
                    //tools_dumpx("indexing list: ",__FILE__,__LINE__,[$xt_order,$indexing,$res[BEE_RI]]);
                    foreach ($indexing as $indexing_key => $indexing_path) {
                        
                        $indexing_key_final_name = $indexing_key;
                        $indexed = array();
                        $index_path_parts = explode(".",$indexing_path);
                        
                        $is_keep = false;
                        if(tools_startsWith($indexing_key,"_keep_")){
                            $is_keep = true;
                            $indexing_key = str_replace("_keep_","",$indexing_key);
                        };

                        $singular_index_key = Inflect::singularize($indexing_key); 
                        if(count($index_path_parts) > 1){
                            //get the case for deleting
                            $indexing_key = $index_path_parts[0];
                        }

                        
                        //tools_dumpx("index_path_parts: ",__FILE__,__LINE__,$index_path_parts);
                        $value_source = $res[BEE_RI];//a reference to extract the final value
                        $prev = "none"; //what is the nature of the previous
                        
                        for ($i=0; $i < count($index_path_parts); $i++) { 
                            $index_path_part = $index_path_parts[$i];
                            $singular_index = Inflect::singularize($index_path_part); 
                            
                            //detect the second last path
                            //its expected that the previous ref be an array
                            if($i+1 == count($index_path_parts)){
                                //its assumed that the previous reference is an array
                                if($prev == "none"){
                                    //this operation is not valid here
                                    array_push($sr_res[BEE_EI],"Indexing cannot be done on a plain: _index_".$indexing_key.":" . $indexing_path);
                                    $prev = "none";
                                }elseif($prev == "object"){
                                    //the refrence is an object, which has an array of these objects
                                    //at this location
                                    //this is wrong for this kind of operation
                                    array_push($sr_res[BEE_EI],"Indexing cannot be done on an object: _index_".$indexing_key.":" . $indexing_path);
                                    $prev = "none";
                                }elseif($prev == "array"){
                                    //tools_dumpx("value_source3: ",__FILE__,__LINE__,$value_source);
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($value_source as $sovObj) {
                                        if(!in_array($sovObj["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sovObj["id"]);
                                            //tools_dumpx("sovObj3: ",__FILE__,__LINE__,$sovObj);
                                            $answer_value = $sovObj[$index_path_part];
                                            //tools_dumpx("sovObjAnswer3: ",__FILE__,__LINE__,$answer_value);
                                            //this answer becomes an index
                                            if(array_key_exists($answer_value,$indexed) == false){
                                                $indexed[$answer_value] = array();
                                            }
                                            array_push($indexed[$answer_value],$sovObj);
                                        }
                                    }
                                    $prev = "none";
                                }
                                $prev = "none";
                                continue;
                            }

                            //traversing loop
                            if($singular_index == $index_path_part){
                                //this node is an object
                                if($prev == "none"){
                                    //our travel has just began
                                    //the value source contains an object at this location
                                    $temp_array = $value_source[$index_path_part];
                                    //that object is the root to our values
                                    $value_source = $temp_array;
                                    $prev = "object";
                                }elseif($prev == "object"){
                                    //the current ref is an object which has an object at this location
                                    //that object is the root to sov
                                    $temp_array = $value_source[$index_path_part];
                                    //that object is the root to our values
                                    $value_source = $temp_array;
                                    $prev = "object";
                                }elseif($prev == "array"){
                                    //the refrence is an array of objects
                                    //each object contains an object at this location as the source
                                    //of values sov
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($value_source as $sovHolder) {
                                        $sov = $sovHolder[$index_path_part];
                                        if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sov["id"]);
                                            array_push($temp_sov,$sov);
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }
                            }else{
                                //this node is an array
                                if($prev == "none"){
                                    //we are just beginig our travel
                                    //the value source contains an array at this location
                                    $temp_array = $value_source[$index_path_part];
                                    //every object in this array is a potential source of the value
                                    //we are looking for
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($temp_array as $sov) {
                                        if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sov["id"]);
                                            array_push($temp_sov,$sov);
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }elseif($prev == "object"){
                                    //the refrence is an object and contains an array at this location
                                    //which has objects that are to become the sov
                                    $temp_array = $value_source[$index_path_part];
                                    //every object in this array is a potential source of the value
                                    //we are looking for
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($temp_array as $sov) {
                                        if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                            array_push($temp_sov_keys,$sov["id"]);
                                            array_push($temp_sov,$sov);
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }elseif($prev == "array"){
                                    //the refrence is an array of ojects and each object
                                    //contains an array at this location with a batch of 
                                    //child objects that are to be the sov
                                    $temp_sov = array();
                                    $temp_sov_keys = array(); //we dont need duplicates
                                    foreach ($value_source as $sovHolder) {
                                        $temp_array = $sovHolder[$index_path_part];
                                        //every object in this array is a potential source of the value
                                        //we are looking for
                                        foreach ($temp_array as $sov) {
                                            if(!array_key_exists($sov["id"],$temp_sov_keys)){
                                                array_push($temp_sov_keys,$sov["id"]);
                                                array_push($temp_sov,$sov);
                                            }
                                        }
                                    }
                                    //the refrence now becomes the new source of truth
                                    $value_source = $temp_sov;
                                    $prev = "array";//now out sov is an array of objects
                                }
                            }
                        }
                        
                        if($is_keep == false){
                            unset($res[BEE_RI][$indexing_key]);//delete previous honey at this node
                        }
                        $res[BEE_RI][$indexing_key_final_name] = $indexed;
                        
                    }
                }
            }//end if _nxtva_

            if($valueXt == "_tree_sum_"){
                //process tree sum
                if(!empty($tree_sums)){
                    //tools_dumpx("empty(tree_sums): ",__FILE__,__LINE__,$res);
                    foreach ($tree_sums as $tree_sum_key => $tree_sum_path) {
                        $tree_sum_path_parts = explode("...",$tree_sum_path);
                        $as = $tree_sum_path_parts[1];
                        $from = $tree_sum_path_parts[0];
                        $root_comb_namex = $tree_sum_key;
                        $current_results = $res[BEE_RI][$tree_sum_key];
                        $sum_comb_name  = Inflect::singularize($root_comb_namex);
                        $new_current_results = tools_tree_sum($current_results,$from,$as,$sum_comb_name);
                        //get all the indexes that are 
                        //tools_dumpx("current_results: ",__FILE__,__LINE__,[$current_results,new_current_results]);
                        $res[BEE_RI]["_tree_sum_".$root_comb_namex] = $new_current_results;
                        //tools_dump("_tree_sum_z: ",__FILE__,__LINE__,$new_current_results);
                    }
                }
            }//end if _nxtva_

            // if($valueXt == "_???_"){
            // }//end if _??_ this is a place holder for future use
        }//end foreach ($xt_order as $valueXt) {
        
        
        
        
    }
    return $res;
}








function bee_hive_update($nectoroid,$structure,$connection,$user_id,$prev_res=array(),$current_raw_honey=null){
    $res = array(null,array(),$structure);
    $bsp_res = bee_segmentation_update($nectoroid,$structure,$connection,$user_id,$current_raw_honey);
    //tools_dumpx("bsp_res: ",__FILE__,__LINE__,$bsp_res);
    $tree = hive_after_segmentation_update($bsp_res,$structure,$connection,$prev_res,$current_raw_honey);
    $res[BEE_RI] = $tree[BEE_RI];
    $res[BEE_EI] = array_merge($res[BEE_EI],$tree[BEE_EI]);
    return $res; 
}

function hive_after_segmentation_update($segmentation_run_res,$structure,$connection,$prev_res=array(),$current_raw_honey=null){
    $res = array(null,array(),$structure);
    $sr_res = $segmentation_run_res;
    //tools_dump("hive segmentation results",__FILE__,__LINE__,$sr_res[BEE_RI]);    $res[BEE_EI] = array_merge($res[BEE_EI],$sr_res[BEE_EI]);
    $res[2] = $sr_res[2];//the structure
    if(count($sr_res[BEE_EI]) == 0){//when we dont have any errors
        //convert these queries into raw honey
        $pu_res = production_update($sr_res[BEE_RI],$connection,$prev_res,$current_raw_honey);
        //tools_dumpx("@3 production_update res: ",__FILE__,__LINE__,$pu_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$pu_res[BEE_EI]);
        if(count($pu_res[BEE_EI]) == 0){//when we dont have any errors
            $pu_res = packaging_update($pu_res[BEE_RI],$structure,$connection);
            $res[BEE_EI] = array_merge($res[BEE_EI],$pu_res[BEE_EI]);
            $res[BEE_RI] = $pu_res[BEE_RI];
        }
    }
    return $res;
}

function bee_hive_post($nectoroid,$structure,$connection,$user_id,$prev_res=array()){
    $res = array(null,array(),$structure);
    $bsp_res = bee_segmentation_post($nectoroid,$structure,$connection,$user_id);
    //tools_dumpx("bsp_res: ",__FILE__,__LINE__,$bsp_res);
    $tree = hive_after_segmentation_post($bsp_res,$structure,$connection,$prev_res);
    $res[BEE_RI] = $tree[BEE_RI];
    $res[BEE_EI] = array_merge($res[BEE_EI],$tree[BEE_EI]);
    return $res; 
}

function hive_after_segmentation_post($segmentation_run_res,$structure,$connection,$prev_res=array()){
    $res = array(null,array(),$structure);
    $sr_res = $segmentation_run_res;
    //tools_dump("hive segmentation results",__FILE__,__LINE__,$sr_res[BEE_RI]);
    $res[BEE_EI] = array_merge($res[BEE_EI],$sr_res[BEE_EI]);
    $res[2] = $sr_res[2];//the structure
    if(count($sr_res[BEE_EI]) == 0){//when we dont have any errors
        //convert these queries into raw honey
        $pr_res = production_post($sr_res[BEE_RI],$connection,$prev_res);
        //tools_dump("@3 production_post res: ",__FILE__,__LINE__,$pr_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
        if(count($pr_res[BEE_EI]) == 0){//when we dont have any errors
            $pr_res = packaging_post($pr_res[BEE_RI],$structure,$connection);
            $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
            $res[BEE_RI] = $pr_res[BEE_RI];
        }
    }
    return $res;
}



function bee_hive_run_uploads($bee){
    $res = array(null,array());
    $file_honey = array();
    //you cannot upload files to the server if you have not yet 
    //logged in
    if($bee["BEE_USER"]["id"] != 0){
        $hn = $bee["BEE_APP_NAME"];
        $path_to_temp_dir = "bee/temp_uploads/uf_" . $hn . "_" . $bee["BEE_USER"]["id"]."/";
        if (!file_exists($path_to_temp_dir)) {
            mkdir($path_to_temp_dir, 0777, true);
        }
        //check that this user has a folder under uploads
        //upload_files_with_post_data
        $target_dir = $path_to_temp_dir;
        foreach ($_FILES as $file_key => $file_value) {
            $target_file = $target_dir . basename($file_value["name"]);
            $uploadOk = 1;
            $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
            $is_an_image = true;
            $file_exists = false;
            $file_size = 10485760; //10mb 
            $file_is_too_large = false;
            $file_type_is_right = true;
            $allowed_file_types = array("jpg","png","jpeg","gif","pdf","txt","mp3","mp4","xlsx");
            //nyd
            //check the  _hive.json to validate the uploaded file using the 
            //parameters below
            // Check if image file is a actual image or fake image
            $check = getimagesize($file_value["tmp_name"]);
            $errMsg = "";
            if($check !== false) {
                $is_an_image = true;
            } else {
                $is_an_image = false;
            }
            // Check if file already exists
            if (file_exists($target_file)) {
                $file_exists = true;
                $uploadOk = 0;
                $errMsg = " File alreay exists";
            }
            // Check file size
            if ($file_value["size"] > $file_size) {
                $file_is_too_large = true;
                $uploadOk = 0;
                $errMsg = " File is too big, allowed size is 10 MB";
            }
            // Allow certain file formats
            if(!in_array($imageFileType,$allowed_file_types)) {
                $file_type_is_right = false;
                $uploadOk = 0;
                $errMsg = " FIle type is not allowed";
            }
            // Check if $uploadOk is set to 0 by an error
            if ($uploadOk == 0) {
                array_push($res[BEE_EI],"Sorry, your file was not uploaded." . $errMsg);
            } else {
                if (move_uploaded_file($file_value["tmp_name"], $target_file)) {
                    $file_honey[$file_key] = $target_file;
                } else {
                    array_push($res[BEE_EI],"Sorry, there was an error uploading your file.");
                }
            }
        }
    }else{
        array_push($res[BEE_EI],"You are not authorised to access this resource");
    }
    $res[BEE_RI] = $file_honey;
    return $res;
}

function bee_hive_delete($nectoroid,$structure,$connection,$user_id,$is_restricted=false){
    $res = array(null,array(),$structure);
    $bsp_res = bee_segmentation_delete($nectoroid,$structure,$connection,$user_id,$is_restricted);
    //tools_dumpx("bsp_res: ",__FILE__,__LINE__,$bsp_res[BEE_RI]);
    $tree = hive_after_segmentation_delete($bsp_res,$structure,$connection,$is_restricted);
    $res[BEE_RI] = $tree[BEE_RI];
    $res[BEE_EI] = array_merge($res[BEE_EI],$tree[BEE_EI]);
    return $res; 
}

function hive_after_segmentation_delete($segmentation_run_res,$structure,$connection,$is_restricted=false){
    //tools_dumpx("@3 structure res: ",__FILE__,__LINE__,$structure);
    $res = array(null,array(),$structure);
    $sr_res = $segmentation_run_res;
    //tools_dump("hive segmentation results",__FILE__,__LINE__,$sr_res[BEE_RI]);
    $res[BEE_EI] = array_merge($res[BEE_EI],$sr_res[BEE_EI]);
    $res[2] = $sr_res[2];//the structure
    if(count($sr_res[BEE_EI]) == 0){//when we dont have any errors
        //convert these queries into raw honey
        $pr_res = production_delete($sr_res[BEE_RI],$connection,$is_restricted);
        //tools_dump("@3 production_post res: ",__FILE__,__LINE__,$pr_res);
        $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
        if(count($pr_res[BEE_EI]) == 0){//when we dont have any errors
            $pr_res = packaging_delete($pr_res[BEE_RI],$structure,$connection);
            $res[BEE_EI] = array_merge($res[BEE_EI],$pr_res[BEE_EI]);
            $res[BEE_RI] = $pr_res[BEE_RI];
        }
    }
    return $res;
}


?>