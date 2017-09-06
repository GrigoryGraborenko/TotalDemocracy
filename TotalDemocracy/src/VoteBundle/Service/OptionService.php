<?php
/**
 * Created by PhpStorm.
 * User: Grisha
 */

namespace VoteBundle\Service;

use Doctrine\ORM\EntityManager;
use JMS\DiExtraBundle\Annotation as DI;
//cannot use DI\Service("vote.option", public=true)

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use VoteBundle\Entity\Option;

/**
 * Class OptionService
 * @package VoteBundle\Service
 *
 * Provides admin-configurable options for the production server
 *
 */
class OptionService
{

    /** @var EntityManager em */
    private $em;

    /** @var Service Container container */
    private $container;

    /** @var Logger logger */
    private $logger;

    // TODO: change this to
    // const defaultParams = array(
    // when php 5.6 becomes the version we all use
    private $defaultParams;

    function initDefaultParams() {
//    const defaultParams = array(
        $this->defaultParams = array(

        "register.enable"                                        => array("default" => "true", "type" => "boolean", "label" => "Allows registration. ")
        ,"verify.enable"                                         => array("default" => "true", "type" => "boolean", "label" => "Allows verification. ")
        ,"password.length.min"                                   => array("default" => "8", "type" => "int", "label" => "Minimum password length. ")
        ,"phone.length.min"                                      => array("default" => "6", "type" => "int", "label" => "Minimum phone number length. ")
        ,"footer.text"                                           => array("default" => "© 2017 PeopleDecide. All rights reserved", "type" => "string", "label" => "The text in the footer ")
        );
    }

    /**
     * OptionService constructor.
     * @param $em
     * @param $container
     * @param $logger
     */
    public function __construct($em, $container, $logger) {
        $this->em = $em;
        $this->container = $container;
        $this->logger = $logger;

        $this->initDefaultParams();
    }

    /**
     * Gets value for a string option
     *
     * @param $name
     * @return mixed
     */
    public function getString($name)
    {
        $value = $this->getValue($name, 'string');
        if($value === NULL) {
            return false;
        }
        return $value;
    }

    /**
     * Changes the value for a string option
     *
     * @param $name
     * @param $value
     * @throws \Exception
     */
    public function setString($name, $value) {

        $option = $this->getOption($name, "string");
        if ($option === NULL) {
            throw new \Exception("Could not find $name option of type boolean");
        }

        $option->setValue($value);
        $this->em->flush();
    }

    /**
     * Gets value for a boolean option
     *
     * @param $name
     * @return mixed
     */
    public function getBoolean($name)
    {
        $value = $this->getValue($name, 'boolean');
        if($value === NULL) {
            return false;
        }
        $val = strtolower($value);
        return ($val === "t") || ($val === "true");
    }

    /**
     * Changes the value for a boolean option
     *
     * @param $name
     * @param $value
     * @throws \Exception
     */
    public function setBoolean($name, $value) {

        $option = $this->getOption($name, "boolean");
        if ($option === NULL) {
            throw new \Exception("Could not find $name option of type boolean");
        }

        $option->setValue($value ? "true" : "false");
        $this->em->flush();
    }

    /**
     * Gets value for an integer option
     *
     * @param $name
     * @return mixed
     */
    public function getInteger($name)
    {
        $value = $this->getValue($name, 'int');
        if($value === NULL) {
            return 0;
        }
        $val = intval($value);
        return $val;
    }

    /**
     * Changes the value for an integer option
     *
     * @param $name
     * @param $value
     * @throws \Exception
     */
    public function setInteger($name, $value) {

        $option = $this->getOption($name, "int");
        if ($option === NULL) {
            throw new \Exception("Could not find $name option of type int");
        }

        $option->setValue(intval($value));
        $this->em->flush();
    }

    /**
     * Gets value for a float option
     *
     * @param $name
     * @return mixed
     */
    public function getFloat($name)
    {
        $value = $this->getValue($name, 'float');
        if($value === NULL) {
            return false;
        }
        $val = floatval($value);
        return $val;
    }

    /**
     * Changes the value for a float option
     *
     * @param $name
     * @param $value
     * @throws \Exception
     */
    public function setFloat($name, $value) {

        $option = $this->getOption($name, "float");
        if ($option === NULL) {
            throw new \Exception("Could not find $name option of type float");
        }

        $option->setValue(floatval($value));
        $this->em->flush();
    }

    /**
     * Gets value for an array option
     *
     * @param $name
     * @return mixed
     */
    public function getArray($name)
    {
        $value = $this->getValue($name, 'array');
        if($value === NULL) {
            return false;
        }

        $val = explode(',', $value);
        return $val;
    }

    /**
     * Changes the value for an array option
     *
     * @param $name
     * @param $value
     * @throws \Exception
     */
    public function setArray($name, $value) {

        $option = $this->getOption($name, "array");
        if ($option === NULL) {
            throw new \Exception("Could not find $name option of type array");
        }

        $option->setValue(implode(',', $value));
        $this->em->flush();
    }

    /**
     * @param $name
     * @param $expected
     * @return Option
     * @throws \Exception
     */
    private function getOption($name, $expected) {

        $option = $this->em->getRepository('VoteBundle:Option')->findOneBy(array('name' => $name));
        if ($option === NULL) {

            $this->logger->debug("$name parameter not in database");

            if(!array_key_exists($name, $this->defaultParams)) {
                return NULL;
            }
            $values = $this->defaultParams[$name];

            $option = new Option();
            $option->setName($name);
            $option->setValue($values['default']);
            $option->setType($values['type']);
            $option->setDescription($values['label']);

            $this->em->persist($option);
            $this->em->flush();

        }
        if ($option->getType() !== $expected) {
            return NULL;
        }

        return $option;
    }

    /**
     * @param $name
     * @param $expected
     * @return mixed
     * @throws \Exception
     */
    private function getValue($name, $expected) {

        $option = $this->getOption($name, $expected);
        if($option === NULL) {
            return NULL;
        }

        return $option->getValue();
    }

}