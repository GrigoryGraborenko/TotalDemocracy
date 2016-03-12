<?php
/**
 * Created by PhpStorm.
 * User: Grisha
 * Date: 3/07/2015
 * Time: 2:54 PM
 */

namespace VoteBundle\Exception;

/**
 * Class BadRequestException
 *
 * Should be thrown when a REST request is called with insufficient or malformed parameters
 *
 * @package VoteBundle\Exception
 */
class BadRequestException extends \Exception {

    private $statusCode;
    private $extraParams;

    /**
     * @param string $message
     */
    public function __construct($message, $statusCode = 400, $extra_params = NULL) {  // HTTP 400 is 'Bad Request'

        $this->statusCode = $statusCode;
        $this->extraParams = $extra_params;

        parent::__construct($message);
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function getExtraParams() {
        return $this->extraParams;
    }

}