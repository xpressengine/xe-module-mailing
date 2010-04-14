<?php
    class mailingView extends mailing 
    {
        function init()
        {
            $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            if(!is_dir($template_path)||!$this->module_info->skin) {
                $this->module_info->skin = 'xe_official';
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            }
            $this->setTemplatePath($template_path);
            $this->setTemplateFile(strtolower(str_replace('dispMailing','',$this->act)));

        }

        function dispMailingIndex()
        {
            $oDocumentModel = &getModel('document');
            $oCommentModel = &getModel('comment');

            Context::addJsFilter($this->module_path.'tpl/filter', 'join_mailing.xml');
            Context::addJsFilter($this->module_path.'tpl/filter', 'leave_mailing.xml');
            Context::addJsFilter($this->module_path.'tpl/filter', 'create_board.xml');

            $site_lang = array();
            $site_args->lang_code = Context::getLangType();
            $output = executeQueryArray('mailing.getUserLang', $site_args);
            if($output->data) {
                foreach($output->data as $key => $val) {
                    $site_lang[$val->site_srl][$val->name] = $val->value;

                }
            }

            $mids[0]->count = 0;

            $output = executeQueryArray('mailing.getModules');
            if(count($output->data)) {
                foreach($output->data as $key => $val) {
                    if(strpos($val->browser_title, '$user_lang->')!==false) {
                        $code = trim(substr($val->browser_title,12));
                        $t = $site_lang[$val->site_srl][$code];
                        if($t) $val->browser_title = $t;
                        else $val->browser_title = $code;
                    }
                    $mids[$val->site_srl]->count += $val->count;
                    $mids[$val->site_srl]->modules[$val->module_srl] = $val;

                    $module_srls[$val->module_srl] = $val->site_srl;
                }
            }

            if($module_srls) {
                $dargs->module_srls = implode(',',array_keys($module_srls));
                $output = executeQueryArray('mailing.getModuleLatestCount', $dargs);
                if($output->data) {
                    foreach($output->data as $key => $val) {
                        $mids[$val->site_srl]->modules[$val->module_srl]->regdate = $val->regdate;
                        $mids[$val->site_srl]->modules[$val->module_srl]->document_count = $val->document_count;
                        if($mids[$val->site_srl]->regdate < $val->regdate) $mids[$val->site_srl]->regdate = $val->regdate;
                    }
                }
            }


            if(count($mids)) {
                $args->site_srls = array_keys($mids);
                $output = executeQueryArray('mailing.getHomepageTitles', $args);
                if(count($output->data)) {
                    foreach($output->data as $key=>$val) {
                        if(strpos($val->title, '$user_lang->')!==false) {
                            $code = trim(substr($val->title,12));
                            $t = $site_lang[$val->site_srl][$code];
                            if($t) $val->title = $t;
                            else $val->title = $code;
                        }
                        if(!isset($mids[$val->site_srl])) continue;
                        $mids[$val->site_srl]->domain = $val->domain;
                        $mids[$val->site_srl]->title = $val->title;
                    }
                }
            }
            Context::set('mids', $mids);

            $joined_module_srls = array();
            if(Context::get('is_logged')) {
                $logged_info = Context::get('logged_info');
                unset($args);
                $args->member_srl = $logged_info->member_srl;
                $output = executeQueryArray('mailing.getMemberJoined', $args);
                if($output->data) {
                    foreach($output->data as $key => $val) {
                        if(!$mids[$val->site_srl]->modules[$val->module_srl]) continue;
                        $mids[$val->site_srl]->joined = true;
                        $mids[$val->site_srl]->modules[$val->module_srl]->joined = true;
                        $joined_module_srls[] = $val->module_srl;
                    }
                }
            }

            $except_module_srls = array();
            if(count($joined_module_srls)) {
                $except_module_srls = $joined_module_srls;
                unset($args);
                $args->module_srl = implode(',',$joined_module_srls);
                $args->list_count = $this->module_info->target_module_count?$this->module_info->target_module_count:5;
                $args->sort_index = 'update_order';
                $output = $oDocumentModel->getDocumentList($args, false, false);
                $document_list[-1]->list = $output->data;
            }

            if($this->module_info->target_module) {
                $module_srls = explode(',',$this->module_info->target_module);
                if($c = count($module_srls)) {
                    for($i=0;$i<$c;$i++) {
                        unset($args);
                        if(!in_array($module_srls[$i],$except_module_srls)) $except_module_srls[] = $module_srls[$i];
                        $args->module_srl = $module_srls[$i];
                        $args->list_count = $this->module_info->target_module_count?$this->module_info->target_module_count:5;
                        $args->sort_index = 'update_order';
                        $output = $oDocumentModel->getDocumentList($args, false, false);
                        $document_list[$module_srls[$i]]->list = $output->data;
                    }

                    if(count($document_list)) {
                        unset($args);
                        $args->module_srls = $this->module_info->target_module;
                        $output = executeQueryArray('mailing.getModulesInfo', $args);
                        if(count($output->data)) {
                            foreach($output->data as $key => $val) {
                                if(!count($document_list[$val->module_srl])) continue;

                                if(strpos($val->browser_title, '$user_lang->')!==false) {
                                    $code = trim(substr($val->browser_title,12));
                                    $t = $site_lang[$val->site_srl][$code];
                                    if($t) $val->browser_title = $t;
                                    else $val->browser_title = $code;
                                }

                                $document_list[$val->module_srl]->module_info = $val;
                            }
                        }
                    }
                }
            }

            unset($args);
            $args->list_count = $this->module_info->display_count?$this->module_info->display_count:20;
            if(count($except_module_srls)) $args->except_module_srls = implode(',',$except_module_srls);
            $output = executeQueryArray('mailing.getNewestDocuments', $args);
            if(count($output->data)) {
                foreach($output->data as $key => $attribute) {
                    $document_srl = $attribute->document_srl;
                    if(!$GLOBALS['XE_DOCUMENT_LIST'][$document_srl]) {
                        $oDocument = null;
                        $oDocument = new documentItem();
                        $oDocument->setAttribute($attribute, false);
                        $GLOBALS['XE_DOCUMENT_LIST'][$document_srl] = $oDocument;
                    }
                    $document_list[0]->list[] = $oDocument;
                }
            }

            if(count($document_list)) {
                $document_srls = array();
                foreach($document_list as $key => $val) {
                    if(!count($val->list)) continue;
                    foreach($val->list as $k => $v) {
                        if(!in_array($v->document_srl, $document_srls)) $document_srls[] = $v->document_srl;
                    }
                }
                if(count($document_srls)) {
                    unset($args);
                    $args->document_srls = implode(',',$document_srls);
                    $output = executeQueryArray('mailing.getCommentSrls', $args);
                    if(count($output->data)) {
                        $comment_srls = array();
                        foreach($output->data as $key => $val) {
                            $comment_srls[] = $val->comment_srl;
                        }
                    }
                }

                if(count($comment_srls)) {
                    $output = $oCommentModel->getComments($comment_srls);
                    $comment_list = array();
                    if(count($output)) {
                        foreach($output as $key => $val) {
                            $comment_list[$val->get('document_srl')] = $val;
                        }
                    }
                }
            }

            Context::set('document_list', $document_list);
            Context::set('comment_list', $comment_list);
            $oModel =& getModel('mailing');
            $oModel->getTargetAddresses(332);
        }
    }

?>
