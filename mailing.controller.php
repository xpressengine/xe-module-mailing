<?php

require_once(_XE_PATH_.'modules/mailing/mailing.lib.php');

class mailingController extends mailing {

	function init(){
	}

	function procMailingToggleList()
	{
		$oMemberModel =& getModel('member');

		$args->module_srl = Context::get('module_srl');
		if(!$args->module_srl) return new Object(-1,'msg_invalid_request');

		if(!Context::get('is_logged')) return new Object(-1,'msg_not_permitted');

		$logged_info = Context::get('logged_info');
		$args->member_srl = $logged_info->member_srl;

		$output = executeQuery('mailing.getMemberJoined', $args);
		if($output->data) {
			return executeQuery("mailing.deleteMember", $args);
		} else {
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($args->member_srl);
			$args->email_address = $member_info->email_address;
			return executeQuery("mailing.insertMember", $args);
		}
	}

	function removeOrgMsg($body) {
		preg_match("/<div[^>]*>(.+)<div style=\'border/siU", $body, $match); // outlook
		if($match) return $match[1];
		preg_match('!^(.+)<div class="gmail_quote"!', $body, $match); // gmail
		if($match) return $match[1];
		preg_match('!^(.+)<span>-----Original Message-----</span>!', $body, $match);
		if($match) return $match[1].'</p></div>';
		return $body;
	}

	function replaceCid($body, $reverse = false) {
		$cid_array = Context::get('cid_array');
		if(!$cid_array) return $body;
		foreach($cid_array as $cid => $uploaded_filename)
		{
			if($reverse)
			{
				$body = str_replace($uploaded_filename, "cid:".$cid, $body);
			}
			else
			{
				$body = str_replace("cid:".$cid, $uploaded_filename, $body);
			}
		}
		return $body;
	}

	function writeComment($comment_srl, &$mailMessage, &$member_info, $reference_srl, $module_srl)
	{
		$obj = null;
		$oCommentModel =& getModel('comment');
		$oComment = $oCommentModel->getComment($reference_srl);
		if($oComment->isExists())
		{
			$obj->document_srl =  $oComment->get('document_srl');
			$obj->parent_srl = $reference_srl;
		}
		else
		{
			$obj->document_srl = $reference_srl;
		}

		$obj->comment_srl = $comment_srl;
		$obj->member_srl = $member_info->member_srl;
		$obj->user_id = $member_info->user_id;
		$obj->user_name = $member_info->user_name;
		$obj->nick_name = $member_info->nick_name;
		$obj->email_address = $member_info->email_address;
		$obj->homepage = $member_info->homepage;
		$obj->content = $this->removeOrgMsg($this->replaceCid($mailMessage->getBody()));
		$obj->module_srl = $module_srl;
		$obj->title = $mailMessage->subject;
		$oCommentController = &getController('comment');
		$output = $oCommentController->insertComment($obj, false);
		return $obj;
	}

	function procMailingInsertMail()
	{
		$message = Context::get('message');
		if(!$message) return;

		$oModuleModel =& getModel('module');
		$mailingConfig = $oModuleModel->getModuleConfig('mailing');
		$maildomain = $mailingConfig->maildomain;
		if(!$maildomain)
		{
			$maildomain = $_SERVER["SERVER_NAME"];
		}

		$mailMessage = new mailMessage($message['tmp_name']);
		$mailMessage->parse();
		if($mailMessage->messageId)
		{
			preg_match("/@(.+)>$/i", $mailMessage->messageId, $matches);
			if($matches[1] == $_SERVER["SERVER_NAME"] || $matches[1] == $maildomain)
			{
				return;
			}
		}

		// except mail 
		$except_mailbody = trim($mailingConfig->except_mailbody);
		if($except_mailbody)
		{
			$_content = $this->replaceCid($mailMessage->getBody());
			if($except_mailbody{0} == '/' && substr($except_mailbody,-1) =='/')
			{
				if(preg_match_all($except_mailbody,$_content)) return;
			}
			else
			{
				$pos = strpos($_content, $except_mailbody);
				if($pos !== false)
				{
					 return;
				} 
			}	
		}

		$fromAddress = mailparse_rfc822_parse_addresses($mailMessage->from);
		$fromAddress = array_shift($fromAddress);
		if($fromAddress["display"]) $nick_name = imap_utf8($fromAddress["display"]);
		$fromAddress = $fromAddress["address"];
		if(!$nick_name) $nick_name = $fromAddress;

		$toAddress = mailparse_rfc822_parse_addresses($mailMessage->to);
		if($mailMessage->cc) {
			$ccAddress = mailparse_rfc822_parse_addresses($mailMessage->cc);
			$toAddress = array_merge($toAddress, $ccAddress);
		}
		foreach($toAddress as $objAddress)
		{
			$obj = $objAddress["address"];
			$mailingAddress = $obj;
			$mid = explode("@", $obj);
			$moduleModel =& getModel('module');
			if($mid[1] != $maildomain) {
				$site_info = $moduleModel->getSiteInfoByDomain($mid[1]);
				if(!$site_info) continue;
				$site_srl = $site_info->site_srl;    
			}
			else
			{
				$site_srl = 0;
			}
			$mid = array_shift($mid);
			$mid = explode(".", $mid);
			if(count($mid) > 1)
			{
				$vid = $mid[0];
				$mid = $mid[1];
				$site_info = $moduleModel->getSiteInfoByDomain($vid);
				$site_srl = $site_info->site_srl;
			}
			else
			{
				$mid = $mid[0];
			}

			$targetModule = $moduleModel->getModuleInfoByMid($mid, $site_srl);
			if($targetModule) break;
		}

		if(!$targetModule)
		{
			$mailMessage->close();
			return;
		}

		$mailing_config = $oModuleModel->getModulePartConfig('mailing', $targetModule->module_srl);
		if(!isset($mailing_config->write_permission)) $mailing_config->write_permission = "only_registered";

		if($mailing_config->write_permission == "not_use")
		{
			$mailMessage->close();
			return;
		}

		$memberModel =& getModel('member');
		$member_srl = $memberModel->getMemberSrlByEmailAddress($fromAddress);
		if($member_srl)
		{
			$member_info = $memberModel->getMemberInfoByMemberSrl($member_srl);
		}

		$oModel =& getModel('mailing');
		if($mailing_config->write_permission == "only_registered" && (!$member_info || !$oModel->isMember($targetModule->module_srl, $fromAddress, $member_info->member_srl)))
		{
			$mailMessage->close();
			return;
		}

		if($member_info) {
			$oMemberController = &getController('member');
			$oMemberController->doLogin($member_info->user_id);
		}
		else
		{
			$member_info->user_id = null;
			$member_info->user_name = $nick_name;
			$member_info->nick_name = $nick_name;
			$member_info->email_address = $fromAddress;
		}

		$reference_srl = null;
		if($mailMessage->inreplyto)
		{
			$reference_srl = $mailMessage->inreplyto;  
		}
		else if($mailMessage->references)
		{
			$reference_srl = $mailMessage->references;
		}

		if($reference_srl)
		{
			$reference_srl = explode("@", $reference_srl); 
			$reference_srl = array_shift($reference_srl);
			$reference_srl = substr($reference_srl, 1);
			$comment_srl = getNextSequence();
			$mailMessage->procWriteAttachments($comment_srl, $targetModule->module_srl, $member_info->member_srl);
			$obj = $this->writeComment($comment_srl, $mailMessage, $member_info, $reference_srl, $targetModule->module_srl);
			$content = sprintf("<a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, $obj->content);
		}
		else
		{
			$document_srl = getNextSequence(); 
			$mailMessage->procWriteAttachments($document_srl, $targetModule->module_srl, $member_info->member_srl);

			$obj = null;
			$obj->document_srl = $document_srl;
			$obj->member_srl = $member_info->member_srl;
			$obj->allow_comment = "Y";
			$obj->allow_trackback = "Y";
			$obj->user_id = $member_info->user_id;
			$obj->user_name = $member_info->user_name;
			$obj->nick_name = $member_info->nick_name;
			$obj->email_address = $member_info->email_address;
			$obj->homepage = $member_info->homepage;
			preg_match("!^\[(.*)\] (.*)!", $mailMessage->subject, $matches);
			if(count($matches))
			{
				$category_name = $matches[1];
				$title = $matches[2];
				$oDocumentModel =& getModel('document');
				$categoryList = $oDocumentModel->getCategoryList($targetModule->module_srl);
				foreach($categoryList as $category)
				{
					if($category_name == $category->title)
					{
						$obj->category_srl = $category->category_srl;
						break;
					}
				}
			}
			if($obj->category_srl)
			{
				$obj->title = $title;
			}
			else
			{
				$obj->title = $mailMessage->subject;
			}



			$obj->content = $this->replaceCid($mailMessage->getBody());
			$obj->module_srl = $targetModule->module_srl;
			$oDocumentController = &getController('document');
			$output = $oDocumentController->insertDocument($obj, true);

			if($output->toBool() && $targetModule->module == 'issuetracker') {
				$issueObj->module_srl = $targetModule->module_srl;
				$issueObj->title = $obj->title;
				$issueObj->document_srl = $obj->document_srl;
				$output = executeQuery('issuetracker.insertIssue', $issueObj);
			}

			$content = sprintf("<a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $obj->content);
			$mailMessage->close(); 
		}

		{
			$oMail = new Mail();
			$oMail->setTitle($obj->title);
			$oMail->setContent( $this->replaceCid($content, true) ); 
			$oMail->setSender($obj->nick_name, $obj->email_address);
			$oMail->setMessageId( ($obj->comment_srl?$obj->comment_srl:$obj->document_srl)."@".$maildomain );
			$oMail->setReplyTo( $mailingAddress );
			$oMail->setReceiptor($mailingAddress, $mailingAddress);
			$cid_array = Context::get('cid_array');
			if($cid_array)
			{
				foreach($cid_array as $cid => $filepath)
				{
					$oMail->addAttachment($filepath, $cid);
				}
			}
			$attachments = Context::get('attachments');
			if($attachments)
			{
				foreach($attachments as $filename => $filepath)
				{
					$oMail->addAttachment($filepath, $filename);
				}
			}
			$mailingAddressesData = $oModel->getTargetAddresses($targetModule->module_srl);
			$res = array();
			foreach($mailingAddressesData as $mailAddr)
			{
				if($mailAddr->member_email_address)
				{
					$res[] = $mailAddr->member_email_address;
				}
				else
				{
					$res[] = $mailAddr->email_address;
				}
			}
			$oMail->setBCC(implode(",", $res));
			$oMail->setAdditionalParams("-f ".$mailingAddress);
			$oMail->send();
		}
	}

	function triggerInsertComment(&$obj)
	{
		if($obj->is_secret == "Y") return new Object();
		if(Context::get('act') == 'procMailingInsertMail') return new Object();
		$moduleModel =& getModel('module');
		$module_srl = $obj->module_srl;
		$targetModule = $moduleModel->getModuleInfoByModuleSrl($module_srl);
		if($targetModule->site_srl != 0)
		{
			$site_info = $moduleModel->getSiteInfo($targetModule->site_srl);
			if(isSiteID($site_info->domain)) {
				$vid = $site_info->domain;
			}
			else
			{
				$maildomain = $site_info->domain;
			}
		}
		$mid = $targetModule->mid;
		$oModel =& getModel('mailing');
		$mailingAddressesData = $oModel->getTargetAddresses($targetModule->module_srl);
		if(!$mailingAddressesData || count($mailingAddressesData) < 1 || !trim($obj->content)) return new Object();
		$content = preg_replace_callback('/<img([^>]+)>/i',array($this,'replaceResourceRealPath'), $obj->content);
		$content = sprintf("<a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, $content);
		$oModuleModel =& getModel('module');
		$mailingConfig = $oModuleModel->getModuleConfig('mailing');
		if(!$mailingConfig->maildomain) $mailingConfig->maildomain = $_SERVER["SERVER_NAME"];
		if(!$maildomain) $maildomain = $mailingConfig->maildomain;

		$oDocumentModel =& getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);
		if($oDocument->get('category_srl'))
		{
			$category = $oDocumentModel->getCategory($oDocument->get('category_srl'));
			$title = sprintf("[%s] %s", $category->title, $oDocument->getTitleText());
		}
		else
		{
			$title = $oDocument->getTitleText();
		}

		$oMail = new Mail();
		$oMail->setTitle("RE: ".$title);

		$oMail->setContent( $content ); 
		if(!$obj->email_address) $oMail->setSender($obj->nick_name, $obj->nick_name.'@noreply.com');
		else $oMail->setSender($obj->nick_name, $obj->email_address);
		$oMail->setMessageId( $obj->comment_srl."@".$mailingConfig->maildomain );
		$oMail->setReferences( $obj->parent_srl?$obj->parent_srl:$obj->document_srl."@".$_SERVER["SERVER_NAME"] );
		if($vid)
		{
			$mailingAddress = $vid.".".$mid."@".$maildomain;
		}
		else
		{
			$mailingAddress = $mid."@".$maildomain;
		}
		$oMail->setReplyTo( $mailingAddress );
		$oMail->setReceiptor($mailingAddress, $mailingAddress);
		$res = array();
		if($obj->parent_srl)
		{
			$oCommentModel =& getModel('comment');
			$oComment = $oCommentModel->getComment($obj->parent_srl);
		}
		foreach($mailingAddressesData as $mailAddr)
		{
			if($mailAddr->include_comment == "N") continue;
			elseif($mailAddr->include_comment == "M")
			{
				if($mailAddr->member_srl != $oComment->member_srl && $mailAddr->member_srl != $oDocument->get('member_srl'))
				{
					continue;	
				}
			}

			if($mailAddr->member_email_address)
			{
                if(substr($mailAddr->member_email_address,-1)=='_') continue;
				$res[] = $mailAddr->member_email_address;
			}
			else
			{
                if(substr($mailAddr->email_address,-1)=='_') continue;
				$res[] = $mailAddr->email_address;
			}
		}
		if(count($res) == 0) return new Object();
		$oMail->setBCC(implode(",", $res));
		$oMail->setAdditionalParams("-f ".$mailingAddress);
		$oMail->send();
		return new Object();
	}

	function replaceResourceRealPath($matches) {
		return preg_replace('/src=(["\']?)\.\/files/i','src=$1'.Context::getRequestUri().'files', $matches[0]);
	}

	function triggerInsertDocument(&$obj)
	{
		if($obj->is_secret == "Y") return new Object();
		if(Context::get('act') == 'procMailingInsertMail') return new Object();
		$module_srl = $obj->module_srl;
		$moduleModel =& getModel('module');
		$targetModule = $moduleModel->getModuleInfoByModuleSrl($module_srl);
		if($targetModule->site_srl != 0)
		{
			$site_info = $moduleModel->getSiteInfo($targetModule->site_srl);
			if(isSiteID($site_info->domain)) {
				$vid = $site_info->domain;
			}
			else
			{
				$maildomain = $site_info->domain;
			}
		}
		$mid = $targetModule->mid;

		$oModel =& getModel('mailing');
		$mailingAddressesData = $oModel->getTargetAddresses($targetModule->module_srl);
		if(!$mailingAddressesData || count($mailingAddressesData) < 1 || !trim($obj->content)) return new Object();

		$oModuleModel =& getModel('module');
		$mailingConfig = $oModuleModel->getModuleConfig('mailing');
		if(!$mailingConfig->maildomain) $mailingConfig->maildomain = $_SERVER["SERVER_NAME"];
		if(!$maildomain) $maildomain = $mailingConfig->maildomain;
		$content = preg_replace_callback('/<img([^>]+)>/i',array($this,'replaceResourceRealPath'), $obj->content);

		$oMail = new Mail();
		if($obj->category_srl)
		{
			$oDocumentModel =& getModel('document');
			$category = $oDocumentModel->getCategory($obj->category_srl);
			$title = sprintf("[%s] %s", $category->title, $obj->title);
		}
		else
		{
			$title = $obj->title;
		}
		$oMail->setTitle($title);

		$content = sprintf("<a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $content);

		$oMail->setContent( $content ); 
		if(!$obj->email_address) $oMail->setSender($obj->nick_name, $obj->nick_name.'@noreply.com');
		else $oMail->setSender($obj->nick_name, $obj->email_address);
		$oMail->setMessageId( ($obj->document_srl)."@".$mailingConfig->maildomain );
		if($vid)
		{
			$mailingAddress = $vid.".".$mid."@".$maildomain;
		}
		else
		{
			$mailingAddress = $mid."@".$maildomain;
		}
		$oMail->setReplyTo( $mailingAddress );
		$oMail->setReceiptor($mailingAddress, $mailingAddress);
		$res = array();
		foreach($mailingAddressesData as $mailAddr)
		{
			if($mailAddr->member_email_address)
			{
                if(substr($mailAddr->member_email_address,-1)=='_') continue;
				$res[] = $mailAddr->member_email_address;
			}
			else
			{
                if(substr($mailAddr->email_address,-1)=='_') continue;
				$res[] = $mailAddr->email_address;
			}
		}

		$oMail->setBCC(implode(",", $res));
		$oMail->setAdditionalParams("-f ".$mailingAddress);
		$oMail->send();
	}

	function triggerDisplayMailingInfo(&$obj) {
		$oModuleModel = &getModel('module');

		if(!in_array($obj->module, array('board','kin','wiki','issuetracker')) || Context::getResponseMethod()!="HTML" || !$obj->grant->access || strpos($obj->act, 'Admin')>0) return new Object();
		$config = $oModuleModel->getModuleConfig('mailing');
		if($config->display_board_header!='Y') return new Object();

		$mailing_address = $obj->module_info->mid.'@'.$config->maildomain;
		$site_module_info = Context::get('site_module_info');

		if($obj->module_info->site_srl && $obj->module_info->site_srl == $site_module_info->site_srl) {
			if(isSiteID($site_module_info->domain)) {
				$mailing_address = $site_module_info->domain.'.'.$mailing_address;
			}
			else
			{
				$mailing_address = $obj->module_info->mid.'@'.$site_module_info->domain;
			}
		}

		Context::set('mailing_address', $mailing_address);

		if(Context::get('is_logged')) {
			$logged_info = Context::get('logged_info');
			unset($args);
			$args->member_srl = $logged_info->member_srl;
			$args->module_srl = $obj->module_info->module_srl;
			$output = executeQuery('mailing.getMemberJoined', $args);
			if($output->data->module_srl == $obj->module_info->module_srl)
			{
				Context::set('mailing_joined', true);
				Context::set('mailing_include_comment', $output->data->include_comment);
				Context::addJsFilter($this->module_path.'tpl/filter', 'configure_mailing.xml');
			}
			else
			{
				Context::set('mailing_joined', false);
			}
		}

		unset($args);
		$args->module_srl = $obj->module_info->module_srl;
		$output = executeQuery('mailing.getMailingCount',$args);
		Context::set('mailing_count', $output->data->count);

		$oTemplateHandler = new TemplateHandler();
		$output = $oTemplateHandler->compile('./modules/mailing/tpl', 'board_header.html');

		$obj->module_info->header_text .= $output;
		Context::set('module_info', $obj->module_info);
	}

	function procMailingCreateBoard() {
		global $lang;
		$oModuleController = &getController('module');

		if(!$this->module_srl || !$this->grant->create_board) return new Object(-1,'msg_invalid_request');
		$args->mid = Context::get('board_id');
		$args->browser_title = Context::get('browser_title');
		if(!$args->mid || !$args->browser_title) return new Object(-1,'msg_invalid_request');
		if(strlen($args->mid)<3) return new Object(-1,sprintf($lang->filter->outofrange, $lang->board_id). ' (3~10)');

		$args->skin = 'xe_official';
		$args->layout_srl = $this->module_info->layout_srl;
		$args->module = 'board';
		$output = $oModuleController->insertModule($args);
		if(!$output->toBool()) return $output;

		$module_srl = $output->get('module_srl');
		$skin_args->colorset = 'white';
		$skin_args->default_style = 'list';
		$skin_args->display_setup_button = 'Y';
		$skin_args->title = $args->browser_title;
		$skin_args->sub_title = 'devCafe';
		$oModuleController->insertModuleSkinVars($module_srl, $skin_args);

		$logged_info = Context::get('logged_info');
		$oModuleController->insertAdminId($module_srl,$logged_info->user_id);

		$this->setRedirectUrl(getFullUrl('','mid',$args->mid));

	}

	function procMailingInsertModuleConfig()
	{
		$module_srl = Context::get('target_module_srl');
		if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
		else $module_srl = array($module_srl);

		$mailing_config->write_permission = Context::get('write_permission');
		if(!$mailing_config->write_permission) $mailing_config->write_permission = "only_registered";

		$oModuleController = &getController('module');
		for($i=0;$i<count($module_srl);$i++) {
			$srl = trim($module_srl[$i]);
			if(!$srl) continue;
			$output = $oModuleController->insertModulePartConfig('mailing',$srl,$mailing_config);
		}
		$this->setMessage('success_updated');
	}

	function procMailingConfigureMailing()
	{
		$vars = Context::getRequestVars();
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new Object(-1, "msg_invliad_request");
		$vars->member_srl = $logged_info->member_srl;
		$output = executeQuery("mailing.updateConfig", $vars);
		if(!$output->toBool()) return $output;
		$this->setMessage('success_updated');
	}
}
?>
