<?php

    function tool_code($len=4){
        $min = 1000;
        $max = 9999;
        if(is_int($len)){
            $temp_min = "1";
            $temp_max = "9";
            for ($i=0; $i < $len-1; $i++) { 
                $temp_min .= "0";
                $temp_max .= "9";
            }
            $min = intval($temp_min);
            $max = intval($temp_max);
        }
        return strval(rand($min,$max));
    }

    function tools_chain($array_to_be_chained, $template = ""){
        if(is_array($array_to_be_chained) && count($array_to_be_chained) > 0){
            $str = "";
            for ($i=0; $i < count($array_to_be_chained); $i++) { 
                // _dump($array_to_be_chained[$i]);
                $str_temp = ($template != "")?(str_ireplace("{}",$array_to_be_chained[$i],$template)) : ($array_to_be_chained[$i]);
                    // _dump($str_temp);
                    $str = $str . " " . $str_temp . ",";
            }
            $str = trim($str);
            $str = substr($str,0,strlen($str)-1);
            return $str;
        }else if(is_array($array_to_be_chained) && count($array_to_be_chained) == 0){
            return $template;
        }else{
            return str_ireplace("{}",$array_to_be_chained,$template);
        }
    }

    function tools_dump($mark,$file,$line,$item,$k=null){
        $can_dump = true;
        if($k != null){
            $can_dump = false;
            if(is_array($item) && array_key_exists($k,$item)){
                $can_dump = true;
            }
        }

        if($can_dump){
            echo "<br/><br/>...............................<br/>Start: ".$mark." <br/>In: &nbsp;&nbsp;&nbsp;&nbsp;" . $file . " <br/>On: &nbsp;&nbsp;&nbsp;" . $line ."<br/>...............................<br/><pre><code>";
            var_dump($item);
            echo "</code></pre><br/>...............................<br/>End: ".$mark."<br/>...............................<br/><br/>";
        }
    }

    function tools_dumpx($mark,$file,$line,$item,$k=null){
        tools_dump($mark,$file,$line,$item,$k=null);
        exit(0);
    }

    //nyd
    //use indexing to make this logic faster
    function tools_tree_sum($current_results,$from,$as,$comb_name){
        for ($loopi=0; $loopi < count($current_results) ; $loopi++) { 
            $current_result = $current_results[$loopi];
            $myTotal = floatval($current_result[$from]);
            $parent_key_value = intval($current_result["id"]);
            //tools_dumpx("current_results",__FILE__,__LINE__,$current_results);
            $child_totals = tools_tree_sum_children($current_results,$from,$parent_key_value ,$comb_name);
            $current_result[$as] = $myTotal + $child_totals;
            $current_results[$loopi] = $current_result;
        }
        return $current_results;
    }

    function tools_tree_sum_children($current_results,$from,$parent_key_value,$comb_name){
        $child_totals  = 0;
        for($lpJ=0; $lpJ < count($current_results) ; $lpJ++) {
            $innerResult = $current_results[$lpJ];
            $fk_key_value = intval($innerResult[$comb_name . "_id"]);
            if($fk_key_value == $parent_key_value){//this is a child
                $childValue = floatval($innerResult[$from]);
                $parent_key_value_child = $innerResult["id"];
                $childInnerInnerTotal = tools_tree_sum_children($current_results,$from,$parent_key_value_child,$comb_name);
                $child_totals = $child_totals + $childValue  + $childInnerInnerTotal;
            }
        }
        return $child_totals;
    }

    //https://stackoverflow.com/questions/5696412/how-to-get-a-substring-between-two-strings-in-php
    /*
        $str = 'before-str-after';
        if (preg_match('/before-(.*?)-after/', $str, $match) == 1) {
            echo $match[1];
        }
    */
    function tools_get_in_between_strings($start, $end, $str){
        $matches = array();
        $regex = "/$start(.*?)$end/";//[a-zA-Z0-9_]*
        preg_match_all($regex, $str, $matches);
        return $matches[1];
    }

    //adopted from
    //https://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
    function tools_startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    function tools_get_app_folder_name(){
        $parts = explode(DIRECTORY_SEPARATOR,__DIR__);
        $folder_name = strtolower(((count($parts)>1)?$parts[count($parts)-2]:$parts[1]));
        return $folder_name;
    }

    function tools_jsonify($json){
        $temp = null;
        if(is_string($json)){
            $temp = json_decode($json, true);
        }else{
            $temp = json_encode($json);
        }
        $error = json_last_error();
        $msg = "";
        switch ($error) {
             case JSON_ERROR_NONE:
                $msg = "";
                break;
             break;
             case JSON_ERROR_DEPTH:
                $msg = "JSON_ERROR_DEPTH - Maximum stack depth exceeded";
                break;
             break;
             case JSON_ERROR_STATE_MISMATCH:
                $msg = "JSON_ERROR_STATE_MISMATCH - Underflow or the modes mismatch";
                break;
             break;
             case JSON_ERROR_CTRL_CHAR:
                $msg = "JSON_ERROR_CTRL_CHAR - Unexpected control character found";
                break;
             break;
             case JSON_ERROR_SYNTAX:
                $msg = "JSON_ERROR_SYNTAX - Syntax error, malformed JSON";
                //var_dump($json);
                break;
             break;
             case JSON_ERROR_UTF8:
                $msg = "JSON_ERROR_UTF8 - Malformed UTF-8 characters, possibly incorrectly encoded";
                break;
             break;
             default:
                $msg = "JSON - Unknown error";
                break;
             break;
        }
        return array($temp,(strlen($msg)>0?array($msg):array()));
    }

    function tools_suck_json_into($json_string, $array_to_be_filled){
        if (strlen($json_string) > 0) {
            $temp_e = tools_jsonify($json_string);
            //tools_dumpx("temp_e",__FILE__,__LINE__,$temp_e);
            if(count($temp_e[BEE_EI]) == 0){//no errors
                $temp = $temp_e[0];
                foreach ($temp as $prop => $value) {
                    $array_to_be_filled[$prop] = $value;
                }
                return array($array_to_be_filled,array());
            }else{
                return array(null,$temp_e[BEE_EI]);
            }
        }else{
            return array(null,array("No json string provided to the sucking process"));
        }
    }
    
    function tools_sanitise_name($val){
        //nyd
        //take out any special character that is not alpha numeric
        $str = preg_replace("/[^A-Za-z0-9 ]/", '', strval($val));
        $str = strtolower(str_replace(" ", "_", $str));
        return $str;
    }

    function tools_respond($data,$errors){
        global $conn;
        global $processes_route;
        global $foo;
        $processes_route = TRUE;
        $conn = null;
        //The response
        $data["errors"] = $errors;
        $data["foo"] = $foo;
        
		//include the header 
        header("Content-type: application/json"); 
        $res =  json_encode($data);
		echo $res;
		exit(0);
    }

    function tools_pack($data,$errors){
        //The response
        $data["_errors"] = ($errors == null)?array(): $errors;
        if($errors == null && is_array($errors) == false){
            $data["_comment"] = "Errors array was actually null, probably something went wrong internally";
        }
        return $data;
    }

    function tools_reply($data,$errors,$connections){
        global $bee_show_sql;
        global $bee_sql_backet;

        //close all connnections
        foreach ($connections as $conn) {
            $conn = null;
        }
        //The response
        $data["_errors"] = ($errors == null)?array(): $errors;
        if($errors == null && is_array($errors) == false){
            $data["_comment"] = "Errors array was actually null, probably something went wrong internally";
        }

        if($bee_show_sql == true){
            $data["_sql"] = $bee_sql_backet;
        }
        
		//include the header 
        header("Content-type: application/json"); 
        $res =  json_encode($data);
        echo $res;
		exit(0);
    }


    

    //
    function tools_exists($haystack,$haystack_key,$niddle_key,$niddle_vlue){
        $list = $haystack[$haystack_key];
        foreach ($list as $index => $obj) {
            if($obj[$niddle_key] == $niddle_vlue ){
                return true;
            }
        }    
        return false;
    }
?>