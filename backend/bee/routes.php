<?php
    

    $claims = null;
    $token_timeout_in_seconds = 1000000;

    #get the length of base_uri
    $base_uri_length = strlen($base_uri);
    $requested_route = trim(trim(strtolower(substr($request_uri,$base_uri_length))),"/");

    //var_dump($_SERVER);

    //initialise the api backend
    if($requested_route == "setup"){
        $foo = db_create_nnetego_db();
        _respond($foo,array());
    }

    //db
    if($requested_route == "db"){
        //$foo = db_create_hotel_db("queen");
       // _dump($foo);
        // $we = db_seed_db("queen");
        // _dump($we);

        //$foo = db_count("queen","item");
        //_dump($foo[0]);

        $foo = db_get("queen","unit","id name", " id > 1");
        //_dump($foo[0]);

        _respond($foo[0],$foo[1]);
    }

    //get all paymeny types
    if($requested_route == "paymenttypes"){ 
        _respond(payment_types_all(),array());
    } 

    //get all countries
    if($requested_route == "countries"){ 
        _respond(countries_get_all(),array());
    } 

	//csharp code for countries
	if($requested_route == "countries/csharp"){ 
        _respond(countries_to_csharp_data(),array());
    } 

    //get a list of special needs
    if($requested_route == "specialneeds" && $method == "get"){ 
        _respond(special_needes_all(),array());
    }

    //register a client
    if($requested_route == "register" && $method == "post"){
        $results = register_client_and_hotel($postdata);
        _respond($results["data"],$results["errors"]);
    } 

    //activate a client
    if($requested_route == "activate" && $method == "post"){ 
        $results = activate_client($postdata);
        _respond($results["data"],$results["errors"]);
    }

    //login a user
    if($requested_route == "login" && $method == "post"){ 
        $results = login_client($postdata);  //login_client($postdata);
        _respond($results["data"],$results["errors"]);
    }

    //ask for modules to install
    if($requested_route == "modules/install" && $method == "get"){ 
        $res = login_authorise($requested_route,$method,array(BUY_MODULES));
        if(count($res["errors"]) == 0){
            if($claims["is_owner"] == TRUE){ 
                $res = modules_get_modules_for($claims["hotel_name"]);
            }else{
                $res["errors"] = array("Account does not own any hotel here");
            }
        }
        _respond($res["data"],$res["errors"]);
    }

    //install posted modules
    if($requested_route == "modules/install" && $method == "post"){ 
        $res = login_authorise($requested_route,$method,array(BUY_MODULES));
        if(count($res["errors"]) == 0){
            if($claims["is_owner"] == TRUE){
                //_dump($postdata);
                $res = modules_install_modules_for($claims["hotel_name"],$postdata,$claims["client_id"],$claims["id"],$claims["is_owner"]);
            }else{
                $res["errors"] = array("Account does not own any hotel here");
            }
        }
        _respond($res["data"],$res["errors"]);
    }

    //account recovery
    if($requested_route == "recover/request" && $method == "post"){ 
        $res = recover_user_account($postdata);
        _respond($res["data"],$res["errors"]);
    }

    //send,it account recover
    if($requested_route == "recover/update" && $method == "post"){ 
        $res = recover_user_account_update($postdata);
        _respond($res["data"],$res["errors"]);
    }
    
    //dashboard
    if($requested_route == "dashboard" && $method == "get"){ 
        $res = login_authorise($requested_route,$method,array(VIEW_DASH_BOARD));
        if(count($res["errors"]) == 0){
            //get the active module installations for this hotel
            //return modules only accessible by this user's roles
            $res = modules_enabled_for_roles($claims["hotel_name"],$claims["roles"]);
        }
        _respond($res["data"],$res["errors"]);
    }

    include("routes_rooms_managment.php");
    include("routes_bookings_management.php");
    include("routes_front_desk.php");
    include("routes_guests_management.php");
    include("routes_users_management.php");
    include("routes_roles_management.php");
    include("routes_reports_management.php");


    //mailer test
    if($requested_route == "email" && $method == "get"){ 
        // $receiver = "julian.okello@gmail.com";
        // $name = "Julian Okello";
        $link = "http://nnetego.com/activate_account.html?htl=hotel_name&id=1-4537";
        // $results = mailer_send_verification_email($receiver,$name, $link);


        $sendgrid = new SendGrid('nyolamike', 'sendgrid-2015');
        _dump($sendgrid);
        $email    = new SendGrid\Email();
        $email->addTo('julian.okello@gmail.com')->
            setFrom('nyolamike@live.com')->
            setSubject('Nnetego account activation link')->
            //setText('Hello World!')->
            setHtml("Click this link <a href='".$link."'>".$link."</a> to activate your account");

        $sendgrid->sendEmail($email);

        //_respond($results["data"],$results["errors"]);
        _respond(null,array());
    }

    //totken test
    if($requested_route == "token" && $method == "get"){
        $foo  = login_generate_token(array(), 1, TRUE, "casamilto");
        _dump($foo);
        _respond(null,array());
    }

    //db joins test
    if($requested_route == "joins" && $method == "get"){
        // SELECT E.empid, E.firstname, E.lastname, O.orderid
        // FROM HR.Employees AS E
        // JOIN Sales.Orders AS O
        // ON E.empid = O.empid;
        // $q = array(
        //     "room"=>"id category_id type_id number status capacity",
        //     "room.category_id.room_category" => "id name description",
        //     "room.type_id.room_type" => "id name description"
        // );
        // $a =  db_fetch("nnetego_peters_place","room","category_id type_id number status capacity price floor block notes");
        // //get all category_ids
        // $cat_ids = _mine_values($a["data"], null, null, "category_id",TRUE);
        // $typ_ids = _mine_values($a["data"], null, null, "type_id",TRUE);
        // //get the categories
        // $x =  db_fetchi("nnetego_peters_place","room_category","id name description",db_or(array("id" => $cat_ids)));
       
        // //get all the type_ids
        // $y =  db_fetchi("nnetego_peters_place","room_type","id name description",db_or(array("id" => $typ_ids)));
        // //piss them off
        // for($i=0;$i<count($a["data"]); $i++){
        //     $cat_id = $a["data"][$i]["category_id"];
        //     $type_id = $a["data"][$i]["type_id"];
        //     $cat = $x["data"][strval($cat_id)];
        //     $typ = $y["data"][strval($type_id)];
        //     $a["data"][$i]["category"] = $cat;
        //     $a["data"][$i]["type"] = $typ;
        // }
        $a = db_pull("nnetego_peters_place","room", ROOM,
            array("category_id" => array(
                "name" => "room_category", "cols"  => ROOM_CATEGORY
            ),"type_id" => array(
                "name" => "room_type", "cols"  => ROOM_TYPE
            ))
        ," id > 2");

        _respond($a["data"],$a["errors"]);
    }
    

    //db child test
    if($requested_route == "child" && $method == "get"){
        $res = rooms_management_get_rooms("nnetego_peters_place");
        //_dump($res);
        _respond($res["data"],array());
    }

    //time test
    if($requested_route == "time" && $method == "get"){
        $res = date_diff(new DateTime("2018-05-17"),new DateTime("2018-05-14"))->days;
        $sx = date_diff(new DateTime("2018-06-21 02:00 pm"),new DateTime("2018-06-22 02:00 pm"));
        //var_dump($sx);
        //$res = date_sub(new DateTime("2018-05-14"), 2); //date("G g H h");

        $now = time();
        $start = strtotime(date('y-m-d g:i', strtotime('-30 days')));
        $diff = $now - $start;
        $start60 = strtotime(date('y-m-d g:i', strtotime('-60 days')));
        $diff2 = $start - $start60;

        $a = new DateTime();
        $a->setTimestamp($now);
        $b = new DateTime();
        $b->setTimestamp($start);
        $diff = date_diff($a,$b)->days;

        $res = $diff; //array($now,$start,$diff,$start60,$diff2,$diff==$diff2);
        _respond($res,array());
    }
    
    //math test
    if($requested_route == "math" && $method == "get"){
        $res = 857858758758585855857857;
        _respond($res,array());
    }
    

    //get root 
    //napi/api/
    //napi/
    if($requested_route == ""){  
        _respond(array("name" => "NAPI (nnetego api)", "version" => "0.1.0"),array());
    } 

    //otherwise
    if($processes_route == false){
        echo "!NAPI 404 <br/> Oops Not Found Here";
        exit(0);
    }
?>