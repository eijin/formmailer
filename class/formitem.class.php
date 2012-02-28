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
*  init											: initialize this. give id, label, validation paramaters
*  put_value								: put value
*  get_value								: get value
*  get_id										: get id
*  get_label								: get label
*  get_validation_error			: get validation error code string
*  validate									: validate a item data.
* 
*/
class formItem {
	protected $id;
	protected $label;
	protected $validation;
	protected $value;
	protected $error;
	
	protected function __constructor() {
		$this->error = "";
	}
	
	// initialize
	public function init($id,$lbl,$vld) {
		$this->id = $id;
		$this->label = $lbl;
		$this->validation = $vld;
		return true;
	}
	
	// for value
	public function put_value($v) {
			$this->value = $v;
	}
	public function get_value() {
		return $this->value;
	}

	// for attributes
	public function get_id() {
		return $this->id;
	}
	public function get_label() {
		return $this->label;
	}
	public function get_validation_error() {
		return $this->error;
	}
	
	// validate
	// thanks for http://www.ideaxidea.com/archives/2009/03/practical_php_regexs.html
	public function validate() {
		$v_tags = split('&',$this->validation);
		foreach( $v_tags as $vt ) {
			if(!empty($vt)) {
				list($tag,$tag_value) = split('=',$vt);
				switch($tag) {
					case "require":
						if (empty($this->value)) {
							$this->error = "REQUIRE";
							return false;
						}
						break;
					case "email":
						if ($this->validate_value == "checkDNS" ) { $domain_check = true; } else { $domain_check = false; }
						if (!empty($this->value) && !$this->check_email($this->value, $domain_check)) {
							$this->error = "EMAIL_INCORRECT";
							return false;
						}
						break;
					case "zip":
						switch($tag_value) {
							case "":	// no value is 'jp'
							case "jp": $ptn = '/^\d{3}-?\d{4}$/'; break;
							case "us": $ptn = "/^([0-9]{5})(-[0-9]{4})?$/i"; break;
							default: $this->error = "VALIDATE_TYPE_ERROR"; return false;
						}
						if (!empty($this->value) && !preg_match($ptn, $this->value)) {
							$this->error = "ZIPCODE_INCORRECT";
							return false;
						}
						break;
					case "phone":
						switch($tag_value) {
							case "":	// no value is 'jp'
							case "jp": $ptn = '/^\d{2,5}-?\d{1,5}-?\d{3,5}$/'; break;
							case "us": $ptn = '/\(?\d{3}\)?[-\s.]?\d{3}[-\s.]\d{4}/x';; break;
							default: $this->error = "VALIDATE_TYPE_ERROR"; return false;
						}
						if (!empty($this->value) && !preg_match($ptn, $this->value)) {
							$this->error = "PHONE_INCORRECT";
							return false;
						}
						break;
					case "date":
						if(empty($tag_value)) { $tag_value = "Y-m-d"; }
						if(!empty($this->value)) {
							$d = strtotime($this->value);
							$dstr = date($tag_value,$d);
							if ($this->value != $dstr) {
								$this->error = "DATE_INCORRECT";
								return false;
							}
						}
						break;
					case "max":
						if (!empty($this->value) && $this->value > $tag_value) {
							$this->error = "OVER_MAX";
							return false;
						}
						break;
					case "min":
						if (!empty($this->value) && $this->value < $tag_value) {
							$this->error = "UNDER_MIN";
							return false;
						}
						break;
					case "maxLength":
						if (!empty($this->value) && strlen($this->value) > $tag_value) {
							$this->error = "OVER_LENGTH";
							return false;
						}
						break;
					case "minLength":
						if (!empty($this->value) && strlen($this->value) < $tag_value) {
							$this->error = "SHORT_LENGTH";
							return false;
						}
						break;
					//case "zenKatakana":
					//	if(!empty($this->value) && !preg_match('/^[ァ-ヶー]+$/',$this->value)) {
					//		$this->error = "NOT_ZENKATAKANA";
					//		return false;
					//	}
					//	break;
					case "alphaNumeric":
						if (!empty($this->value) && !preg_match('/^[a-zA-Z0-9 \_\-\+]+$/', $this->value)) {
							$this->error = "ALPHANUMERI_INCORRECT";
							return false;
						}
						break;
					case "numeric":
						if (!empty($this->value) && !preg_match('/^[0-9]+$/', $this->value)) {
							$this->error = "NUMERIC_INCORRECT";
							return false;
						}
						break;
					case "url":
						if (!empty($this->value) && !preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $this->value)) {
							$this->error = "URL_INCORRECT";
							return false;
						}
						break;
					case "ip":
						if (!empty($this->value) && !preg_match('/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $this->value)) {
							$this->error = "IP_INCORRECT";
							return false;
						}
						break;
					case "creditCard":		// credit card number
						if (!empty($this->value) && !preg_match('/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/', $this->value)) {
							$this->error = "CREDIT_CARD_INCORRECT";
							return false;
						}
					case "ssn":		// u.s. social security number
						if (!empty($this->value) && !preg_match('/^[\d]{3}-[\d]{2}-[\d]{4}$/', $this->value)) {
							$this->error = "SSN_INCORRECT";
							return false;
						}
						break;
					case "custom":
						if (!empty($this->value)) {
							if (!preg_match($this->value, $this->value)) {
								$this->error = "CUSTOM_INCORRECT";
								return false;
							}
						}
						break;
					default:
						$this->error = "VALIDATE_TYPE_ERROR";
						return false;
				}
			}
		}
		return true;
	}
	
	protected function check_email($email, $domainCheck = false) {
		if (preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+([\.][a-z0-9-]+)+$/i", $email)) {
				if ($domainCheck && function_exists('checkdnsrr')) {
						list (, $domain)  = explode('@', $email);
						if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) {
								return true;
						}
						return false;
				}
				return true;
		}
		return false;
	}

}
?>