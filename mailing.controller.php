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
            preg_match("/<div[^>]*>(.+)<div style=\'border/siU", $body, $match);
            if($match) return $match[1];
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
            $mailMessage = new mailMessage($message['tmp_name']);
            $mailMessage->parse();
            if($mailMessage->messageId)
            {
                preg_match("/@(.+)>$/i", $mailMessage->messageId, $matches);
                if($matches[1] == $_SERVER["SERVER_NAME"])
                {
                    return;
                }
            }

            $fromAddress = mailparse_rfc822_parse_addresses($mailMessage->from);
            $fromAddress = array_shift($fromAddress);
            $fromAddress = $fromAddress["address"];

            $memberModel =& getModel('member');
            $member_srl = $memberModel->getMemberSrlByEmailAddress($fromAddress);
            if(!$member_srl) 
            {
                $mailMessage->close();
                return;
            }
            $member_info = $memberModel->getMemberInfoByMemberSrl($member_srl);

			$oModuleModel =& getModel('module');
            $mailingConfig = $oModuleModel->getModuleConfig('mailing');
            $maildomain = $mailingConfig->maildomain;
            if(!$maildomain)
            {
                $maildomain = $_SERVER["SERVER_NAME"];
            }

            $toAddress = mailparse_rfc822_parse_addresses($mailMessage->to);
			foreach($toAddress as $objAddress)
			{
				$obj = $objAddress["address"];
				$mailingAddress = $obj;
				$mid = explode("@", $obj);
				if($mid[1] != $maildomain) continue;
				$mid = array_shift($mid);
				$mid = explode(".", $mid);
				$site_srl = 0;
				$moduleModel =& getModel('module');
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
            $oModel =& getModel('mailing');
            if(!$oModel->isMember($targetModule->module_srl, $fromAddress, $member_info->member_srl))
            {
                $mailMessage->close();
                return;
            }
            $oMemberController = &getController('member');
            $oMemberController->doLogin($member_info->user_id);

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
                $obj->title = $mailMessage->subject;
                $obj->content = $this->replaceCid($mailMessage->getBody());
                $obj->module_srl = $targetModule->module_srl;
                $oDocumentController = &getController('document');
                $output = $oDocumentController->insertDocument($obj, true);
                $content = sprintf("<a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $obj->content);
                $mailMessage->close(); 
            }

            {
                $oMail = new Mail();
                $oMail->setTitle($obj->title);
                $oMail->setContent( $this->replaceCid($content, true) ); 
                $oMail->setSender($obj->user_name, $obj->email_address);
                $oMail->setMessageId( ($obj->comment_srl?$obj->comment_srl:$obj->document_srl)."@".$_SERVER["SERVER_NAME"] );
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
                $oMail->send();
            }
        }

        function triggerInsertComment(&$obj)
        {
            $moduleModel =& getModel('module');
            $module_srl = $obj->module_srl;
            $targetModule = $moduleModel->getModuleInfoByModuleSrl($module_srl);
            if($targetModule->site_srl != 0)
            {
                $site_info = $moduleModel->getSiteInfo($targetModule->site_srl);
                $vid = $site_info->domain;
            }
            $mid = $targetModule->mid;
            $oModel =& getModel('mailing');
            $mailingAddressesData = $oModel->getTargetAddresses($targetModule->module_srl);
            if(!$mailingAddressesData || count($mailingAddressesData) < 1) return;
            $content = preg_replace_callback('/<img([^>]+)>/i',array($this,'replaceResourceRealPath'), $obj->content);
            $content = sprintf("<a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, $content);
            $oModuleModel =& getModel('module');
            $mailingConfig = $oModuleModel->getModuleConfig('mailing');
            $maildomain = $mailingConfig->maildomain;
            if(!$maildomain)
            {
                $maildomain = $_SERVER["SERVER_NAME"];
            }

            $oDocumentModel =& getModel('document');
            $oDocument = $oDocumentModel->getDocument($obj->document_srl);

            $oMail = new Mail();
            $oMail->setTitle("RE: ".$oDocument->getTitleText());

            $oMail->setContent( $content ); 
            $oMail->setSender($obj->user_name, $obj->email_address);
            $oMail->setMessageId( $obj->comment_srl."@".$_SERVER["SERVER_NAME"] );
            $oMail->setReferences( $obj->parent_srl?$obj->parent_srl:$obj->document_srl."@".$_SERVER["SERVER_NAME"] );
            if($targetModule->site_srl != 0)
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
                    $res[] = $mailAddr->member_email_address;
                }
                else
                {
                    $res[] = $mailAddr->email_address;
                }
            }
            $oMail->setBCC(implode(",", $res));
            $oMail->send();
        }

        function replaceResourceRealPath($matches) {
            return preg_replace('/src=(["\']?)\.\/files/i','src=$1'.Context::getRequestUri().'files', $matches[0]);
        }

        function triggerInsertDocument(&$obj)
        {
            $module_srl = $obj->module_srl;
            $moduleModel =& getModel('module');
            $targetModule = $moduleModel->getModuleInfoByModuleSrl($module_srl);
            if($targetModule->site_srl != 0)
            {
                $site_info = $moduleModel->getSiteInfo($targetModule->site_srl);
                $vid = $site_info->domain;
            }
            $mid = $targetModule->mid;

            $oModel =& getModel('mailing');
            $mailingAddressesData = $oModel->getTargetAddresses($targetModule->module_srl);
            if(!$mailingAddressesData || count($mailingAddressesData) < 1) return;

            $oModuleModel =& getModel('module');
            $mailingConfig = $oModuleModel->getModuleConfig('mailing');
            $maildomain = $mailingConfig->maildomain;
            if(!$maildomain)
            {
                $maildomain = $_SERVER["SERVER_NAME"];
            }
            $content = preg_replace_callback('/<img([^>]+)>/i',array($this,'replaceResourceRealPath'), $obj->content);

            $oMail = new Mail();
            $oMail->setTitle($obj->title);

            $content = sprintf("<a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $content);

            $oMail->setContent( $content ); 
            $oMail->setSender($obj->user_name, $obj->email_address);
            $oMail->setMessageId( ($obj->document_srl)."@".$_SERVER["SERVER_NAME"] );
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
                    $res[] = $mailAddr->member_email_address;
                }
                else
                {
                    $res[] = $mailAddr->email_address;
                }
            }

            $oMail->setBCC(implode(",", $res));
            $oMail->send();
        }

        function triggerDisplayMailingInfo(&$obj) {
            $oModuleModel = &getModel('module');

            if($obj->module != 'board' || Context::getResponseMethod()!="HTML" || !$obj->grant->access || strpos($obj->act, 'Admin')>0) return new Object();
            $config = $oModuleModel->getModuleConfig('mailing');
            if($config->display_board_header!='Y') return new Object();
                    
            $mailing_address = $obj->module_info->mid.'@'.$config->maildomain;
            $site_module_info = Context::get('site_module_info');

            $site_module_info = Context::get('site_module_info');
            if($obj->module_info->site_srl && $obj->module_info->site_srl == $site_module_info->site_srl) $mailing_address = $site_module_info->domain.'.'.$mailing_address;

            Context::set('mailing_address', $mailing_address);

            if(Context::get('is_logged')) {
                $logged_info = Context::get('logged_info');
                unset($args);
                $args->member_srl = $logged_info->member_srl;
                $args->module_srl = $obj->module_info->module_srl;
                $output = executeQuery('mailing.getMemberJoined', $args);
                Context::set('mailing_joined', $output->data->module_srl==$obj->module_info->module_srl?true:false);
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

    }
?>
