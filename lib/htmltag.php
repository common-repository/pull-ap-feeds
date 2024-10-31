<?

require_once("basic_additions.php");

    function xml_cdata($cdata) {
        return "<![CDATA[".$cdata."]]>";
         
    }
    function xmltag($name, $properties, $contents) {
        // just like HTMLTAG except it automatically disables the comments and line breaks
        return htmltag("", $name, $properties, $contents, false, false, false);
    }
     
     

    /**
     * returns an html tag $name with properties determined by the $properties
     * for example:
     * echo htmltag('my name', 'span', array('class' => 'my_name'), 'Thomas Hallock');
     * will output:
     * <div class = 'my_name'>Thomas Hallock</div>
     * 
     * @param string $memo a short description of what the tag is for.
     * @param string $name the HTML tag name
     * @param array $properties properties of the HTML tag. works with associative arrays and non-associative arrays. ['key'] => 'value' gets turned into key = 'value'
     * @param string $content the innerHTML of the tag
     * @param bool $indent true = indent output , false = don't indent output
     * @param bool $comment_inside true = output $memo as an HTML comment , false = don't output $memo as an HTML comment
     * @param bool $auto_closing true = if the tag is a singleton, automatically close the tag
     * @param int $quote_escape_style quote escape style for property values
     */
    function htmltag($memo, $name, $properties = array(), $content = "", $indent = true, $comment_inside = true, $auto_closing = true, $quote_escape_style = ENT_QUOTES) {
        $ret = "";
        htmltag_append_ref($ret, $memo, $name, $properties, $content, $indent, $comment_inside, $auto_closing, $quote_escape_style);
        return $ret;
    }
    function htmltag_append($memo, $name, $properties, $content = "", $indent = true, $comment_inside = true, $auto_closing = true) {
        $ret = "";
        htmltag_append_ref($ret, $memo, $name, $properties, $content, $indent, $comment_inside, $auto_closing, false);
        return $ret;
    }
    function htmltag_ref(&$ret, $memo, $name, $properties, $content = "", $indent = true, $comment_inside = true, $auto_closing = true, $destroy_content = true) {
        return htmltag_append_ref($ret, $memo, $name, $properties, $content, $indent, $comment_inside, $auto_closing, $destroy_content);
    }
    function htmltag_append_ref(&$ret, $memo, $name, $properties, &$content, $indent = true, $comment_inside = true, $auto_closing = true, $destroy_content = true, $quote_escape_style = ENT_QUOTES) {
		global $display_format;
		
		$nametolower = strtolower($name);
		switch($display_format) {
			case "ical":
				if(isset($properties['icaltag']))
					$ret .= $properties['icaltag'].":";
			case "plaintext":
				switch($nametolower) {
				case 'input':
					switch($properties['type']) {
						case 'text':
						case '':
						$ret .= "\t".value_or_false($properties['value']);
					}
				break;
				case 'p':
				case 'br':
				case 'label':
					$ret .= "\n".$content;
				break;
				case 'h1';
				case 'h2';
				case 'h3';
					$ret .= "\n".strtoupper($content);
				break;
				case "li":
					$ret .= " * $content";
				break;
				case 'img':
					if(isset($properties['alt'])) {
						$ret .= $properties['alt'];
					}
				break;
				case 'input':
					switch(strtolower($properties['type'])) {
					case 'checkbox':
					case 'select':
					break;
					
					case 'option':
					
					if(isset($properties['selected'])) {
						$ret .= $content;
					}
					
					break;
					case 'text':
					case 'default':
					$ret .= value_or_false($properties['value']);
					}
				break;
				case 'a':
					if(isset($properties['class'])) {
						if(stristr(" ".$properties['class']." ", " show_url_in_plaintext ")) {
							$aurl = absolute_url($properties['href']);
							if($content && 0!=strcmp($content, $aurl))
								$ret .= $content." : ";
							$ret .= $aurl." ";
							break;
						}
					}
				default:
				$ret .= $content;
				}



				if(0==strcmp($display_format, "plaintext")) {

					switch($nametolower) {
						case 'h1':
						case 'h2':
						case 'h3':
						case 'div':
						case "p":
							$ret .= "\n";
						case "tr":
						case "li":
							$ret .= "\n";
						break;
						case 'td':
							$ret .= "\t";
						break;
						default:
//						if($pts=value_or_false($properties['plaintext_suffix'])) {
//							$ret .= html_entity_decode($pts);
//						}
					}
				} else {
					$ret .= "\n";
				}
			break;
			case 'widget_iframe':
				if('a' == $nametolower) {
//					die('killing this link : '.$properties['href']);
					unset($properties['href']);
				}
			case "html":
			default:

		        //        $ret = "";
		        if ($memo && !$comment_inside) {
		//            $ret .= "<!-- BEGIN ".$memo." -->";
		        }
		        $ret .= "<$name ";
				if($properties) {
			        foreach($properties as $key => $val) {
			            if ($key && 0 != strcmp($key, "singles")) {
			                $ret .= "".$key." = \"".htmlentities($val, ENT_COMPAT)."\" ";
			            }
			            else if(0 == strcmp($key, "singles")) {
			                foreach($val as $single) {
			                    $ret .= $single." ";
			                }
			            } else {
			                $ret .= $val." ";
			            }
			        }
				}
	        if (false && !$content && $auto_closing) {
	            $ret .= " />\n";
	        } else {
	            $ret .= ">";
	            if ($memo && $comment_inside) {
	                //  $ret .= "<!-- BEGIN ".$memo." -->";
	            }
	            if ($indent) {
	                //                $ret .= "\n";
                
	                //              $ret .= $content;
                
	                // the below two methods take up too much memory.
                
	                //$ret .= "     ";
	                //$ret .= str_replace("\n", "     \n", $content);
                
	                $excon = explode("\n", $content);
	                foreach($excon as $line) {
	                    // indent the contents of the tag
	                    if($line)
							$ret .= "     ".$line."\n";
	                }
	            } else {
	                $ret .= $content;
	            }
	            $ret .= "</$name >";
	            if ($memo) {
	//                $ret .= "<!-- END ".$memo." -->";
	            }
	            if ($indent) {
	                $ret .= "\n";
	            }
	        }
	        unset($content);
		}
        return $ret;
    }

    // note: if there is a javascript property, make sure to only use single quotes. double quotes don't work yet. this is entirely possible, it just doesn't work yet
    function build_select_menu($properties, $values, $selected_value = '') {
         
        $options = "";
		$inoptgroup = false;

        foreach($values as $action => $label) {
			if(0==strcmp(substr($action,0,3), "---")) {
				if($inoptgroup)
					$options .= "</optgroup>\n";
				if($label) {
					$options .= "<optgroup label = \"$label\">\n";
					$inoptgroup = true;
				} else {
					$inoptgroup = false;
				}
			} else {
	            $options .= "<option value = '".$action."' ";
	            if (0 == strcmp($action, $selected_value))
	                $options .= "selected ";
	            $options .= ">".$label."</option>\n";
			}
        }
		if($inoptgroup) {
			$options .= "</optgroup>\n";
		}
        $ret = htmltag("select menu", "select", $properties, $options);
        return $ret;
    }
     


?>