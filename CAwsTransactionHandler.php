<?php
include_once('IClickatellTransactionHandler.php');
include('aws.phar');

use Aws\Sqs\SqsClient;

Class CAwsTransactionHandler implements IClickatellTransactionHandler {

    private $_aws_key;
    private $_aws_secret;
    private $_aws_region;
    private $_aws_read_queue;
    private $_aws_read_queue_url;

    private $_aws_client;

    /**  
     * constructor
     */
    public function __construct($aws_key, $aws_secret, $aws_region, $aws_read_queue) {
        $this->_aws_region = $aws_region;
        $this->_aws_key = $aws_key;
        $this->_aws_secret = $aws_secret;
        $this->_aws_read_queue = $aws_read_queue;

        $this->_aws_client = SqsClient::factory(array(
                    'key'    => $this->_aws_key,
                    'secret' => $this->_aws_secret,
                    'region' => $this->_aws_region
                    ));

        $queueUrl = $this->_aws_client->getQueueUrl(array('QueueName'=>$this->_aws_read_queue));
        $this->_aws_read_queue_url = $queueUrl->get('QueueUrl');
    }

    /**
     * Must return array
     *  uid => unique request id
     *  request_data => array
     */
    public function getNextRequest() {
        $this->logLine('Checking for a new request on AWS queue');
        $ret = NULL;

        $result = $this->_aws_client->receiveMessage(array(
                    'QueueUrl' => $this->_aws_read_queue_url
                    ));

        if(!$result) { return FALSE; }

        $messages = $result->get('Messages');
        if(is_array($messages)) {
            $message = array_shift($messages);
            $ret['uid'] = $message['ReceiptHandle'];
            $ret['request_data'] = array();

            $jdata = json_decode($message['Body'], TRUE);
            if($jdata) {
              $this->logLine('The request message has been decoded');
              $ret['request_data'] = $jdata;
            } else {
              $this->logLine('The request message is not in JSON format');
            }
        }

        return $ret;
    }

    /**
     * This function will be called when we get a success notification from the network
     */
    public function requestSucceess($uid, $request_data) {
        $this->logLine('Request was successfull: ' . $uid);

        $this->_aws_client->deleteMessage(array(
                    'QueueUrl' => $this->_aws_read_queue_url,
                    'ReceiptHandle' => $uid
                    ));

        return TRUE;
    }

    /**
     * This function will be called when we get a failure notification from the network
     */
    public function requestFailure($uid, $request_data) {
        $this->logLine('Request failed: ' . $uid);

        $this->_aws_client->deleteMessage(array(
                    'QueueUrl' => $this->_aws_read_queue_url,
                    'ReceiptHandle' => $uid
                    ));

        return TRUE;
    }

    /**
     * Log a line to the console
     */
    public function logLine($line) {
        echo 'AWSHandler: ' . date('Y-m-d H:i:s') .' ';
        echo $line . "\n";
    }

}