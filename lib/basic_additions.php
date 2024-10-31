<?

    function value_or_false(&$assoc, $key = -999) {
        if ($key == -999) {
            return isset($assoc) ? $assoc :
            false;
        } else {
            return (isset($assoc[$key])) ? $assoc[$key] :
            0;
        }
    }
    function value_or_true(&$assoc, $key) {
        if (isset($assoc[$key]))
            return $assoc[$key];
        else
            return 1;
    }
     
    function request_or_false($key) {
        return (string)value_or_false($_REQUEST, $key);
    }
    function request_or_true($key) {
        return (string)value_or_true($_REQUEST, $key);
    }
	function absolute_url($url, $protocol = "http", $port = "80") {
		global $config;
				
		if(!$server_name = value_or_false($config['server_name']))
			$server_name = $_SERVER['SERVER_NAME'];
		
		if(!$ssl_port = value_or_false($config['sslport']))
			$ssl_port = $_SERVER['REMOTE_PORT'];
		
		if(!$req_url = value_or_false($config['request_uri']))
			$req_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : false;
		
		if(ereg("^[A-z]*\:", $url))
			$rurl = $url;
		else if($url[0] == '/') {
			$rurl = "https://". $server_name . ":" . $ssl_port . $url;
		} else if($req_url) {
			$base_url = preg_replace  ( "/(^(.*))\/(.*)(\?.*)$/" , "$1/"  , $req_url );
			$rurl = "https://". $server_name . ":" . $ssl_port . $base_url.$url;
		} else {
			$rurl = "https://". $server_name . ":" . $ssl_port . "/" .$url;
		}
		return $rurl;
	}
?>