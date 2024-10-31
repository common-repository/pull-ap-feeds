<?php
require_once(dirname(__FILE__)."/basic_additions.php");

function build_url($params, $ext_params = array()) {

    global $debug;

    $delimiter = isset($ext_params['delimiter']) ? $ext_params['delimiter'] : '&';
    $leading_character = isset($ext_params['leading_character']) ? $ext_params['leading_character'] : '?';
    $req = "";
    $more = false;

	if(value_or_false($debug)) {
		$params['debug'] = "true";
	}

    if ($params) {
        foreach($params as $key => $val) {
            if ($more)
                $req .= $delimiter;
            $more = true;
             
            if (!$key) {
                $req .= urlencode($val);
            } else {
                if (0 == strcmp(gettype($val), "array")) {
                    foreach($val as $vk => $vv) {
                        $req .= $key."[$vk]=".rawurlencode($vv);
                    }
                } else {
                    $req .= $key."=".rawurlencode($val);
                }
            }
        }
    }
	$ret = $leading_character.$req;
	if(isset($ext_params['anchor'])) {
		$ret .= "#".urlencode($ext_params['anchor']);
	}
    return $ret;
}

?>