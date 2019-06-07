<?php

class SendGrid {
  const VERSION = '2.2.0';

  protected $namespace  = 'SendGrid',
            $headers    = array('Content-Type' => 'application/json'),
            $options,
            $web;
  public    $api_user,
            $api_key,
            $url,
            $version = self::VERSION;

  
  public function __construct($api_user, $api_key, $options=array()) {
    $this->api_user = $api_user;
    $this->api_key = $api_key;

    $options['turn_off_ssl_verification'] = (isset($options['turn_off_ssl_verification']) && $options['turn_off_ssl_verification'] === true);
    $protocol = isset($options['protocol']) ? $options['protocol'] : 'https';
    $host = isset($options['host']) ? $options['host'] : 'api.sendgrid.com/api/';
    $port = isset($options['port']) ? $options['port'] : '';

    $this->url = isset($options['url']) ? $options['url'] : $protocol . '://' . $host . ($port ? ':' . $port : '');

    $this->options  = $options;
  }

  public function sendEmail(SendGrid\Email $email) {
    $command = 'mail.send.json';
    $form             = $email->toWebFormat();
    $form['api_user'] = $this->api_user; 
    $form['api_key']  = $this->api_key; 

    $response = $this->makeRequest($this->url.$command, $form);

    return $response;
  }
  
  //EDITED
  public function run($command, $parameters = array(), $http_method = "post", $headers = array())
  {

        $url = $this->url.$command;

        $form = array();
        if(is_array($parameters) || $parameters instanceof Traversable) {
            $form['api_user'] = $this->api_user;
            $form['api_key'] = $this->api_key;
            foreach ($parameters as $key => $value) {
                $form[$key] = $value;
            }
        } else if(is_string($parameters)) {
            $form = "api_user=". urlencode($this->api_user) . "&api_key=". urlencode($this->api_key)."&".$parameters;
        }
       
        $response = null;
        if($http_method == "post")
            $response = $this->makeRequest($url, $form);
        else if($http_method == "get")
            $response =$this->makeRequest($url, $form, false);

        if(isset($response))
            return $response;
        return false;
  }

  /**
   * Makes the actual HTTP request to SendGrid
   * @param $form array web ready version of SendGrid\Email
   * @return stdClass parsed JSON returned from SendGrid
   */
  private function makeRequest($url, $form, $post = true) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, $post);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
    curl_setopt($ch, CURLOPT_USERAGENT, 'sendgrid/' . $this->version . ';php');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->options['turn_off_ssl_verification']);

    $response = curl_exec($ch);

    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
      throw new Exception($error);
    }

    return json_decode($response);
  }

  public static function register_autoloader() {
    spl_autoload_register(array('SendGrid', 'autoloader'));
  }

  public static function autoloader($class) {
    // Check that the class starts with 'SendGrid'
    if ($class == 'SendGrid' || stripos($class, 'SendGrid\\') === 0) {
      $file = str_replace('\\', '/', $class);

      if (file_exists(dirname(__FILE__) . '/' . $file . '.php')) {
        require_once(dirname(__FILE__) . '/' . $file . '.php');
      }
    }
  }
}
