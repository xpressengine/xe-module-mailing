<?php

    class mailingModel extends mailing {

        function init() {
        }

        function isMember($module_srl, $email_address, $member_srl)
        {
            $args->module_srl = $module_srl;
            $args->email_address = $email_address;
            $args->member_srl = $member_srl;
            $output = executeQueryArray("mailing.isMember", $args);
            if($output->data) return true;
            else return false;
        }

        function getTargetAddresses($module_srl) {
            if(!$module_srl) return array();
            $args->module_srl = $module_srl;
            $output = executeQueryArray("mailing.getTargetAddresses", $args);
            if(!$output->toBool() || !$output->data) return array();
            return $output->data;
        }

        function getModuleList($member_srl)
        {
            $output = executeQueryArray("board.getModuleList");
            $boards = array();
            $res = array();
            foreach($output->data as $board)
            {
                $boards[] = $board->module_srl;
                $board->joined = "N";
                $res[$board->module_srl] = $board;
            }

            $args->module_srl = implode(",", $boards);
            $args->member_srl = $member_srl;
            $output2 = executeQueryArray("mailing.getMemberJoined", $args);
            if(!$output2->data) $output2->data = array();
            foreach($output2->data as $data)
            {
                $res[$data->module_srl]->joined = "Y";
            }

            return $res;
        }
    }
?>
