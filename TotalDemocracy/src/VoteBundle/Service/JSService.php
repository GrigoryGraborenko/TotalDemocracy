<?php
/**
 * Created by PhpStorm.
 * User: Grisha
 * Date: 6/08/2015
 * Time: 12:44 PM
 */

namespace VoteBundle\Service;

use JMS\DiExtraBundle\Annotation as DI;

/**
 * Class JSService
 * @package VoteBundle\Service
 */
class JSService {

    private $parameters = array();

    /**
     * Attaches this key value pair to g_InitialData javascript variable
     *
     * @param $key
     * @param $value
     * @throws \Exception
     */
    public function output($key, $value) {

        if(array_key_exists($key, $this->parameters)) {
            throw new \Exception('Added JS parameter ' . $key . ' twice');
        }

        $this->parameters[$key] = $value;
    }

    /**
     * Attaches all key-value pairs in the given array to the g_InitialData javascript variable
     *
     * @param $params
     * @throws \Exception
     */
    public function addParameters($params) {

        foreach($params as $key => $value) {
            $this->output($key, $value);
        }
    }

    /**
     * Returns the associative array with the parameters
     *
     * @return array
     */
    public function getParameters() {
        return $this->parameters;
    }
}
