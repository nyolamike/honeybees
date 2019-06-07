<?php
    function bee_security_modules($bee){
        $whole_honey = array(
            "modules" => $bee["BEE_GARDEN_STRUCTURE"]["_modules"]
        );
        return $whole_honey;
    }

    function bee_security_permissions($bee){
        $combs = $bee["BEE_HIVE_STRUCTURE"]["combs"];
        $whole_honey = array(
            "permissions" => array()
        );
        
        foreach ($combs as $combs_name => $combs_def) {
            if(tools_startsWith($combs_name,"_")){
                continue;
            }
            $plural_name = Inflect::pluralize($combs_name);
            $perm = array(
                "name" => $combs_name,
                "plural_name" => $plural_name,
            );
            array_push($whole_honey["permissions"],$perm);
        }
        return $whole_honey;
    }

    function bee_security_extract_targets($found,$node,$hive_combs){
        foreach ($node as $node_key => $node_value) {
            if(tools_startsWith($node_key,"_")){
                //nyd
                //validate _fx_ nodes
                //validate _xtu_ nodes
                //and others
                continue;
            }
            $keysingle = Inflect::singularize($node_key);
            //whats validated must be in the hive combs
            if(array_key_exists($keysingle,$hive_combs) && !array_key_exists($keysingle,$found)){
                array_push($found,$keysingle);
            }
            $found = bee_security_extract_targets($found,$node_value,$hive_combs);
        }
        return $found;
    }

    function bee_security_authorise($token_user,$nectoroid,$hive_combs,$can_create=false,$can_read=false,$can_update=false,$can_delete=false){
        $res = array(true,array());
        if(BEE_DRONE_SECURITY_ENABLED == false){//just skip authentication layer
            return $res;
        }
        if(!array_key_exists("user_roles",$token_user)){
            array_push($res[BEE_EI],"Processing authorisation failed probably you are missing an authentication header");
            return $res;
        }
        //tools_dumpx("token_user",__FILE__,__LINE__,$token_user);
        $user_roles = $token_user["user_roles"];
        $nector_combs = bee_security_extract_targets(array(),$nectoroid,$hive_combs);
        //tools_dumpx("nector_combs security",__FILE__,__LINE__,$nector_combs);
        $user_perms = array();
        foreach ($user_roles as $ind => $user_role) {
            if(array_key_exists("role",$user_role)){
                $role = $user_role["role"];
                if(array_key_exists("role_permisiions",$role)){
                    $perms = $role["role_permisiions"];
                    foreach ($perms as $pi => $perm) {
                        $nem = $perm["permission"];
                        if(!array_key_exists($nem,$user_perms)){
                            $user_perms[$nem] = array(0,0,0,0);
                        }
                        $pcc = intval($perm["can_create"]);
                        $pcr = intval($perm["can_read"]);
                        $pcu = intval($perm["can_update"]);
                        $pcd = intval($perm["can_delete"]);
                        $user_perms[$nem][0] = ($pcc>0)?$pcc:0;
                        $user_perms[$nem][1] = ($pcr>0)?$pcr:0;
                        $user_perms[$nem][2] = ($pcu>0)?$pcu:0;
                        $user_perms[$nem][3] = ($pcd>0)?$pcd:0;
                    }
                }
            }
        }
        //all the $nector_combs must pass
        $str = json_encode($nectoroid);
        $failed_keys = array();
        foreach ($nector_combs as $nector_comb) {
            if(array_key_exists($nector_comb,$user_perms) && !in_array($nector_comb,$failed_keys)){
                $p = $user_perms[$nector_comb];
                if($can_create == true && $p[0] == 0){
                    array_push($res[BEE_EI],"Not authorised to create this resource " . $nector_comb . " in " . $str);
                    array_push($failed_keys,$nector_comb);
                }
                if($can_read == true && $p[1] == 0){
                    array_push($res[BEE_EI],"Not authorised to read from this resource " . $nector_comb . " in " . $str);
                    array_push($failed_keys,$nector_comb);
                }
                if($can_update == true && $p[2] == 0){
                    array_push($res[BEE_EI],"Not authorised to edit this resource " . $nector_comb . " in " . $str);
                    array_push($failed_keys,$nector_comb);
                }
                if($can_delete == true && $p[3] == 0){
                    array_push($res[BEE_EI],"Not authorised to delete this resource " . $nector_comb . " in " . $str);
                    array_push($failed_keys,$nector_comb);
                }
            }elseif(!in_array($nector_comb,$failed_keys)){
                array_push($res[BEE_EI],"Not authorised to access this resource " . $nector_comb . " in " . $str);
                array_push($failed_keys,$nector_comb);
            }
        }
        if(count($res[BEE_EI])>0){
            $res[BEE_RI] = false;
        }
        return $res;
    }
?>