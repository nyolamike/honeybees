<?php
function bee_run_handle_get($res,$bee,$querydata=null){
    global $BEE_GLOBALS;
    global $countries_list;
    global $bee_show_sql;
    global $xt_order;
    
    if(isset($_GET["q"]) && $querydata == null){
        $query_base64 = $_GET["q"];
        //tools_dumpx("_GET",__FILE__,__LINE__,$query_base64);
        $query = base64_decode($query_base64);
        //tools_dumpx("query",__FILE__,__LINE__,$query);
        $tsji_res = tools_suck_json_into($query, array());
        $res[BEE_EI] = array_merge($tsji_res[BEE_EI],$res[BEE_EI]);
        if(count($res[BEE_EI])==0){//no errors
            $querydata = $tsji_res[BEE_RI];
        }
    }
    
    if($querydata == null){
        //there is nothing to process
        array_push($res[BEE_EI],"Missing query parameter q in the url");
    }else{
        //check if we can return all the sqls
        if(array_key_exists("_sql",$querydata)){
            $bee_show_sql = true;
        }

        //extraction order
        $xt_order = array();
        foreach ($querydata as $keyF => $valueF) {
            if(tools_startsWith($keyF, "_xtu_")){
                array_push($xt_order,"xtu");
            }
            if(tools_startsWith($keyF, "_nxtva_")){
                array_push($xt_order,"_nxtva_");
            }
            if(tools_startsWith($keyF, "_index_")){
                array_push($xt_order,"_index_");
            }
            if(tools_startsWith($keyF, "_indexk_")){
                array_push($xt_order,"_indexk_");
            }
            if(tools_startsWith($keyF, "_tree_sum_")){
                array_push($xt_order,"_tree_sum_");
            }
        }

        //get system modules
        if(array_key_exists("_f_modules",$querydata)){
            $res[BEE_RI] = bee_security_modules($bee);
        }elseif(array_key_exists("_f_permissions",$querydata)){
            $res[BEE_RI] =  bee_security_permissions($bee);
        }elseif(array_key_exists("_f_countries",$querydata)){
            $res[BEE_RI] = array(
                "countries" => $countries_list
            );
        }elseif(array_key_exists("_f_now",$querydata)){
            $res[BEE_RI] = array(
                "now" => time()
            );
        }elseif(array_key_exists("_f_bee",$querydata)){
            $res[BEE_RI] =  array(
                "bee" => array(
                    "date" => date("Y-m-d") . "@" . BEE_GARDEN
                )
            );
        }elseif(array_key_exists("_f_xlsx",$querydata)){ //reading excell files
            //you must be logged in to access this
            if($bee["BEE_USER"]["id"] != 0){
                //this reads data from an excell file
                $resRead = array(
                    "_f_xlsx" => array(
                        "_from" => array()
                    )
                );
                //if _file_ part contains bee/temp_uploads/uf_ then it is uploaded first
                //else we assume that that is the file name
                $filePath = "";
                $source= $querydata["_f_xlsx"]["_from"];
                foreach($source as $sourceItemKey => $sourceItemObject) {
                    $resRead["_f_xlsx"]["_from"] = array();
                    $resRead["_f_xlsx"]["_from"][$sourceItemKey] = null;
                    foreach($sourceItemObject as $sourceItemObjectKey => $sourceItemObjectObject) {
                        $resRead["_f_xlsx"]["_from"][$sourceItemKey] = array();
                        $resRead["_f_xlsx"]["_from"][$sourceItemKey][$sourceItemObjectKey] = null;
                        if(tools_startsWith($sourceItemObjectKey,"_file_")){
                            $fileNameFromClient = $sourceItemObjectObject;
                            if(tools_startsWith($fileNameFromClient,"bee/temp_uploads/uf_")){ 
                                //this file needs to be uploaded
                                $section_name = $sourceItemObjectKey;
                                $value = $fileNameFromClient;
                                $comb_name = $sourceItemKey;
                                $structure = $bee["BEE_HIVE_STRUCTURE"]["combs"];
                                $bsefv_res = bee_segmentation_evaluate_file_value($section_name,$value,$comb_name,$structure);
                                if(count($bsefv_res[BEE_EI])==0){
                                    $res_section_name = $bsefv_res[BEE_RI][0];
                                    $filePath =  $bsefv_res[BEE_RI][1];
                                }else{
                                    $res[BEE_EI] = array_merge($res[BEE_EI],$bsefv_res[BEE_EI]); 
                                }
                            }else{
                                $filePath = $fileNameFromClient;
                            }
                        }
                        //now we need a path to this file
                        $target_file = $filePath; //uploads/batch_repayments/2019 01 police repyments.xlsx
                        if (file_exists($target_file)) {
                            //https://www.phpclasses.org/package/6279-PHP-Parse-and-retrieve-data-from-Excel-XLS-files.html
                            $xlsx = SimpleXLSX::parse($target_file);
                            if($xlsx){
                                $resRead["_f_xlsx"]["_from"][$sourceItemKey][$sourceItemObjectKey] =  $xlsx->rows();
                            }else {
                                $err = SimpleXLSX::parseError();
                                array_push($res[BEE_EI], $err);
                            }
                            
                        }else{
                            array_push($res[BEE_EI], "File : " . $target_file ." does not exist");
                        }
                    }
                }

                $res[BEE_RI] = $resRead;
                
            }else{
                array_push($res[BEE_EI],"You are not authorised to access this resource: reading server excell files");
            }
        }else{
            //authorise
            $bsv_res = bee_security_authorise(
                $bee["BEE_USER"],
                $querydata,
                $bee["BEE_HIVE_STRUCTURE"]["combs"],
                false, //create
                true, //read
                false, //update
                false //delete
            );
            $res[BEE_EI] = array_merge($res[BEE_EI],$bsv_res[BEE_EI]);
            if(count($res[BEE_EI])==0){//no errors
                //tools_dumpx("querydata",__FILE__,__LINE__,$querydata);
                $brp_res = bee_run_get($querydata,$bee["BEE_HIVE_STRUCTURE"]["combs"],$bee["BEE_HIVE_CONNECTION"]);
                //tools_dump("@a tracing null errors",__FILE__,__LINE__,$brp_res[BEE_EI]);
                $res[BEE_EI] = array_merge($res[BEE_EI],$brp_res[BEE_EI]);
                //tools_dump("tracing null errors ",__FILE__,__LINE__,$res[BEE_EI]);
                $res[BEE_RI] = $brp_res[BEE_RI];
            }
            //
        }  
    }
    return $res;
}
?>