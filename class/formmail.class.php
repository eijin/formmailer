<?php
/* =========================================================================
formmailer is to set up and manage mail form system
Copyright (C) 2012  Eiji Nakai www.smallmake.com

formmailer.php: version 1.0.0 - Jan 8, 2012.

formmailer Project Revision 1.0.0 - Jan 8, 2012.
Xmlbulletin is licenced under the GPL.
- formmailer.php
- formmail.class.php
- formitem.class.php

Using my following copyleft software
- tool_maintain_config_xml.php
- tool_generate_htmls.php

GPL:
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
============================================================================= */
/* 
* METHOD
*  init											: initialize this. give options and load paramaters.
*  load											: load item data. give $_REQUEST, then load to its map.
*  validate									: validate item data.
*  send											: send data mail to you, and send auto reply to a user. additionally save CSV file.
*  get_validate_error_html	: get validation alert html code, if it has some error.
*  get_confirm_html					: get the confirmation html code.
*  get_thanks_html					: get the completion message page html code.
*  get_error_html						: get the error message page html code, if you have error.
* 
*/

require_once("formitem.class.php");

class formMail {
	// options
	public $options;				// this is array, initializes in _constructer
	
	// config file name
	protected $config_xml;			// xml file name for configuration

	// paramaters from config file (and html)
	public $head;						// header info from xml config file
	public $params;					// valious paramater from xml config file
	protected $params_on_html;	// this override on params from html

	// form item data
	protected $items;						// array of items based on instance of class formItem
	protected $attachments;			// array for attachment files

	protected $error_html;

	/* -------------------------------------------------------------------
	* Following two functions are specifically for Japanese.
	*  if you want to change them, you may rewrite them or override by extention classes. 
	*/
		
	// make validate error alert message for Japanese
	protected function get_validate_alert() {
		$validate_list_start = "<ul>\n";
		$validate_list_msg = "<li>「%s」は%s</li>\n";
		$validate_list_end = "</ul>\n";
		$validate_error_msg = array(
			'REQUIRE'=>								"必須項目です。",
			'EMAIL_INCORRECT'=>				"空白や全角文字などが含まれている可能性があります。",
			'ZIPCODE_INCORRECT'=>			"半角数字でハイフン区切り、3桁-4桁で入力してください。",
			'PHONE_INCORRECT'=>				"半角数字で市外局番等をハイフン(-)で区切って入力ください。",
			'DATE_INCORRECT'=>				"日付の書式が違っています。",
			'OVER_MAX'=>							"数字が大きすぎます。",
			'UNDER_MIN'=>							"数字が小さすぎます。",
			'OVER_LENGTH'=>						"文字数をオーバーしています。",
			'SHORT_LENGTH'=>					"文字数が少なすぎます。",
			'ALPHANUMERI_INCORRECT'=>	"半角英数字のみで入力してください。記号は使えません。",
			'NUMERIC_INCORRECT'=>			"半角数字のみで入力してください。",
			'URL_INCORRECT'=>					"URLの書式が誤っています。",
			'IP_INCORRECT'=>					"IPアドレスの書式が誤っています。",
			'SSN_INCORRECT'=>					"米国社会保障番号の書式が誤っています。",
			'PHONE_INCORRECT'=>				"半角数字で市外局番等をハイフン(-)で区切って入力ください。",
			'VALIDATE_TYPE_ERROR'=>		"== エラー：validate type error =="
		);
		$validate_alert = $validate_list_start;
		foreach($this->validate_list as $key=>$value) {
			$validate_alert .= sprintf($validate_list_msg,$this->items[$key]->get_label(),$validate_error_msg[$value]);
		}
		$validate_alert .= $validate_list_end;
		return $validate_alert;
	}
	
	// send mail for Japanese @2010.05.08 changed: add $cc, $bcc
	protected function send_mail($from,$to,$cc,$bcc,$subject,$message,$attachments=array()) {
		// convert code of Subject
		$x_subject = mb_convert_encoding($subject, "JIS", "UTF-8");
		$x_subject = base64_encode($x_subject);
		$x_subject = "=?iso-2022-jp?B?".$x_subject."?=";
		
		// make header
		$gmt = date("Z");
		$gmt_abs  = abs($gmt);
		$gmt_hour = floor($gmt_abs / 3600);
		$gmt_min = floor(($gmt_abs - $gmt_hour * 3600) / 60);
		if ($gmt >= 0) {
				$gmt_flg = "+";
		} else {
				$gmt_flg = "-";
		}
		$gmt_rfc = date("D, d M Y H:i:s ").sprintf($gmt_flg."%02d%02d", $gmt_hour, $gmt_min);
	
		$headers = "Date: {$gmt_rfc}\n";
		$headers .= "From: {$from}\n";
		if (!empty($cc)) { $headers .= "Cc: " . $cc . "\n"; }
		if (!empty($bcc)) { $headers .= "Bcc: " . $bcc . "\n"; }
		$headers .= "MIME-Version: 1.0\n";
		$headers .= "X-Mailer: smallmake's form mailer on PHP5\n";
		
		$boundary = "";
		if(sizeof($attachments) != 0) {
			$boundary = md5(uniqid(rand())); // make boundary string
			$headers .= "Content-Type: multipart/mixed;\n";
			$headers .= "\tboundary=\"" . $boundary . "\"\n";
		} else { 
			$headers .= "Content-type: text/plain; charset=ISO-2022-JP\n";
			$headers .= "Content-Transfer-Encoding: 7bit";
		}
		
		// convert code of Message
		$x_message = $message;
		if (get_magic_quotes_gpc()) {
			$x_message = stripslashes($x_message);
		}
		$x_message = str_replace("\r\n", "\r", $x_message);
		$x_message = str_replace("\r", "\n", $x_message);
		$x_message = mb_convert_encoding($x_message, "JIS", "UTF-8");
		// attachments
		if(sizeof($attachments) != 0) {
			$x_message_sep = "--". $boundary . "\n";
			$x_message_sep .= "Content-Type: text/plain; charset=ISO-2022-JP\n";
			$x_message_sep .= "Content-Transfer-Encoding: 7bit\n\n";
			$x_message = $x_message_sep . $x_message;
			$x_message .= "\n\n";
			foreach($attachments as $attach) {
				$f_tmp_name = $attach['tmp_name'];
				$f_name = $attach['name'];
				$f_name = mb_convert_encoding($f_name, "JIS","UTF-8");
				$f_type = $attach['type'];
				if(file_exists($f_tmp_name)){
					$fp = fopen($f_tmp_name, "r") or die("error");
					$contents = fread($fp, filesize($f_tmp_name));
					fclose($fp);
					$f_encoded = chunk_split(base64_encode($contents)); //base64 encode
					$x_message .= "\n--". $boundary . "\n";
					$x_message .= "Content-Type: " . $f_type . ";\n";
					$x_message .= "\tname=\"=?iso-2022-jp?B?" . base64_encode($f_name) ."?=\"\n";
					$x_message .= "Content-Transfer-Encoding: base64\n";
					$x_message .= "Content-Disposition: attachment;\n";
					$x_message .= "\tfilename*=iso-2022-jp''" . $f_name ."\n\n";
					$x_message .= $f_encoded . "\n";
				}
			}
			$x_message .= "\n--". $boundary . "--\n";
		}
		// php mail function
		return mail($to, $x_subject, $x_message, $headers);
		//return mb_send_mail($to, $x_subject, $x_message, $headers);
	}


	/* -------------------------------------------------------------------
	* Folowing are not deppended on any language
	*/
	
	// constructor
	function __construct() {
		$this->head 	=	array(
											"title"							=> "",
											"link"							=> "",
											"description"				=> "",
											"language"					=> "",
											"pubDate"						=> ""
										);
		$this->params =	array(
											"dataEmailFrom"					=> "",
											"dataEmailTo"						=> "",
											"dataEmailSubject"				=> "",
											"dataEmailComment"				=> "",
											"dataCsvPath"				=> "",
											"dataCsvEncode"			=> "",
											"htmlValidate"			=> "",
											"htmlConfirm"				=> "",
											"htmlThanks"				=> "",
											"returnUrl"					=> "",
											"autoReplyFrom"			=> "",
											"autoReplySubject"	=> "",
											"autoReplyCc"				=> "",
											"autoReplyBcc"			=> "",											
											"autoReplyMessage"	=> ""
										);
		$this->options = array(
											"hostName"								=> "",
											"htmlEncode"							=> "UTF-8",
											"dataEmailHeadMessage"		=> "",
											"dataEmailItemLabelPrefix"=> "",
											"dataEmailItemLabelSuffix"=> " : ",
											"uploadFileLimitedSize"		=> "2097152"
										);
		$this->params_on_html = array();
		$this->items = array();
		$this->validate_list = array();
	}
	
	// initializes
	public function init($config_xml, $config_xml_dir) {
		$this->config_xml = $config_xml;

		$config_xml_path = $config_xml;
		if (!empty($config_xml_dir)) {
			if (substr($config_xml_dir,-1,1) != '/') $config_xml_dir = $config_xml_dir . "/";
			$config_xml_path = $config_xml_dir . $config_xml;
		}
	
		// load settings
		if (!file_exists($config_xml_path)) {
			$this->error_html = $this->makeHtml("error","Can't find XML file: ".$config_xml_path, "");
			return false;
		} else {
			if (($data = simplexml_load_file($config_xml_path))===false) {
				$this->error_html = $this->makeHtml("error","Can't open XML file.", "");
				return false;
			} else {
				// load header info
				foreach($this->head as $key=>$value) {
					$this->head[$key] = $data->channel->$key;
				}
				foreach($this->options as $key=>$value) {
					if (!empty($data->channel->options->$key)) {
						$this->options[$key] = $data->channel->options->$key;
					}
				}
				foreach($this->params as $key=>$value) {
					$this->params[$key] = $data->channel->params->$key;
				}
			}
			
			
			if($this->options['hostName'] != "") {
				$ref = parse_url($_SERVER['HTTP_REFERER']);
				if ($ref['host'] != $this->options['hostName'])
				die("Error: Referer is a different host.");
			}
	
			return true;
		}
	}
	
	
	// load $_REQUEST and $_FILES data
	public function load($request,$files=array()) {
		// ------------------------------------
		// process the item deffinitions
		// you must write exp. <input type="hidden" name="items[age]" value="age,numeric" /> in your form HTML
		if (!array_key_exists("items", $request)) {
			$this->error_html = $this->makeHtml("error","There is no setting hidden 'items' in the HTML file.", "");
			return false;
		}
		foreach($request['items'] as $key=>$value) {
			if ($this->options['htmlEncode'] != "UTF-8") { $value = mb_convert_encoding($value,"UTF-8",$this->options['htmlEncode']); }
			list($label,$validation) = split(",",$value);
			if (get_magic_quotes_gpc()) { $label = stripslashes($label); }	// @2010.05.30 negrect backslashes
			$item = new formItem;
			$item->init($key,trim($label),trim($validation));
			$this->items[$key] = $item;
		}
		// put item values into item based on the item diffinitions
		// Note that value is converted to UTF-8
		foreach($request as $key=>$value ) {
			if(array_key_exists($key,$this->items)) {					// this is item by the deffinition
				if(is_array($value)) { $value = join(", ",$value); }
				
				$value = htmlspecialchars_decode($value);
				if (get_magic_quotes_gpc()) { $value = stripslashes($value); }	// @2010.05.30 negrect backslashes
				
				if ($this->options['htmlEncode'] != "UTF-8") { $value = mb_convert_encoding($value,"UTF-8",$this->options['htmlEncode']); }
				$this->items[$key]->put_value($value);
			} else if(array_key_exists($key,$this->params)) {	// this is paramaters
				if ($this->options['htmlEncode'] != "UTF-8") { $value = mb_convert_encoding($value,"UTF-8",$this->options['htmlEncode']); }
				$this->params_on_html[$key] = $value;
			}
		}
		// override params by params_on_html
		foreach($this->params_on_html as $key=>$value) {
			$this->params[$key] = $this->params_on_html[$key];
		}
		
		// ------------------------------------
		// process attachment files definition
		$this->attachments = $files;

		// put item values into item based on the item diffinitions
		// Note that value is converted to UTF-8
		foreach($this->attachments as $key=>$val ) {
			if( intval($val['size']) > intval($this->options['uploadFileLimitedSize']) ) {
				$this->error_html = $this->makeHtml("error",
																						$val['name'] . "(" . 
																						round(intval($val['size'])/(1024*1024), 1) . "MB) is over max size(" .
																						round(intval($this->options['uploadFileLimitedSize'])/(1024*1024), 1) . "MB).", "");
				return false;
			}
			$value = $val['name'];
			if ($this->options['htmlEncode'] != "UTF-8") { $value = mb_convert_encoding($value,"UTF-8",$this->options['htmlEncode']); }
			//if (get_magic_quotes_gpc()) { $value = stripslashes($value); }
			$this->items[$key]->put_value(htmlspecialchars_decode($value));
			$this->attachments[$key]['name'] = $value; // !!!! attention! I convert $_FILES[]['name'] to UTF-8, because ... see spec note.@2010.06.30
		}
		return true;
	}
	
	// validate all items
	public function validate() {
		$flag = true;
		foreach ($this->items as $item) {
			if (!$item->validate()) {
				$this->validate_list[$item->get_id()] = $item->get_validation_error();
				$flag = false;	// validation error
			}
		}
		return $flag;
	}
	
	// validate error html
	public function get_validate_error_html() {
		$validate_alert = $this->get_validate_alert();
		//
		if (empty($this->params['htmlValidate'])) {	// use default page
			$html = $this->makeHtml("Varidate Error",$validate_alert, "");
		} else {																		// use template html
			$fp = fopen($this->params['htmlValidate'], "r");
			$html = fread($fp, filesize($this->params['htmlValidate']));
			if ($this->options['htmlEncode'] != "UTF-8") { $html = mb_convert_encoding($html,"UTF-8",$this->options['htmlEncode']); }
			$html = str_replace("__%ValidationAlert%__",$validate_alert, $html);
		}
		if ($this->options['htmlEncode'] != "UTF-8") { $html = mb_convert_encoding($html,$this->options['htmlEncode'],"UTF-8"); }
		return $html;
	}

	// confirm html
	public function get_confirm_html() {
		if (sizeof($this->attachments) > 0) {	// ERROR! because cannot bring any <input type="file" from previous page
			$this->error_html = $this->makeHtml("error","When you use &lt;input type=file...&gt; objects, you cannot use confirmation page and remove 'confirm_mode'","");
			return $this->get_error_html();
		}
		 // making input hidden tag for paramaters and items
		$hidden_tags = "";
		$hidden_tags .= '<input type="hidden" name="config_xml" id="config_xml" value="' . $this->config_xml . '" />' . "\n";
		foreach($this->params_on_html as $key=>$value) {
			$hidden_tags .= '<input type="hidden" name="'. $key .'" id="'. $key .'" value="' . $value . '" />' . "\n";
		}
		foreach($this->items as $item) {
			// no need validation paramater, so this outputs only label into "value" attribute
			$hidden_tags .= '<input type="hidden" name="items['. $item->get_id() .']" id="items['. $item->get_id() .']" value="' . $item->get_label() . '" />' . "\n";
		}
		foreach($this->items as $item) {
			$value = htmlspecialchars($item->get_value());		// @2010.05.08 changed
			$hidden_tags .= '<input type="hidden" name="'. $item->get_id() .'" id="'. $item->get_id() .'" value="' . $value . '" />' . "\n";
		}
		// making html
		if (empty($this->params['htmlConfirm'])) {		// use default page
			$page_content = $hidden_tags;
			foreach($this->items as $item) { // label and value for display
				$v = $item->get_value();
				if (empty($v)) {$v = "-"; }
				$v = nl2br(htmlspecialchars($v));
				$page_content .= $item->get_label() . " : " . $item->get_value() . "<br />\n";
			}
			$page_content .= '<input type="submit" value="submit">';
			$html = $this->makeHtml("Confirm",$page_content,"");
		} else {																		// use template html
			$fp = fopen($this->params['htmlConfirm'], "r");
			$html = fread($fp, filesize($this->params['htmlConfirm']));
			if ($this->options['htmlEncode'] != "UTF-8") { $html = mb_convert_encoding($html,"UTF-8",$this->options['htmlEncode']); }
			$html = str_replace("__%HiddenData%__",$hidden_tags, $html);
			$html = str_replace("__%PhpSelf%__",$_SERVER['PHP_SELF'], $html);
			foreach($this->items as $item) {
				$v = $item->get_value();
				if (empty($v)) {$v = "-"; }
				$v = nl2br(htmlspecialchars($v));
				$html = str_replace("__%".$item->get_id()."%__",$v,$html);
			}
		}
		if ($this->options['htmlEncode'] != "UTF-8") { $html = mb_convert_encoding($html,$this->options['htmlEncode'],"UTF-8"); }
		return $html;
	}
	
	// thanks html
	public function get_thanks_html() {
		if (empty($this->params['htmlThanks'])) {	// use default page
			$html = $this->makeHtml("Thank you.","Thank you","");
		} else {																	// use template html
			$fp = fopen($this->params['htmlThanks'], "r");
			$html = fread($fp, filesize($this->params['htmlThanks']));
			if ($this->options['htmlEncode'] != "UTF-8") { $html = mb_convert_encoding($html,"UTF-8",$this->options['htmlEncode']); }
			foreach($this->items as $item) {
				$v = $item->get_value();
				if (empty($v)) {$v = "-"; }
				$v = nl2br(htmlspecialchars($v));
				$html = str_replace("__%".$item->get_id()."%__",$v,$html);
			}
		}
		if ($this->options['htmlEncode'] != "UTF-8") { $html = mb_convert_encoding($html,$this->options['htmlEncode'],"UTF-8"); }
		return $html;
	}
	
	// error html
	public function get_error_html() {
		return $this->error_html;
	}
	
	// make html
	protected function makeHtml($page_title,$page_content,$return_url) {
		$form_action = $_SERVER['PHP_SELF'];
		$return_url = $this->params['returnUrl'];
$html =<<< END_OF_HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>$page_title</title>
</head>
<body>
<h2>$page_title</h2>
<form name="f" method="post" action="$form_action">
	<p>$page_content</p>
	<a href="$return_url">[RETURN]</a>
</form>
</body>
</html>
END_OF_HTML;
		return $html;
	}
	
	// put value to item
	public function putItemValue($key, $value) {
		if (!array_key_exists($key, $this->items)) {
				$this->error_html = $this->makeHtml("error","cannot put value to items. no exists key: ". $key,"");
				return false;
		}
		$this->items[$key]->put_value($value);
		return true;
	}

	public function send() {
		// check paramaters
		if( empty($this->params['dataEmailFrom']) || empty($this->params['dataEmailTo']) || empty($this->params['dataEmailSubject']) ) {
			$this->error_html = $this->makeHtml("error","require paramaters of dataEmailFrom, dataEmailTo and dataEmailSubject","");
			return false;
		}
		// send data to us
		$message = "";
		if(!empty($this->params['dataEmailComment'])) {
			$message .= $this->params['dataEmailComment'] . "\n\n";
		}
		if(!empty($this->options['dataEmailHeadMessage'])) {
			$str = $this->options['dataEmailHeadMessage'];
			$str = preg_replace_callback('/({.+?})/',create_function('$proc','$func = substr($proc[0],1,strlen($proc[0])-2);eval(\'$res = \' . $func . \';\');return $res;'),$str);
			$str = preg_replace('/\\\\n/',"\n", $str);
			$message .= $str;
		}
		//
		foreach ($this->items as $item) {
			$message .= $this->options['dataEmailItemLabelPrefix'] . $item->get_label() . $this->options['dataEmailItemLabelSuffix'] . $item->get_value() . "\n";
		}
		$res = $this->send_mail($this->params['dataEmailFrom'],$this->params['dataEmailTo'],"","",$this->params['dataEmailSubject'],$message,$this->attachments);
		if (!$res) {
			$this->error_html = $this->makeHtml("error","send data mail error","");
			return false;
		}
		// send auto reply to user
		if (!empty($this->params['autoReplyFrom'])) {
			if(array_key_exists("email",$this->items)) {
				if ($this->items['email']->get_value() != "") {
					$message = $this->params['autoReplyMessage'];
					foreach($this->items as $item) { // insert items value into message
						$v = $item->get_value();
						$message = str_replace("__%".$item->get_id()."%__",$v,$message);
					}
					$res = $this->send_mail(	$this->params['autoReplyFrom'],
																		$this->items['email']->get_value(),
																		$this->params['autoReplyCc'],
																		$this->params['autoReplyBcc'],
																		$this->params['autoReplySubject'],
																		$message	);
					if (!$res) {
						$this->error_html = $this->makeHtml("error","send auto replay mail error","");
						return false;
					}
				}
			}
		}
		
		// save csv file @2010.05.08
		if (!empty($this->params['dataCsvPath'])) {
			$dataCsvPath = $this->params['dataCsvPath'];
			$dataCsvEncode = $this->params['dataCsvEncode'];
			if(empty($dataCsvEncode)) { $dataCsvEncode = "sjis-win"; }
			// make item header
			$item_header = "";
			foreach ($this->items as $item) {
				$item_header .= '"' . str_replace('"','""', $item->get_label()) . '",';
			}
			$item_header = substr($item_header, 0, strlen($item_header)-1) . "\n";
			$item_header = mb_convert_encoding($item_header, $dataCsvEncode, "UTF-8");
			// if size zero or not exist, insert item header in the top of file
			$work = "";
			if (file_exists($this->params['dataCsvPath'])) {
				if (filesize($this->params['dataCsvPath']) == 0) {
					$work = $item_header;
				}
			} else {
				$work = $item_header;
				if( ($fh = fopen($dataCsvPath, 'w')) === FALSE) {
					$this->error_html = $this->makeHtml("error","cannot create CSV file:".$dataCsvPath,"");
					return false;
				}
				fwrite($fh, "");
				fclose($fh);
			}
			// put item data
			$item_data = "";
			foreach ($this->items as $item) {
				$item_data .= '"' . str_replace('"','""', $item->get_value()) . '",';
			}
			$item_data = substr($item_data, 0, strlen($item_data)-1) . "\n";
			$item_data = mb_convert_encoding($item_data, $dataCsvEncode, "UTF-8");
			$work .= $item_data;
			// write file
			$fh = fopen($dataCsvPath, 'a');
			while(1) {
				if (flock($fh, LOCK_EX)) {
					 fwrite($fh, $work);
					 fflush($fh);
					 flock($fh, LOCK_UN);
					 break;
				}
			}
			fclose($fh);
		}
		return true;
	}
	
}
?>
