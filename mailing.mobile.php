<?php
	require_once(_XE_PATH_.'modules/mailing/mailing.view.php');

	class mailingMobile extends mailingView {

		var $tabs = array();

		function init()
		{
			// 모바일 스킨 설정
            $template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
            if(!is_dir($template_path)||!$this->module_info->mskin) {
                $this->module_info->mskin = 'default';
                $template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
            }
            $this->setTemplatePath($template_path);

			// 기본 탭 지정 (모바일 페이지 상단에 표시 됨. 로그인 유무에 따라 달라짐)
			if(Context::get('is_logged')) $this->tabs['index'] = Context::getLang('mailing_joined_newest_documents');
			$this->tabs['total'] = Context::getLang('mailing_newest_documents');

			// 대상 mid 추출 (관리자 지정 mid 에 대한 값도 여기서 추출)
            $site_lang = array();
            $site_args->lang_code = Context::getLangType();
            $output = executeQueryArray('mailing.getUserLang', $site_args);
            if($output->data) {
                foreach($output->data as $key => $val) {
                    $site_lang[$val->site_srl][$val->name] = $val->value;

                }
            }
            $mids[0]->count = 0;

			$selected_modules = explode(',',$this->module_info->target_module);

			$mid_titles = array();

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

					unset($sobj);
					$mid_titles[$val->module_srl] = $val->browser_title;

					if(in_array($val->module_srl, $selected_modules)) $this->tabs[$val->module_srl] = $val->browser_title;
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
            Context::set('mid_titles', $mid_titles);

			Context::set('tabs', $this->tabs);
		}

		function dispMailingIndex() {
            $oDocumentModel = &getModel('document');
            $oCommentModel = &getModel('comment');

			$mids = Context::get('mids');

			$target = Context::get('target');
			if(!$target) $target = 'index';
			elseif(!in_array($target, array_keys($this->tabs))) $target = 'index';
			if(!Context::get('is_logged')&&$target == 'total') $target = 0;
			Context::set('target', $target);

			$document_list = array();

			switch($target) {
				// 로그인한 회원이 가입한 메일링 최신 글
				case 'index' :
						$logged_info = Context::get('logged_info');
						unset($args);
						$args->member_srl = $logged_info->member_srl;
						$output = executeQueryArray('mailing.getMemberJoined', $args);
						if(!$output->data) break;

						foreach($output->data as $key => $val) {
							if(!$mids[$val->site_srl]->modules[$val->module_srl]) continue;
							$mids[$val->site_srl]->joined = true;
							$mids[$val->site_srl]->modules[$val->module_srl]->joined = true;
							$joined_module_srls[] = $val->module_srl;
						}

						if(!count($joined_module_srls)) break;

						unset($args);
						$args->module_srl = implode(',',$joined_module_srls);
						$args->list_count = 20;
						$args->sort_index = 'update_order';
						$output = $oDocumentModel->getDocumentList($args, false, false);

						$document_list = $output->data;
					break;

				// 전체 최신글
				case 'total' :
						unset($args);
						$args->list_count = 20;
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
								$document_list[] = $oDocument;
							}
						}
					break;

				// 관리자가 선택한 메일링 글
				default :
						unset($args);
						$args->module_srl = $target;
						$args->list_count = 20;
						$args->sort_index = 'update_order';
						$output = $oDocumentModel->getDocumentList($args, false, false);

						$document_list = $output->data;
					break;
			}


            if(count($document_list)) {
                $document_srls = array();
                foreach($document_list as $key => $val) {
					if(!in_array($val->document_srl, $document_srls)) $document_srls[] = $val->document_srl;
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

			$this->setTemplateFile('list.html');
		}

		function dispMailingCategory()
		{
			$this->setTemplateFile('category.html');
		}
	}


?>
