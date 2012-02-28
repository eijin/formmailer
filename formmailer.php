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
- tool_generate_config.php
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

	/* you have to set path to formmail.class.php ---------------------------- */
	require_once("class/formmail.class.php");
	
	// one paramater from file "formmail_config_xml_dir.php"
	$config_xml_dir = "";

	/* you can set following three paramaters. ------------------------------- */
	// if you don't want to check host for security, you may set empty "" here.	
	// optins ---
	//  config_dir : the directory of 'config xml' -- read our document about 'congfig xml'
	//  html_encode : default is UTF-8 -- you may set TYPE depended on your HTML - exp. ASCII,JIS,EUC-JP,SJIS (refer from PHP mb_encodings)
	//  data_email_header : you may add header message to head of data email. for example: date('Y-m-d'), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
	//  data_email_label_prefix : you may change data item label prefix. Default is no prefix.
	//  data_email_label_suffix : you may change data item label suffix. Default is " : "
	//  upload_file_max_size		: When you use attachment file function, you may change max file size. Default size is 2097152 byte.


	// two paramaters from html form input
	$config_xml = $_REQUEST["config_xml"];
	$confirm_mode = $_REQUEST["confirm_mode"];
	
	$fmail = new formMail;	// formmail.class.php instance
	// initialyze this
	if (!$fmail->init($config_xml, $config_xml_dir)) {
		echo "error";
		$html = $fmail->get_error_html();
	} else {
		// load $_REQUEST, $_FILES
		if (!$fmail->load($_REQUEST, $_FILES)) {
			$html = $fmail->get_error_html();
		} else {
			// validate
			if( !$fmail->validate() ) {
				$html = $fmail->get_validate_error_html();
			} else {
				// confirm
				if ($confirm_mode=="on") {
					$html = $fmail->get_confirm_html();
				} else {
					// submit
					if ($fmail->send()){
						$html = $fmail->get_thanks_html();
					} else {
						$html = $fmail->get_error_html();
					}
				}
			}
		}
	}
	echo $html;	// output HTML code.
?>
