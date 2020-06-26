<?php


class Ame_Amepayment_Helper_Dbame extends Mage_Core_Helper_Abstract{

    public function getReadDB(){
        $resource = Mage::getSingleton('core/resource');
        return $resource->getConnection('core_read');
    }

    public function getWriteDB(){
        $resource = Mage::getSingleton('core/resource');
        return $resource->getConnection('core_write');
    }

    public function insertRefund($ame_order_id,$refund_id,$operation_id,$amount,$status){
        $transaction_id = $this->getTransactionIdByOrderId($ame_order_id);
        $sql = "INSERT INTO ame_refund (ame_transaction_id,refund_id,operation_id,amount,status,created_at,refunded_at)
                VALUES ('".$transaction_id."','".$refund_id."','".$operation_id."',".$amount.",'".$status."',NOW(),NOW())";
        $resourceDb = $this->getWriteDB();
        $resourceDb->query($sql);
        return true;
    }

    public function refundIdExists($refund_id){
        $sql = "SELECT refund_id FROM ame_refund WHERE refund_id = '".$refund_id."'";
        $resourceDb = $this->getReadDB();
        $result = $resourceDb->fetchOne($sql);
        if($result){
            return true;
        }
        else{
            return false;
        }
    }

    public function transactionIdExists($magento_increment_id){
        $sql = "select at.ame_transaction_id from ame_transaction as at 
                LEFT JOIN ame_order as ao ON at.ame_order_id = ao.ame_id
                where ao.increment_id = '{$magento_increment_id};'";
        $resourceDb = $this->getReadDB();
        $result = $resourceDb->fetchOne($sql);
        if($result){
            return true;
        }
        else{
            return false;
        }
    }

    public function insertOrder($order,$result_array){
        $sql = "INSERT INTO ame_order (increment_id,ame_id,amount,cashback_amount,
                       qr_code_link,deep_link)
                VALUES (" . $order->getIncrementId() . ",'" . $result_array['id'] . "',
                        " . $result_array['amount'] . ",
                        0,
                        '" . $result_array['qrCodeLink'] . "',
                        '" . $result_array['deepLink'] . "')";
        $resourceDb = $this->getWriteDB();
        $resourceDb->query($sql);
    }

    public function updateToken($expires_in,$token){
        $sql = "UPDATE ame_config SET ame_value = '" . $expires_in . "'WHERE ame_option = 'token_expires'";
        $resourceDb = $this->getWriteDB();
        $resourceDb->query($sql);
        $sql = "UPDATE ame_config SET  ame_value = '" . $token . "' WHERE ame_option = 'token_value'";
        $resourceDb->query($sql);
    }

    public function getToken(){
        $sql = "SELECT ame_value FROM ame_config WHERE ame_option = 'token_expires'";
        $resourceDb = $this->getReadDB();
        $token_expires = $resourceDb->fetchOne($sql);
        $sql = "SELECT ame_value FROM ame_config WHERE ame_option = 'token_value'";
        if (time() + 600 < $token_expires) {
            $token = $resourceDb->fetchOne($sql);
            Mage::log("ameRequest getToken returns: " . $token);
            return $token;
        }
        return false;
    }

    public function getTransactionAmount($ame_transaction_id){
        $sql = "SELECT amount FROM ame_transaction WHERE ame_transaction_id = '".$ame_transaction_id."'";
        $resourceDb = $this->getReadDB();
        return $resourceDb->fetchOne($sql);
    }

    public function getTransactionIdByOrderId($ame_order_id){
        $sql = "SELECT ame_transaction_id FROM ame_transaction WHERE ame_order_id = '".$ame_order_id."'";
        $resourceDb = $this->getReadDB();
        return $resourceDb->fetchOne($sql);
    }

    public function insertTransactionSplits($transaction_array){
        $splits = $transaction_array['splits'];
        $array_keys = array('id','date','amount','status','cashType');
        foreach($splits as $split) {
            $sql = file_get_contents(__DIR__ . "/SQL/inserttransactionsplit.sql");
            if(array_key_exists('id',$transaction_array)) {
                $sql = str_replace('[AME_TRANSACTION_ID]', $transaction_array['id'], $sql);
            }
            if(array_key_exists('id',$split)) {
                $sql = str_replace('[AME_TRANSACTION_SPLIT_ID]', $split['id'], $sql);
            }
            if(array_key_exists('date',$split)) {
                $sql = str_replace('[AME_TRANSACTION_SPLIT_DATE]', json_encode($split['date']), $sql);
            }
            if(array_key_exists('amount',$split)) {
                $sql = str_replace('[AMOUNT]', $split['amount'], $sql);
            }
            if(array_key_exists('status',$split)) {
                $sql = str_replace('[STATUS]', $split['status'], $sql);
            }
            if(array_key_exists('cashType',$split)) {
                $sql = str_replace('[CASH_TYPE]', $split['cashType'], $sql);
            }
            $others = [];
            foreach($split as $key => $value){
                if(!in_array($key,$array_keys)){
                    $others[$key] = $value;
                }
            }
            $others_json = json_encode($others);
            $sql = str_replace('[OTHERS]', $others_json, $sql);
            $resourceDb = $this->getWriteDB();
            $resourceDb->query($sql);
        }
        return true;
    }

    public function insertTransaction($transaction_array){
        $sql = file_get_contents(__DIR__ . "/SQL/inserttransaction.sql");
        $sql = str_replace('[AME_ORDER_ID]',$transaction_array['attributes']['orderId'],$sql);
        $sql = str_replace('[AME_TRANSACTION_ID]',$transaction_array['id'],$sql);
        $sql = str_replace('[AMOUNT]',$transaction_array['amount'],$sql);
        $sql = str_replace('[STATUS]',$transaction_array['status'],$sql);
        $sql = str_replace('[OPERATION_TYPE]',$transaction_array['operationType'],$sql);
        $resourceDb = $this->getWriteDB();
        $resourceDb->query($sql);
        $this->insertTransactionSplits($transaction_array);
        return true;
    }

    public function getAmeIdByIncrementId($incrementId){
        $sql = "SELECT ame_id FROM ame_order WHERE increment_id = '".$incrementId."'";
        $resourceDb = $this->getReadDB();
        return $resourceDb->fetchOne($sql);
    }

    public function getFirstPendingTransactions($num){
        $sql = file_get_contents("SQL/getfirstpendingtransactions.sql");
        $sql = str_replace("[LIMIT]",$num,$sql);
        $resourceDb = $this->getReadDB();
        return $resourceDb->fetchAssoc($sql);
    }

    public function insertCallback($json){
        $sql = "INSERT INTO ame_callback (json,created_at) VALUES ('".$json."',NOW())";
        $resourceDb = $this->getWriteDB();
        $resourceDb->query($sql);
    }

    public function getOrderIncrementId($ameid){
        $sql = "SELECT increment_id FROM ame_order WHERE ame_id = '".$ameid."'";
        $resourceDb = $this->getReadDB();
        return $resourceDb->fetchOne($sql);
    }
}
