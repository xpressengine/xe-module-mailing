<?php
    class mailingAdminView extends mailing 
    {
        function init() 
        {
            $oModuleModel = &getModel('module');
            $module_category = $oModuleModel->getModuleCategories();
            Context::set('module_category', $module_category);

            $this->setTemplatePath(sprintf("%stpl/",$this->module_path));
            $this->setTemplateFile(strtolower(str_replace('dispMailingAdmin','',$this->act)));

            $module_srl = Context::get('module_srl');
            if($module_srl) {
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
                if(!$module_info) {
                    Context::set('module_srl','');
                    $this->act = 'dispMailingAdminIndex';
                } else {
                    ModuleModel::syncModuleToSite($module_info);
                    $this->module_info = $module_info;
                    Context::set('module_info',$module_info);
                }
            }
            Context::set('module_srl', $this->module_info->module_srl);
        }

        function dispMailingAdminIndex() 
        {
            $args->sort_index = "module_srl";
            $args->page = Context::get('page');
            $args->list_count = 20;
            $args->page_count = 10;
            $args->s_module_category_srl = Context::get('module_category_srl');
            $output = executeQueryArray('mailing.getMailingList', $args);
            ModuleModel::syncModuleToSite($output->data);

            $oModuleModel =& getModel('module');
            $mailingConfig = $oModuleModel->getModuleConfig('mailing');
            Context::set('mailingConfig', $mailingConfig);

            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('mailing_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);
        }

        function dispMailingAdminInsert()
        {
            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);

            $oLayoutMode = &getModel('layout');
            $layout_list = $oLayoutMode->getLayoutList();
            Context::set('layout_list', $layout_list);

            if($this->module_info->target_module) {
                $args->module_srls = $this->module_info->target_module;
                $output = executeQueryArray('module.getModulesInfo', $args);
                Context::set('target_modules', $output->data);
            }
        }

        function dispMailingAdminSkin()
        {
            $oModuleAdminModel = &getAdminModel('module');
            $skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
            Context::set('skin_content', $skin_content);
        }

        function dispMailingAdminGrant()
        {
            $oModuleAdminModel = &getAdminModel('module');
            $grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
            Context::set('grant_content', $grant_content);
        }

    }
?>
