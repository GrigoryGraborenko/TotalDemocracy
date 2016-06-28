<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 27/06/2016
 * Time: 6:49 AM
 */

namespace VoteBundle\Service;

use OAuth2\Client;

/**
 * Class NationBuilderService
 * @package VoteBundle\Service
 */
class NationBuilderService {

    private $session;
    private $logger;

    private $base_url;
    private $client_id;
    private $secret;

    private $api_token;

//    public function

    // sync function that
    // 1. uses person/match with email
    // 2. if no match, create
    // 3. checks fields
    // 4. sends an update if any mismatch occurred


    /**
     * Checks to see if a person exists. If they do, returns their details
     *
     * @param $email
     * @return null
     */
    public function getPerson($email) {

        list($success, $result) = $this->communicate("people/match", array("email" => $email));
        if(!$success) {
            return NULL;
        }
        return $result['person'];
    }

    /**
     * @param $new
     * @param $old
     * @return null
     */
    private function recursiveDiff($new, $old) {

        $diff = array();
        foreach($new as $key => $value) {
            $old_val = $old[$key];
            if(is_array($value)) {
                if(is_array($old_val)) {
                    $recurse_diff = $this->recursiveDiff($value, $old_val);
                    if($recurse_diff !== NULL) {
                        $diff[$key] = $recurse_diff;
                    }
                } else {
                    $diff[$key] = $value;
                }
                continue;
            }

            if(array_key_exists($key, $old) && ($old_val === $value)) {
                continue;
            }
            $diff[$key] = $value;
        }
        if(count($diff) <= 0) {
            return NULL;
        }
        return $diff;
    }

    /**
     * Syncs this user into the nationbuilder database
     *
     * @param $user
     */
    public function syncPerson($user) {

        $person = $this->getPerson($user->getEmail());

        $sync_user = array(
            "email" => $user->getEmail()
            ,"first_name" => $user->getGivenNames()
            ,"last_name" => $user->getSurname()
            ,"phone" => $user->getPhone()
            ,"primary_address" => array(
                "zip" => $user->getPostcode()
                ,"country_code" => "AU"
            )
        );
        if($user->getDOB() !== NULL) {
            $sync_user['birthdate'] = $user->getDOB()->format("Y-m-d");
        }

        if($person === NULL) {
            $person_data = $sync_user;
        } else {
            $person_data = $this->recursiveDiff($sync_user, $person);
//            $person_data = array();
//            foreach($sync_user as $nb_key => $value) {
//                if($value != $person[$nb_key]) {
//                    $person_data[$nb_key] = $value;
//                }
//            }
        }
        $this->logger->info("REFRESH: " . json_encode($person_data));

        if($person_data === NULL) {
            return;
        }

        if($person === NULL) {
            $result = $this->sendData("people", array('person' => $person_data), "POST");
        } else {
            $result = $this->sendData("people/" . $person['id'], array('person' => $person_data), "PUT");
        }
        $this->logger->info("RESULT: " . json_encode($result));

    }


    /**
     * NationBuilderService constructor.
     * @param $session
     * @param $logger
     * @param $baseURL
     * @param $clientID
     * @param $secret
     * @param $testToken
     */
    public function __construct($session, $logger, $baseURL, $clientID, $secret, $testToken) {
        $this->session = $session;
        $this->logger = $logger;
        $this->base_url = $baseURL;
        $this->client_id = $clientID;
        $this->secret = $secret;

        if($testToken !== NULL) {
            $this->api_token = $testToken;
        } else if($session->has("nationbuilder.api_token")) {
            $this->api_token = $session->get("nationbuilder.api_token");
        } else {
            $this->api_token = NULL;
        }
    }

    /**
     * Gets/sends data from the nation builder site - use for GET and DELETE
     *
     * @param $api_call
     * @param $params
     * @param $method
     * @return null
     */
    private function communicate($api_call, $params = array(), $method = "GET") {

        if($this->api_token === NULL) {
            return array(false, "No API token");
        }

        $send_url = $this->base_url . "api/v1/" . $api_call;

        $client = new Client($this->client_id, $this->secret);
        $client->setAccessToken($this->api_token);

        $response = $client->fetch($send_url, $params, $method);

        if($response['code'] !== 200) {
//            $this->logger->error("Could not retrieve data. Response: " . json_encode($response));
            return array(false, $response['result']);
        }
        return array(true, $response['result']);
    }

    /**
     * Gets/sends data from the nation builder site - use for POST and PUT
     *
     * @param $api_call
     * @param $method
     * @param $params
     * @return null
     */
    private function sendData($api_call, $params, $method = "POST") {

        if($this->api_token === NULL) {
            return NULL;
        }

        $token = $this->api_token;
        $send_url = $this->base_url . "api/v1/" . $api_call;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch,CURLOPT_URL, $send_url . "?access_token=$token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($params) );
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json'));

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $expected_code = $method === "POST" ? 201 : 200;

        if($http_code !== $expected_code) {
            $this->logger->info("Error code $http_code: $result");
            return NULL;
        }

        $json_decode = json_decode($result, true);

        return array(
            'result' => (null === $json_decode) ? $result : $json_decode,
            'code' => $http_code
        );
    }

}