<?php

    class mailPart {
        var $partData = null;
        var $path = null;

        function mailPart($partData, $path)
        {
            $this->partData = $partData;
            $this->path = $path;
        }

        function decodeBody(&$body)
        {
            if($this->partData["transfer-encoding"] == "quoted-printable")
            {
                $obj->body = quoted_printable_decode($body);
                $ret = Context::convertEncoding($obj);
                return $ret->body;
            }
            else if ($this->partData["transfer-encoding"] == 'base64') {
                $obj->body = base64_decode($body);
                $ret = Context::convertEncoding($obj);
                return $ret->body;
            }
            else
            {
                return $body;
            }
        }

        function get($key)
        {
            return $this->partData[$key];
        }

        function getBody()
        {
            if(!$this->partData) return null;

            $start = $this->partData['starting-pos-body'];
            $end = $this->partData['ending-pos-body'];
            $fp = fopen($this->path, "r");
            fseek($fp, $start, SEEK_SET);
            $body = fread($fp, $end-$start); 
            fclose($fp);
            $body = $this->decodeBody($body);
            return $body;
        }

        function writeToFile()
        {
            $filename = "./files/cache/mailing/".rand(10000,90000);
            $realpath = FileHandler::getRealPath($filename);

            $headers = $this->partData['headers'];
            $encoding = $headers['content-transfer-encoding'];
            $start = $this->partData['starting-pos-body'];
            $end = $this->partData['ending-pos-body'];
            $fp = fopen($this->path, "rb");
            fseek($fp, $start, SEEK_SET);
            $len = $end-$start;
       
            if($encoding == 'base64')
            {
                $whandle = fopen($realpath, 'w+b');
                $filter = stream_filter_append($whandle, 'convert.base64-decode', STREAM_FILTER_WRITE);
                $res = stream_copy_to_stream($fp, $whandle, $len);
                fclose($whandle);
            }
            else
            {
                $fileObject = FileHandler::openFile($realpath, "w");
                $written = 0;
                $write = 2048;
                while($written < $len) {
                    if (($written+$write > $len )) {
                        $write = $len - $written;
                    }
                    $part = fread($fp, $write);
                    $fileObject->write($this->decode($part, $encoding));
                    $written += $write;
                }
                $fileObject->close();
            }
            fclose($fp);
            return $filename;
        }

        function writeAttachments($document_srl, $module_srl, $member_srl)
        {
            $upload_target_srl = $document_srl;
            $file_info = array();
            $file_info['name'] = imap_utf8($this->get('content-name'));
            if(!$file_info['name']) return;
            $file_info['tmp_name'] = $this->writeToFile();

            $oFileController =& getController('file');
            $output = $oFileController->insertFile($file_info, $module_srl, $document_srl, 0, true);
            $uploaded_filename = $output->get('uploaded_filename');
            if($this->partData["content-id"])
            {
                $cid_array = Context::get('cid_array');
                if(!$cid_array) $cid_array = array();
                $cid_array[$this->partData["content-id"]] = $uploaded_filename;
                Context::set('cid_array', $cid_array);
            }
            else
            {
                $attachments = Context::get('attachments');
                if(!$attachments) $attachments = array();
                $attachments[$file_info['name']] = $uploaded_filename;
                Context::set('attachments', $attachments);
            }
            unlink(FileHandler::getRealPath($file_info['tmp_name']));

            return true;
        }

        function decode($encodedString, $encodingType) {
            if ($encodingType == 'base64') {
                $decodedString = base64_decode($encodedString);
                return $decodedString;
            } else if ($encodingType == 'quoted-printable') {
                return quoted_printable_decode($encodedString);
            } else {
                return $encodedString;
            }
        }
    }

    class htmlPart extends mailPart {
        function getBody()
        {
            $body = parent::getBody(); 
            preg_match("/<body[^>]*>(.+)<\/body>/siU", $body, $match);
            if($match) return $match[1];
            return $body;
        }
    }

    class multiPart extends mailPart {
        var $children = array();

        function addChild($partId, &$part)
        {
            $this->children[$partId] = $part;
        }

        function getBody()
        {
            foreach($this->children as $child)
            {
                return $child->getBody();
            }
            return null;
        }

        function writeAttachments($document_srl, $module_srl, $member_srl)
        {
            $res = array();
            $bFirst = true;
            foreach($this->children as $child)
            {
                $res = $child->writeAttachments($document_srl, $module_srl, $member_srl);
            }
        }
    }

    class multiPartAlternative extends multiPart {
        
        function getBody()
        {
            foreach($this->children as $part) {
				if ($part instanceof multiPart) {
					return $part->getBody();
				}
                else if ($part->get('content-type') == "text/html") {
                    return $part->getBody();
                }
            }
        }

        function writeAttachments($document_srl, $module_srl, $member_srl)
        {
            foreach($this->children as $part) {
				if ($part instanceof multiPart) {
					$res = $part->writeAttachments($document_srl, $module_srl, $member_srl);
				}
			}
        }
    }

    class mailPartFactory {
        function createMailPart(&$part, $path)
        {
            if(substr($part["content-type"], 0, 9) == "multipart")
            {
                if($part["content-type"] == "multipart/alternative")
                {
                    $obj =& new multiPartAlternative($part, $path);
                    return $obj;
                }
                else
                {
                    $obj =& new multiPart($part, $path);
                    return $obj;
                }
            }
            else if ($part["content-type"] == "text/html")
            {
                $obj =& new htmlPart($part, $path);
                return $obj;
            }
            else
            {
                $obj =& new mailPart($part, $path);
                return $obj;
            }
        }
    }

    class mailMessage {

        var $mailResource = null;
        var $subject = null;
        var $from = null;
        var $to = null;
        var $parts = array();
        var $body = null;
        var $path = null;
        var $mainPart = null;
        var $inreplyto = null;
        var $references = null;
        var $messageId = null;
		var $cc = null;
        
        function mailMessage($path)
        {
            $this->mailResource = mailparse_msg_parse_file($path);
            $this->path = $path;
        }

        function getParentId($partId)
        {
            if(!strchr($partId, ".")) return null;
            $arr = explode(".", $partId);
            array_pop($arr);
            return implode(".", $arr);
        }

        function getBody()
        {
            if($this->mainPart)
            {
                return str_replace("\r", "", $this->mainPart->getBody());
            }
            else
            {
                return null;
            }
        }

        function parse()
        {
            $structure = mailparse_msg_get_structure($this->mailResource);

            foreach($structure as $partId)
            {
                $part = mailparse_msg_get_part($this->mailResource, $partId);
                unset($data);
                $data = mailparse_msg_get_part_data($part);
                $partObj = mailPartFactory::createMailPart($data, $this->path);
                $this->parts[$partId] = $partObj;
                $parentId = $this->getParentId($partId);
                if($parentId)
                {
                    $this->parts[$parentId]->addChild($partId, $partObj);
                }
            }
            $this->mainPart =& $this->parts[1];
            $headers = $this->mainPart->get("headers");

            $this->subject = imap_utf8($headers['subject']); 
            $this->to = imap_utf8($headers['to']);
            $this->from = imap_utf8($headers['from']);
			if($headers['cc'])
				$this->cc = imap_utf8($headers['cc']);
            $this->inreplyto = $headers['in-reply-to'];
            $this->references = $headers['references'];
            $this->messageId = $headers['message-id'];
        }

        function close()
        {
            if($this->mailResource) {
                mailparse_msg_free($this->mailResource);
                $this->mailResource = null;
            }
        }

        function procWriteAttachments($document_srl, $module_srl, $member_srl)
        {
            $this->mainPart->writeAttachments($document_srl, $module_srl, $member_srl);
        }
    }

?>
