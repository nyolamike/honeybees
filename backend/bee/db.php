<?php

function db_get_connection($username, $password,$servername="localhost",$databasename="",$is_test=false){
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
function db_run($run_config){
    $sql = $run_config["sql"];
    $db_show_sql_on_error  = (isset($run_config["show_sql_on_errors"]))? $run_config["show_sql_on_errors"] : SHOW_SQL_ON_ERRORS;
    $conn = $run_config["conn"];
    $db_response = array(
        array(
            "db_res" => null,
            "id" => 0, 
            "data" => array()
        ),
        array()
    );
    try{  
        if(tools_startsWith($sql,"INSERT") == TRUE){
            $res = $conn->exec($sql);
            //get the last insert id
            $liid = $$conn->lastInsertId();
            $db_response[0]["id"] = $liid;
            $db_response[0]["db_res"] = $res;
        }else if(tools_startsWith($sql,"SELECT") == TRUE){
            $res = $conn->query($sql);
            $data = array();
            foreach ($res as $row) {
                array_push($data, $row);
            }
            $db_response[0]["db_res"] = $res;
            $db_response[0]["data"] = $data;
        }else if(tools_startsWith($sql,"UPDATE") == TRUE){
            $res = $conn->exec($sql);
            //nyd
            //get the number of affected records
            $db_response[0]["num"] = 1;
            $db_response[0]["db_res"] = $res;
        }else{
            $res = $conn->exec($sql);
            $db_response[0]["db_res"] = $res;
        }
    }catch(PDOException $e){
        $msg = $e->getMessage() . " " . (($db_show_sql_on_error == TRUE)? $sql : "");
        array_push($db_response[1], $msg);
    }catch(Exception $e){
        $msg = $e->getMessage() . " " . (($db_show_sql_on_error == TRUE)? $sql : "");
        array_push($db_response[1], $msg);
    }
    return $db_response;
}

function db_create_db($conn,$hive_name,$show_errors){
    $config = array(
        "sql" => "CREATE DATABASE IF NOT EXISTS " . $hive_name,
        "conn" => $conn,
        "show_sql_on_errors" => $show_errors
    );
    $dear_db = db_run($config);
    return $dear_db;
}

    function db_inn($col_name,$num){ return tools_chain($col_name, "`{}` int(".$num.") NOT NULL"); }

    function db_fk($col_name){ return tools_chain($col_name, "`{}` int(30) NOT NULL"); }

    function db_inn_d($col_name,$num, $default){ 
        return tools_chain($col_name, "`{}` int(".$num.") NOT NULL DEFAULT '". $default. "'");
    }

    function db_in($col_name,$num){ return tools_chain($col_name, "`{}` int(".$num.") NULL "); }

    function db_dnn($col_name){ return tools_chain($col_name, "`{}` DOUBLE NOT NULL DEFAULT '0.0' "); }

    function db_vcnn($col_name,$num){ return tools_chain($col_name, "`{}` varchar(".$num.") NOT NULL"); }

    function db_vcn($col_name,$num){ return tools_chain($col_name, "`{}` varchar(".$num.") NULL"); }

    function db_vcnn_d($col_name,$num,$default){ 
        return tools_chain($col_name, "`{}` varchar(".$num.") NOT NULL DEFAULT '".$default."' "); 
    }

    function db_tn($col_name){ return tools_chain($col_name, "`{}` text NULL"); }

    function db_tnn($col_name){ return tools_chain($col_name, "`{}` text NOT NULL"); }

    function db_tnn_d($col_name,$default){ return tools_chain($col_name, "`{}` text NOT NULL DEFAULT '".$default."' "); }

    function db_tsnn($col_name){ return tools_chain($col_name, "`{}` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP "); }

    function db_dtnn($col_name){ return tools_chain($col_name, "`{}`  DATE NOT NULL "); }

function db_default_column_def(){
    return array("vcn",100);
}





function db_sql_ct($table_name,$columns){
    $sql = "CREATE TABLE IF NOT EXISTS `" . $table_name . "` (";
    $sql = $sql . " `id` int(30) NOT NULL AUTO_INCREMENT,";
    for ($i=0; $i < count($columns); $i++) { 
        $sql = $sql . " " . $columns[$i] . ",";
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

function db_ct($conn,$table_name, $columns, $show_errors){
    $config = array(
        "sql" => db_sql_ct($table_name,$columns)[0],
        "conn" => $conn,
        "show_sql_on_errors" => $show_errors
    );
    $dear_db = db_run($config);
    return $dear_db;
}

//db add column
function db_ac($conn,$table_name, $colm_sql){
    $config = array(
        "sql" => "ALTER ".$table_name." ADD " . $colm_sql,
        "conn" => $conn,
        "show_sql_on_errors" => SHOW_SQL_ON_ERRORS
    );
    $dear_db = db_run($config);
    return $dear_db;
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


?>