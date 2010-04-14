<?php
    class mailingAdminController extends mailing 
    {
        function init() 
        {
        }

        function procMailingAdminInsert() 
        {
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            $args = Context::getRequestVars();
            $args->module = 'mailing';
            $args->mid = $args->mailing_name;
            unset($args->body);
            unset($args->mailing_name);

            $args->use_category = 'N';
            if($args->display_all != 'Y') $args->display_all = 'N';

            if($args->module_srl) {
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
                if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
            }

            if(!$args->module_srl) {
                $output = $oModuleController->insertModule($args);
                $msg_code = 'success_registed';
            } else {
                $output = $oModuleController->updateModule($args);
                $msg_code = 'success_updated';
            }

            if(!$output->toBool()) return $output;

            $this->setMessage($msg_code);
            $this->add('module_srl', $output->get('module_srl'));
        }

        function procMailingAdminDelete() 
        {
            $oModuleController = &getController('module');

            $module_srl = Context::get('module_srl');

            $output = $oModuleController->deleteModule($module_srl);
            if(!$output->toBool()) return $output;

            $this->setMessage('success_deleted');
        }

        function procMailingAdminConfig()
        {
            $maildomain = Context::get('maildomain');
            $display_board_header = Context::get('display_board_header');
            $config->maildomain = $maildomain;
            $config->display_board_header = $display_board_header;
            $oModuleController =& getController('module');
            $oModuleController->insertModuleConfig('mailing', $config);
            $this->setMessage('success_updated');
        }

    }
?>
