<?php
function bee_hive_run_login($post_nectoroid,$bee){
    global $BEE_GLOBALS;
    global $countries_list;
    $res = array(null, array(),$bee);

    //nyd 
    //validation

    //hash the password
    $raw_password = $post_nectoroid["_f_login"]["password"];
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    $email = $post_nectoroid["_f_login"]["email"];
    $app_name = ""; //$post_nectoroid["_f_login"]["app_name"];
    $hive_id = 0;
    $user_id = 0;

    $found = false;
    $found_hive = null;
    foreach ($bee["BEE_GARDEN"]["hives"] as $hive_index => $hive_obj) {
        $stop = false;
        foreach ($hive_obj["hive_users"] as $hive_user_index => $hive_user_obj) {
            if($hive_user_obj["email"] == $email){
                //tools_dumpx("hive_name: ",__FILE__,__LINE__,$hive_user_obj);
                if (password_verify($raw_password, $hive_user_obj["password"])) {
                    $app_name = $hive_user_obj["hive_name"];
                    $found_hive = $hive_obj;
                    $hive_id = $found_hive["id"];
                    $user_id = $hive_user_obj["user_id"];
                    $found = true;
                    $stop = true;
                    break;
                }
            }
        }
        if($stop == true){
            break;
        }
    }  
    if($found == false){
        $res[BEE_EI] = array("Business account not found");
        return $res;
    }
    

    //check if hive exists
    //we only do this if we have an open hive reistrtion policy
    $hive_name = $app_name; //BEE_GARDEN . "_" . tools_sanitise_name($app_name);
    if($bee["BEE_HIVE_STRUCTURE"]["is_registration_public"] == true){
        //tools_dumpx("hive_name: ",__FILE__,__LINE__,$hive_name);
        $hive_exists = tools_exists($bee["BEE_GARDEN"],"hives","hive_name",$hive_name);
        if(!$hive_exists){
            $res[BEE_EI] = array("Unknown application name " . $app_name);
            return $res;
        }
        //we would then get a connection to this hive
        $hrgc_res = hive_run_get_connection(BEE_USER_NAME, BEE_PASSWORD,BEE_SERVER_NAME,$hive_name,false);
        $res[BEE_EI] = array_merge($res[BEE_EI],$hrgc_res[BEE_EI]);
        $connection = $hrgc_res[BEE_RI];
        $bee["BEE_HIVE_CONNECTION"] = $connection;
    }

    $hive_exists = tools_exists($bee["BEE_GARDEN"],"hives","hive_name",$hive_name);
    if(!$hive_exists){
        $res[BEE_EI] = array("Unknown business application name " . $app_name);
        return $res;
    }

    //select user with these things
    $user_nector = array(
        "users" => array(
            "_w" => array(
                array(
                    array(
                        array("email","=",$email),
                        "AND",
                        array("status","=","active")
                    ),
                    "AND",
                    array("id","=",$user_id)
                )
            ),
            "user_roles" => array(
                "role" => array(
                    "role_permisiions" => array(),
                    "role_modules" => array()
                )
            )
        )
    );
    $brg_res = bee_run_get($user_nector,$bee["BEE_HIVE_STRUCTURE"]["combs"],$bee["BEE_HIVE_CONNECTION"]);
    $res[BEE_EI] = array_merge($res[BEE_EI],$brg_res[BEE_EI]);
    if(count($brg_res[BEE_EI])==0){
        $users = $brg_res[BEE_RI]["users"];
        if(count($users)==0){
            array_push($res[BEE_EI],"Account not found or is not activated");
        }else{
            $foundUser = null;
            foreach ($users as $user) {
                if(password_verify($raw_password, $user["password"])) {
                    $foundUser = $user;
                    break;
                }
            }
            if($foundUser == null){
                array_push($res[BEE_EI],"Incorrect email or password");
            }else{
                //generate a token for this user
                $token = new Emarref\Jwt\Token();

                // Standard claims are supported
                $token->addClaim(new Emarref\Jwt\Claim\Audience(['audience_1', 'audience_2']));
                $token->addClaim(new Emarref\Jwt\Claim\Expiration(new \DateTime('1440 minutes'))); //a day
                $token->addClaim(new Emarref\Jwt\Claim\IssuedAt(new \DateTime('now')));
                $token->addClaim(new Emarref\Jwt\Claim\Issuer('your_issuer'));
                $token->addClaim(new Emarref\Jwt\Claim\JwtId('qwerty'));
                $token->addClaim(new Emarref\Jwt\Claim\NotBefore(new \DateTime('now')));
                $token->addClaim(new Emarref\Jwt\Claim\Subject('api'));

                unset($foundUser["password"]);
                //tools_reply($foundUser,array(),array());
                
                // Custom claims are supported
                $token->addClaim(new Emarref\Jwt\Claim\PublicClaim('user', $foundUser["id"]));
                $token->addClaim(new Emarref\Jwt\Claim\PublicClaim('app_name',$app_name));
                $token->addClaim(new Emarref\Jwt\Claim\PublicClaim('app_id',$hive_id));
                
                //nyd
                //add roles etc
                $jwt = new Emarref\Jwt\Jwt();
                $ect = $bee["BEE_JWT_ENCRYPTION"];
                $serializedToken = $jwt->serialize($token, $ect);

                $res[BEE_RI] = array(
                    "_f_login" => array(
                        "token" => $serializedToken,
                        "user" => $foundUser
                    )
                );
            }
        }
    }
    return $res;
}
?>