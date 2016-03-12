<?php
/**
 * Created by PhpStorm.
 * User: Grisha
 * Date: 3/07/2015
 * Time: 2:54 PM
 */

namespace VoteBundle\Exception;

/**
 * Class ErrorRedirectException
 *
 * Should be thrown when a an error is thrown that requires redirection
 *
 * @package VoteBundle\Exception
 */
class ErrorRedirectException extends \Exception {

    protected $named_route;
    protected $custom_variable;
    protected $url_params;

    /**
     * @param string $message
     */
    public function __construct($named_route, $message, $custom_variable = NULL, $url_params = array()) {

        $this->named_route = $named_route;
        $this->custom_variable = $custom_variable;
        $this->url_params = $url_params;

        parent::__construct($message);
    }

    public function getNamedRoute() {
        return $this->named_route;
    }

    public function getVariableName() {
        if($this->custom_variable === NULL) {
            return "error-redirect";
        }
        return $this->custom_variable;
    }

    public function getURLParams() {
        return $this->url_params;
    }

}