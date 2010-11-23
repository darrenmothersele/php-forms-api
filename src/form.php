<?php

/*
 *  Turn on error reporting during development
 */
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

/*
 *  PHP Forms API library configuration 
 */ 

define('FORMS_DEFAULT_PREFIX', '<div class="form-container">');
define('FORMS_DEFAULT_SUFFIX', '</div>');
define('FORMS_DEFAULT_FIELD_PREFIX', '<div class="form-item">');
define('FORMS_DEFAULT_FIELD_SUFFIX', '</div>');
define('FORMS_VALIDATE_EMAIL_DNS', TRUE);
define('FORMS_VALIDATE_EMAIL_BLOCKED_DOMAINS', 'mailinator.com|guerrillamail.com');
define('FORMS_BASE_PATH', '');
define('FORMS_XSS_ALLOWED_TAGS', 'a|em|strong|cite|code|ul|ol|li|dl|dt|dd');

// Here are some prioity things I'm working on:
// TODO: Support edit forms by allowing an array of values to be specified, not just taken from _REQUEST


class cs_form {
  
  protected $form_id = 'cs_form';
  protected $form_token = '';
  protected $action = '';
  protected $attributes = array();
  protected $method = 'post';
  protected $prefix = FORMS_DEFAULT_PREFIX;
  protected $suffix = FORMS_DEFAULT_SUFFIX;
  protected $validate = array();
  protected $processed = FALSE;
  protected $preprocessors = FALSE;
  protected $validated = FALSE;
  protected $submitted = FALSE;
  protected $valid = TRUE;
  protected $submit = '';
  protected $error = '';
  
  protected $fields = array();
  
  public function __construct($options = array()) {
    foreach ($options as $name => $value) {
      $this->$name = $value;
    }
    if (empty($this->submit)) {
      $this->submit = "{$this->form_id}_submit";
    } 
    $sid = session_id();
    if (!empty($sid)) {
      $this->form_token = sha1(mt_rand(0, 1000000));
  		$_SESSION['form_token'][$this->form_token] = $_SERVER['REQUEST_TIME'];
		}
  }
  
  // Warning: some messy logic in calling process->submit->values
  public function values() {
    if (!$this->processed) {
      $this->process();
    }
    $output = array();
    foreach ($this->fields as $name => $field) {
      $output[$name] = $field->values();
    }
    return $output;
  }
  
  public function reset() {
    foreach ($this->fields as $name => $field) {
      $field->reset();
      unset($_POST[$name]);
    }
    unset($_REQUEST['form_id']);
    $this->processed = FALSE;
    $this->validated = FALSE;
    $this->submitted = FALSE;
  }
  
  public function is_submitted() {
    return $this->submitted;
  }
  
  public function process() {
    if (!$this->processed) {
      $request = ($this->method == 'post') ? $_POST : $_GET;
      if (isset($request['form_id']) && $request['form_id'] == $this->form_id) {
        foreach ($request as $name => $value) {
          if (isset($this->fields[$name])) {
            $this->fields[$name]->process($value, $name);
          }
        }
      }
      $this->processed = TRUE;
    }
    if (!$this->preprocessors) {
      foreach ($this->fields as $name => $field) {
        $field->preprocess();
      }
    }
    if ((!$this->submitted) && $this->valid()) {
      $this->submitted = TRUE;
      $submit_function = $this->submit;
      if (function_exists($submit_function)) {
        foreach ($this->fields as $name => $field) {
          $field->postprocess();
        }
        $submit_function($this, ($this->method == 'post') ? $_POST : $_GET);
      }
    }
  }
  
  public function valid() { 
    if ($this->validated) { 
      return $this->valid;
    }
    if (!isset($_REQUEST['form_id'])) {
      $this->valid = FALSE;
    } else if ($_REQUEST['form_id'] == $this->form_id) {
      $sid = session_id();
      if (!empty($sid)) {
        $this->valid = FALSE;
        $this->error = 'Form is invalid or has expired';
        if (isset($_REQUEST['form_token']) && isset($_SESSION['form_token'][$_REQUEST['form_token']])) {
          if ($_SESSION['form_token'][$_REQUEST['form_token']] >= $_SERVER['REQUEST_TIME'] - 7200) {
            $this->valid = TRUE;
            $this->error = '';
            unset($_SESSION['form_token'][$_REQUEST['form_token']]);
          }
        }
      }
      foreach ($this->fields as $field) {
        if (!$field->valid()) {
          $this->valid = FALSE;
        }
      }
    }
    $this->validated = TRUE;
    return $this->valid;
  }
  
  public function add_field($name, $field) {
    if (!is_object($field)) {
      $field_type = isset($field['type']) ? "cs_{$field['type']}" : 'cs_textfield';
      $field = new $field_type($field);
    }
    $this->fields[$name] = $field;
  }
  
  public function show_errors() {
    return empty($this->error) ? '' : "<li>{$this->error}</li>";
  }
  
  public function render() {
    $output = $this->prefix;
    if (!$this->valid()) {
      $output .= "<div class=\"error\"><ul>";
      $output .= $this->show_errors();
      foreach ($this->fields as $field) {
        $output .= $field->show_errors();
      }
      $output .= "</div>";
    }
    $attributes = '';
    foreach ($this->attributes as $key => $value) {
      $attributes .= " {$key}=\"{$value}\"";
    }
    $output .= "<form action=\"{$this->action}\" method=\"{$this->method}\"{$attributes}>\n";
    foreach ($this->fields as $name => $field) {
      $output .= $field->render($name);
    }
    $output .= "<input type=\"hidden\" name=\"form_id\" value=\"{$this->form_id}\" />\n";
    $output .= "<input type=\"hidden\" name=\"form_token\" value=\"{$this->form_token}\" />\n";
    $output .= "</form>\n";
    return $output . $this->suffix;
  }
  
  public static function validate_required($value = NULL) {
    if (!empty($value)) {
      return TRUE;
    } else {
      return "<em>%t</em> is required";
    }
  }  
  
  public static function validate_max_length($value, $options) {
    if (strlen($value) > $options) {
      return "Maximum length of <em>%t</em> is {$options}";
    }
    return TRUE;
  }
  public static function validate_min_length($value, $options) {
    if (strlen($value) < $options) {
      return "<em>%t</em> must be longer than {$options}";
    }
    return TRUE;
  }
  public static function validate_exact_length($value, $options) {
    if (strlen($value) != $options) {
      return "<em>%t</em> must be {$options} characters long.";
    } 
    return TRUE;
  }
  public static function validate_alpha($value) {
    if (!preg_match( "/^([a-z])+$/i", $value)) {
      return "<em>%t</em> must contain alphabetic characters.";
    }
    return TRUE;
  }
  
	protected function validate_alpha_numeric($value) {
		if (!preg_match("/^([a-z0-9])+$/i", $value)) {
		  return "<em>%t</em> must only contain alpha numeric characters.";
	  } 
	  return TRUE;
	}
	
	protected function validate_alpha_dash($value) {
		if (!preg_match("/^([-a-z0-9_-])+$/i", $value)) {
		  return "<em>%t</em> must contain only alpha numeric characters, underscore, or dashes";
	  }
	  return TRUE;
	}
	
	protected function validate_numeric($value) {
		if (!is_numeric($value)) {
		  return "<em>%t</em> must be numeric.";
	  } 
	  return TRUE;
	}
	
	protected function validate_integer($value) {
		if (!preg_match( '/^[\-+]?[0-9]+$/', $value)) {
		  return "<em>%t</em> must be an integer.";
	  }
	  return TRUE;
	}
	
  public static function validate_match($value, $options) {
    $other = cs_form::scan_array($options, $_REQUEST);
    if ($value != $other) {
      return "The field <em>%t</em> is invalid.";
    }
    return TRUE;
  }
  
  public static function validate_file_extension($value, $options) {
    $options = explode(',', $options);
    $ext = substr(strrchr($value['filepath'], '.'), 1);
    if (!in_array($ext, $options)) {
      return "File upload <em>%t</em> is not of required type";
    }
    return TRUE;
  }
  public static function validate_file_not_exists($value) {
    if (file_exists($value['filepath'])) {
      return "The file <em>%t</em> has already been uploaded";
    }
    return TRUE;
  }
  public static function validate_max_file_size($value, $options) {
    if ($value['filesize'] > $options) {
      $max_size = cs_form::format_bytes($options);
      return "The file <em>%t</em> is too big. Maximum filesize is {$max_size}.";
    }
    return TRUE;
  }
  
  private static function format_bytes($size) {
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 2).$units[$i];
  }
  
  public static function validate_email($email) {
    if (empty($email)) return TRUE;
    $check_dns = FORMS_VALIDATE_EMAIL_DNS;
    $blocked_domains = explode('|', FORMS_VALIDATE_EMAIL_BLOCKED_DOMAINS);
    $atIndex = strrpos($email, "@");
    if (is_bool($atIndex) && !$atIndex) {
      return "<em>%t</em> is not a valid email. It must contain the @ symbol.";
    } else {
      $domain = substr($email, $atIndex+1);
      $local = substr($email, 0, $atIndex);
      $localLen = strlen($local);
      $domainLen = strlen($domain);
      if ($localLen < 1 || $localLen > 64) {
        return "<em>%t</em> is not a valid email. Local part is wrong length.";
      } else if ($domainLen < 1 || $domainLen > 255) {
        return "<em>%t</em> is not a valid email. Domain name is wrong length.";
      } else if ($local[0] == '.' || $local[$localLen-1] == '.') {
        return "<em>%t</em> is not a valid email. Local part starts or ends with '.'";
      } else if (preg_match('/\\.\\./', $local)) {
        return "<em>%t</em> is not a valid email. Local part two consecutive dots.";
      } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
        return "<em>%t</em> is not a valid email. Invalid character in domain.";
      } else if (preg_match('/\\.\\./', $domain)) {
        return "<em>%t</em> is not a valid email. Domain name has two consecutive dots.";
      } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
        if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
        return "<em>%t</em> is not a valid email. Invalid character in local part.";
        }
      }
      if (in_array($domain, $blocked_domains)) {
        return "<em>%t</em> is not a valid email. Domain name is in list of disallowed domains.";
      }  
      if ($check_dns && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
        return "<em>%t</em> is not a valid email. Domain name not found in DNS.";
      }
    }
    return TRUE;
  }
  
  public static function process_trim($text) {
    return trim($text);
  }
  public static function process_ltrim($text) {
    return ltrim($text);
  }
  public static function process_rtrim($text) {
    return rtrim($text);
  }
  
  private static function _validate_utf8($text) {
    if (strlen($text) == 0) {
      return TRUE;
    }
    return (preg_match('/^./us', $text) == 1);
  }
  
  public static function process_xss_weak($string) {
    return filter_xss($string, array('a|abbr|acronym|address|b|bdo|big|blockquote|br|caption|cite|code|col|colgroup|dd|del|dfn|div|dl|dt|em|h1|h2|h3|h4|h5|h6|hr|i|img|ins|kbd|li|ol|p|pre|q|samp|small|span|strong|sub|sup|table|tbody|td|tfoot|th|thead|tr|tt|ul|var'));
  }
  
  public static function process_xss($string, $allowed_tags = FORMS_XSS_ALLOWED_TAGS) {
    // Only operate on valid UTF-8 strings. This is necessary to prevent cross
    // site scripting issues on Internet Explorer 6.
    if (!cs_form::_validate_utf8($string)) {
      return '';
    }
    // Store the input format
    cs_form::_filter_xss_split($allowed_tags, TRUE);
    // Remove NUL characters (ignored by some browsers)
    $string = str_replace(chr(0), '', $string);
    // Remove Netscape 4 JS entities
    $string = preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $string);

    // Defuse all HTML entities
    $string = str_replace('&', '&amp;', $string);
    // Change back only well-formed entities in our whitelist
    // Decimal numeric entities
    $string = preg_replace('/&amp;#([0-9]+;)/', '&#\1', $string);
    // Hexadecimal numeric entities
    $string = preg_replace('/&amp;#[Xx]0*((?:[0-9A-Fa-f]{2})+;)/', '&#x\1', $string);
    // Named entities
    $string = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]*;)/', '&\1', $string);

    return preg_replace_callback('%
      (
      <(?=[^a-zA-Z!/])  # a lone <
      |                 # or
      <[^>]*(>|$)       # a string that starts with a <, up until the > or the end of the string
      |                 # or
      >                 # just a >
      )%x', 'cs_form::_filter_xss_split', $string);
  }
  
  private static function _filter_xss_split($m, $store = FALSE) {
    static $allowed_html;

    if ($store) {
      $m = explode("|", $m);
      $allowed_html = array_flip($m);
      return;
    }

    $string = $m[1];

    if (substr($string, 0, 1) != '<') {
      // We matched a lone ">" character
      return '&gt;';
    }
    else if (strlen($string) == 1) {
      // We matched a lone "<" character
      return '&lt;';
    }

    if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9]+)([^>]*)>?$%', $string, $matches)) {
      // Seriously malformed
      return '';
    }

    $slash = trim($matches[1]);
    $elem = &$matches[2];
    $attrlist = &$matches[3];

    if (!isset($allowed_html[strtolower($elem)])) {
      // Disallowed HTML element
      return '';
    }

    if ($slash != '') {
      return "</$elem>";
    }

    // Is there a closing XHTML slash at the end of the attributes?
    // In PHP 5.1.0+ we could count the changes, currently we need a separate match
    $xhtml_slash = preg_match('%\s?/\s*$%', $attrlist) ? ' /' : '';
    $attrlist = preg_replace('%(\s?)/\s*$%', '\1', $attrlist);

    // Clean up attributes
    $attr2 = implode(' ', _filter_xss_attributes($attrlist));
    $attr2 = preg_replace('/[<>]/', '', $attr2);
    $attr2 = strlen($attr2) ? ' ' . $attr2 : '';

    return "<$elem$attr2$xhtml_slash>";
  }
  
  public static function process_plain($text) {
    // if using PHP < 5.2.5 add extra check of strings for valid UTF-8
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }
  
  public static function attributes($attributes) {
    if (is_array($attributes)) {
      $t = '';
      foreach ($attributes as $key => $value) {
        $t .= " $key=" . '"' . cs_form::process_plain($value) . '"';
      }
      return $t;
    }
  }
  
  private static function scan_array($string, $array) {
    list($key, $rest) = preg_split('/[[\]]/', $string, 2, PREG_SPLIT_NO_EMPTY);
    if ( $key && $rest ) {
        return @cs_form::scan_array($rest, $array[$key]);
    } elseif ( $key ) {
        return $array[$key];
    } else {
        return FALSE;
    }
  }
  
}

class cs_field {

  protected $title = '';
  protected $description = '';
  protected $attributes = array();
  protected $autocomplete_path = FALSE;
  protected $ajax = FALSE;
  protected $default_value;
  protected $disabled = FALSE;
  protected $validate = array();
  protected $preprocess = array();
  protected $postprocess = array();
  protected $prefix = FORMS_DEFAULT_FIELD_PREFIX;
  protected $suffix = FORMS_DEFAULT_FIELD_SUFFIX;
  protected $size = 60;
  protected $weight = 0;
  protected $value = '';
  protected $error = '';
  
  public function __construct($options = array()) {
    foreach ($options as $name => $value) {
      $this->$name = $value;
    }
    $this->value = $this->default_value;
  }

  public function values() {
    return $this->value;
  }

  public function reset() {
    $this->value = $this->default_value;
  }

  public function get_weight() {
    return $this->weight;
  }
  
  public function process($value) {
    $this->value = $value;
  }
  
  public function preprocess($process_type = "preprocess") {
    foreach ($this->$process_type as $processor) {
      $processor = "process_{$processor}";
      if (function_exists($processor)) {
        $this->value = $processor($this->value);
      } else {
        $this->value = cs_form::$processor($this->value);
      }
    }
  }
  public function postprocess() {
    $this->preprocess("postprocess");
  }
  
  public function valid() {
    foreach ($this->validate as $validator) {
      $matches = array();
      preg_match('/^([A-Za-z0-9_]+)(\[(.+)\])?$/', $validator, $matches);
      $validator = "validate_{$matches[1]}";
      $options = isset($matches[3]) ? $matches[3] : NULL;
      if (function_exists($validator)) {
        $error = $validator($this->value, $options);
      } else {
        $error = cs_form::$validator($this->value, $options);
      }
      if ($error !== TRUE) {
        $this->error = str_replace('%t', $this->title, $error);
        return FALSE;
      }
    }
    return TRUE;
  }
  
  public function show_errors() {
    return empty($this->error) ? '' : "<li>{$this->error}</li>";
  }

}

class cs_submit extends cs_field {
  
  public function render($name) {
    if (empty($this->value)) {
      $this->value = 'Submit';
    }
    $output = $this->prefix;
    $output .= "<input type=\"submit\" id=\"{$name}\" name=\"{$name}\" value=\"{$this->value}\" />\n";
    return $output . $this->suffix;
  }
}

class cs_textfield extends cs_field {
  
  public function render($name) {
    $output = $this->prefix;
    $this->attributes['class'] = 'textfield';
    if (!empty($this->error)) {
      $this->attributes['class'] .= ' error';
    }
    $required = (in_array('required', $this->validate)) ? ' <span class="required">*</span>' : '';
    $output .= "<label for=\"{$name}\">{$this->title}{$required}</label>\n";
    $output .= "<input type=\"text\" id=\"{$name}\" name=\"{$name}\" value=\"{$this->value}\" size=\"{$this->size}\" class=\"{$this->attributes['class']}\" />\n";
    if (!empty($this->description)) {
      $output .= "<div class=\"description\">{$this->description}</div>";
    }
    return $output . $this->suffix;
  }
  
}

class cs_textarea extends cs_field {

  protected $rows = 5;

  public function render($name) {
    $output = $this->prefix;
    $output .= "<label for=\"{$name}\">{$this->title}</label>\n";
    $output .= "<textarea id=\"{$name}\" name=\"{$name}\" cols=\"{$this->size}\" rows=\"{$this->rows}\">\n";
    $output .= $this->value;
    $output .= "</textarea>";
    if (!empty($this->description)) {
      $output .= "<div class=\"description\">{$this->description}</div>";
    }
    return $output . $this->suffix;
  }
}


class cs_password extends cs_field {
  public function render($name) {
    $output = $this->prefix;
    $output .= "<label for=\"{$name}\">{$this->title}</label>\n";
    $output .= "<input type=\"password\" id=\"{$name}\" name=\"{$name}\" value=\"\" size=\"{$this->size}\" />\n";
    return $output . $this->suffix;
  }
}

class cs_select extends cs_field {

  protected $multiple = FALSE;

  public function render($name) {
    $output = $this->prefix;
    $output .= "<label for=\"{$name}\">{$this->title}</label>\n";
    $extra = ($this->multiple) ? ' multiple' : '';
    $field_name = ($this->multiple) ? "{$name}[]" : $name;
    $output .= "<select name=\"{$field_name}\" id=\"{$name}\"{$extra}>\n";
    foreach ($this->options as $key => $value) {
      $output .= "<option value=\"{$key}\">{$value}</option>\n";
    }
    $output .= "</select>\n";
    return $output . $this->suffix;
  } 
}

class cs_radios extends cs_field {
  public function render($name) {
    $output = $this->prefix;
    $output .= "<label for=\"{$name}\">{$this->title}</label>\n";
    foreach ($this->options as $key => $value) {
      $checked = ($this->value == $key) ? ' checked=\"checked\"' : '';
      $output .= "<label><input type=\"radio\" name=\"{$name}\" value=\"{$key}\"{$checked} />{$value}</label>\n";
    }
    return $output . $this->suffix;
  }
}

class cs_checkboxes extends cs_field {
  public function render($name) {
    $output = $this->prefix;
    if (!empty($this->title)) {
      $output .= "<label for=\"{$name}\">{$this->title}</label>\n";
    }
    foreach ($this->options as $key => $value) {
      $checked = (is_array($this->default_value) && in_array($key, $this->default_value)) ? ' checked=\"checked\"' : '';
      $output .= "<label><input type=\"checkbox\" name=\"{$name}[]\" value=\"{$key}\"{$checked} />{$value}</label>\n";
    }
    return $output . $this->suffix;
  }
}

class cs_file extends  cs_field {
  protected $uploaded = FALSE;

  public function __construct($options = array()) {
    parent::__construct($options);
    if (!isset($options['size'])) {
      $this->size = 30;
    }
  }

  public function render($name) {
    $output = $this->prefix;
    if (!empty($this->title)) {
      $output .= "<label for=\"{$name}\">{$this->title}</label>\n";
    }
    $output .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$name}\" />";
    $output .= "<input type=\"file\" name=\"{$name}\" size=\"{$this->size}\" />";
    return $output . $this->suffix;
  }
  
  public function process($value, $name) {
    $this->value = array(
      'filepath' => $this->destination .'/'. basename($_FILES[$name]['name']),
      'filename' => basename($_FILES[$name]['name']),
      'filesize' => $_FILES[$name]['size'],
      'mimetype' => $_FILES[$name]['type'],
    );
    if ($this->valid()) {
      move_uploaded_file($_FILES[$name]['tmp_name'], $this->value['filepath']);
      $this->uploaded = TRUE;
    }
  }
  
  public function valid() {  
    if ($this->uploaded) {
      return TRUE;
    }
    return parent::valid();
  }
}


class cs_fieldset extends cs_field {
  
  protected $collapsible = FALSE;
  protected $collapsed = FALSE;
  
  protected $fields = array();
  
  public function add_field($name, $field) {
    if (!is_object($field)) {
      $field_type = isset($field['type']) ? "cs_{$field['type']}" : 'cs_textfield';
      $field = new $field_type($field);
    }
    $this->fields[$name] = $field;
  }
  
  public function values() {
    $output = array();
    foreach ($fields as $name => $field) {
      $output[$name] = $field->values();
    }
    return $output;
  }
  
  public function render($parent_name) {
    $output = $this->prefix;
    $this->attributes['class'] = 'fieldset';
    if ($this->collapsible) {
      $this->attributes['class'] .= ' collapsible';
      if ($this->collapsed) {
        $this->attributes['class'] .= ' collapsed';
      } else {
        $this->attributes['class'] .= ' expanded';
      }
    }
    $output .= "<fieldset class=\"{$this->attributes['class']}\">\n<legend>{$this->title}</legend>\n<div class=\"fieldset-inner\">\n";
    foreach ($this->fields as $name => $field) {
      $output .= $field->render("{$parent_name}[{$name}]");
    }
    return $output ."</div></fieldset>\n". $this->suffix;
  }
  
  public function preprocess() {
    foreach ($this->fields as $field) {
      $field->preprocess();
    }
  }
  public function process($values) {
    foreach ($values as $name => $value) {
      $this->fields[$name]->process($value);
    }
  }
  
  public function valid() {
    $valid = TRUE;
    foreach ($this->fields as $field) {
      if (!$field->valid()) {
        $valid = FALSE;
      }
    }
    return $valid;
  }
  public function show_errors() {
    $output = "";
    foreach ($this->fields as $field) {
      $output .= $field->show_errors();
    }
    return $output;
  }
  
  public function reset() {
    foreach ($this->fields as $field) {
      $field->reset();
    }
  } 
}


