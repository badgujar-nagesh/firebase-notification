<?php

/**
 * Class used to send push notification to android and iOS for FireBase.
 * @author  Nagesh Badgujar
 */
class Notification {

    /**
     * represents the sender of the Norification.
     */
    private $sender;
    
    /**
     * represents the text of notification.
     */
    private $text;
    
    /**
     *represents the subject of the notification.
     */
    private $subject;
    
    /**
     * represents the Firebase Server Key.
     */
    private $serverKey;
    
    /**
     *used to store all the android users.
     */
    private $_androidUsers;
    
    /**
     *used to store all the ios users.
     */
    private $_iosUsers;
    
    /**
     * header to send with curl.
     */
    private $headers;
    
    /**
     * URL of firebase.
     */
    private $url = 'https://fcm.googleapis.com/fcm/send';
    
    /**
     * Limit for sending multiple notifications at once.
     */
    private $limit;
    
    /**
     * Response of all the IDs to which the successful notification sent.
     */
    private $response;

    public function __construct($key) {
        $this->serverKey = $key;
        $this->limit = 1000;
        $this->flagArray = array();
        $this->setHeader();
    }

    /**
     * sets the users according to device types
     * @param type $receivers  users array directly from database
     * @return $this
     */
    public function setReceivers($receivers) {
        if (empty($receivers)) {
            return $this;
        }

        foreach ($receivers as $receiver) {
            if ($receiver['device_type'] == 'A') {
                $this->_androidUsers[] = array('id' => $receiver['id'], 'firebase_token' => $receiver['firebase_token']);
            } else if ($receiver['device_type'] == 'I') {
                $this->_iosUsers[] = array('id' => $receiver['id'], 'firebase_token' => $receiver['firebase_token']);
            }
        }
        return $this;
    }

    /**
     * sets the limit for sending the multiple notifications batch.
     * @param type $limit
     * @return $this
     */
    public function setLimit($limit) {
        $this->limit = $limit;
        return $this;
    }

    /**
     * sets the sender of notification
     * @param type $sender
     * @return $this
     */
    public function setSender($sender) {
        $this->sender = $sender;
        return $this;
    }

    /**
     * sets the notification text.
     * @param type $text
     * @return $this
     */
    public function setText($text) {
        $this->text = $text;
        return $this;
    }

    /**
     * sets the notification subject.
     * @param type $text
     * @return $this
     */
    public function setSubject($subject) {
        $this->subject = $subject;
        return $this;
    }

    /**
     * sends the notifications.
     */
    public function sendMultiple() {

        $this->sendMultipleToAndroid();
        $this->sendMultipleToIphone();

        return $this->response;
    }

    /**
     * send notification to Android devices.
     */
    public function sendToAndroid($token) {

        $fields = array(
            'to' => $token,
            'notification' => array('title' => $this->subject, 'text' => $this->text),
        );

        return $this->curl($fields);
    }

    /**
     * send notification to iPhone devices.
     */
    public function sendToIphone($token) {

        $fields = array(
            'to' => $token,
            'notification' => array(
                'body' => $this->text,
                'title' => $this->subject,
                'sound' => "default",
            )
        );

        return $this->curl($fields);
    }

    /**
     * sets the header for curl request.
     * @param type $key
     */
    public function setHeader() {
        $this->headers = array(
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        );
    }

    /**
     * sends the notifications to android devices.
     */
    public function sendMultipleToAndroid() {        
        $idArrays = $this->getRegistrationsIds($this->_androidUsers);
        $splittedReceivers = array_chunk($this->_androidUsers, $this->limit);
        foreach ($idArrays as $key => $ids) {
            $fields = array(
                'registration_ids' => $ids,
                'notification' => array('title' => $this->subject, 'text' => $this->text),
            );

            $data = $this->curl($fields);
            if (isset($data->success) && $data->success >= 1) {
                $this->save($data, $splittedReceivers[$key]);
            }
        }

        return $this->response;
    }

    /**
     * send notification to iPhone devices.
     */
    public function sendMultipleToIphone() {
        $idArray = $this->getRegistrationsIds($this->_iosUsers);
        $splittedReceivers = array_chunk($this->_iosUsers, $this->limit);
        foreach ($idArray as $key => $ids) {
            $fields = array(
                'registration_ids' => $ids,
                'notification' => array(
                    'body' => $this->text,
                    'title' => $this->subject,
                    'sound' => "default",
                )
            );

            $data = $this->curl($fields);
            if (isset($data->success) && $data->success >= 1) {
                $this->response($data, $splittedReceivers[$key]);
            }
        }

        return $this->response;
    }

    /**
     * makes the device token array and splits this to maximum limit depends on google limit.
     * @param type $users
     * @return type
     */
    private function getRegistrationsIds($devices) {
        $registrationIDs = array();
        foreach ($devices as $device) {
            $registrationIDs[] = $device['firebase_token'];
        }

        return array_chunk($registrationIDs, $this->limit);
    }

    /**
     * saves the notification data to database.
     * @param type $response
     * @param type $users
     * @return type
     */
    private function response($response, $receivers) {
        foreach ($response->results as $key => $value) {

            // firebase sends the response of each message as per the sequence
            // of the IDs we sent to the firebase, so we can match the message response
            // with the respective receiver ID.
            if (isset($value->message_id) && isset($receivers[$key]['id'])) {
                if (!in_array($receivers[$key]['id'], $this->flagArray)) {
                    $this->flagArray[] = $receivers[$key]['id'];
                    $this->response[] = $receivers[$key]['id'];
                }
            }
        }
    }

    /**
     * send the curl request to FCM.
     * @param type $fields
     * @return type
     */
    private function curl($fields) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

}
