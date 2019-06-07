<?php
    //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 for @ iamnyolamike@gmail.om
    //export OLD_SENDGRID_API_KEY='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' 
    function mailer_send_verification_email($receiver,$name, $link, $bee){
        try{
            //$sendgrid = new SendGrid('nyolamike', 'sendgrid-2015');
            $username = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["username"];
            $sender = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["sender"];
            $password = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["password"];
            $subject = $bee["BEE_HIVE_STRUCTURE"]["activation_email_subject"];
	        $sendgrid = new SendGrid($username, $password);
            //_dump($sendgrid);

            $cont = file_get_contents("bee/activate_email_template.html");
            //tools_dumpx("send grid",__FILE__,__LINE__,$cont);
            $cont = str_replace("__client_name",$name,$cont);
            $cont = str_replace("__link",$link,$cont);
            $cont = str_replace("__year",date("Y"),$cont);

            $email= new SendGrid\Email();
            $email->
                addTo($receiver)->
                setFrom($sender)->
                setSubject($subject)->
                //setText('Hello World!')->
                setHtml($cont);
            $atemp = $sendgrid->sendEmail($email);
            //tools_dumpx("send grid",__FILE__,__LINE__,$atemp);
            if(property_exists($atemp,"errors")){
                var_dump($atemp->errors);
            }
            return True;
        }catch(Exception $ex){
            $e = $ex->getMessage();
            tools_dumpx("send grid errors",__FILE__,__LINE__,$e);
            return $e;
        }
    }

    function mailer_send_recovery_email($receiver,$name, $links, $bee){
        try{
            $username = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["username"];
            $sender = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["sender"];
            $password = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["password"];
            $subject = $bee["BEE_HIVE_STRUCTURE"]["recovery_email_subject"];
            $sendgrid = new SendGrid($username, $password);

            $cont = file_get_contents("bee/recovery_email_template.html");
            $cont = str_replace("__client_name",$name,$cont);

            $str = "";
            foreach ($links as $ind => $link) {
                $str .= "<br/>For ".$link["title"]."<br/><a href=\"".$link['link']."\">".$link['link']."</a><br/>";
            }
            //tools_dumpx("str",__FILE__,__LINE__,$str);
            $cont = str_replace("__password",$str,$cont);
            //tools_dumpx("cont",__FILE__,__LINE__,$cont);
            $cont = str_replace("__year",date("Y"),$cont);

            //_dump($sendgrid);
            $email = new SendGrid\Email();
            $email->
                addTo($receiver)->
                setFrom($sender)->
                setSubject($subject)->
                setHtml($cont);
            $atemp = $sendgrid->sendEmail($email);
            if(property_exists($atemp,"errors")){
                var_dump($atemp->errors);
            }
            return True;
        }catch(Exception $ex){
            $e = $ex->getMessage();
            tools_dumpx("send grid errors",__FILE__,__LINE__,$e);
            return $e;
        }
    }

    function mailer_send_user_password_to_email($receiver,$name,$password, $bee){
        $username = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["username"];
        $sender = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["sender"];
        $password = $bee["BEE_HIVE_STRUCTURE"]["sendgrid"]["password"];
        $subject = $bee["BEE_HIVE_STRUCTURE"]["password_email_subject"];
        try{

            $cont = file_get_contents("bee/password_email_template.html");
            $cont = str_replace("__client_name",$name,$cont);
            $cont = str_replace("__password",$password,$cont);
            $cont = str_replace("__year",date("Y"),$cont);

            $sendgrid = new SendGrid($username, $password);
            //_dump($sendgrid);
            $email    = new SendGrid\Email();
            $email->
                addTo($receiver)->
                setFrom($sender)->
                setSubject($subject)->
                //setText('Hello World!')->
                setHtml($cont);
            $atemp = $sendgrid->sendEmail($email);
            return True;
        }catch(Exception $ex){
            return $ex->getMessage();
        }
    }

    
?>