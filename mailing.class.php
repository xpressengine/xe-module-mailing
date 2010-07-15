<?php

    class mailing extends ModuleObject {

        function moduleInstall() {
            $oDB = &DB::getInstance();
            $oDB->addIndex("mailing_members", "unique_email", array("module_srl", "email_address"), true);
            return new Object();
        }

        function checkUpdate() {
            $oModuleModel =& getModel('module');
            if(!$oModuleModel->getTrigger('document.insertDocument', 'mailing', 'controller', 'triggerInsertDocument', 'after')) return true;
            if(!$oModuleModel->getTrigger('comment.insertComment', 'mailing', 'controller', 'triggerInsertComment', 'after')) return true;
            if(!$oModuleModel->getTrigger('moduleHandler.proc', 'mailing', 'controller', 'triggerDisplayMailingInfo', 'after')) return true;
			if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'mailing', 'view', 'triggerDispMailingAdditionSetup', 'before')) return true;

            $oDB = &DB::getInstance();
			if(!$oDB->isColumnExists("mailing_members", "include_comment")) return true;

            return false;
        }

        function moduleUpdate() {
            $oModuleModel =& getModel('module');
            $oModuleController =& getController('module');
            if(!$oModuleModel->getTrigger('document.insertDocument', 'mailing', 'controller', 'triggerInsertDocument', 'after')) {
                $oModuleController->insertTrigger('document.insertDocument', 'mailing', 'controller', 'triggerInsertDocument', 'after');
            }
            if(!$oModuleModel->getTrigger('comment.insertComment', 'mailing', 'controller', 'triggerInsertComment', 'after')) {
                $oModuleController->insertTrigger('comment.insertComment', 'mailing', 'controller', 'triggerInsertComment', 'after');
            }
            if(!$oModuleModel->getTrigger('moduleHandler.proc', 'mailing', 'controller', 'triggerDisplayMailingInfo', 'after')) {
                $oModuleController->deleteTrigger('ModuleHandler.proc', 'mailing', 'controller', 'triggerDisplayMailingInfo', 'after');
                $oModuleController->insertTrigger('moduleHandler.proc', 'mailing', 'controller', 'triggerDisplayMailingInfo', 'after');
            }
			if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'mailing', 'view', 'triggerDispMailingAdditionSetup', 'before')) {
				$oModuleController->insertTrigger('module.dispAdditionSetup', 'mailing', 'view', 'triggerDispMailingAdditionSetup', 'before');
			}

            $oDB = &DB::getInstance();
			if(!$oDB->isColumnExists("mailing_members", "include_comment")) {
				$oDB->addColumn("mailing_members", "include_comment", "char", 1, "A");
			}

            return new Object(0, 'success_updated');
        }

        function recompileCache() {
        }
    }
?>
