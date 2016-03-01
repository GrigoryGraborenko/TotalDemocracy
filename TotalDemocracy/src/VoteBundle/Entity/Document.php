<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 13/08/2015
 * Time: 3:53 PM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * @ORM\Entity
 * @ORM\Table(name="document")
 * @ExclusionPolicy("all")
 */
class Document {

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="UUID")
     * @Expose
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     * @Expose
     */
    protected $whenCreated;

    /**
     * @ORM\Column(type="string")
     * @Expose
     */
    protected $name;

    /**
     * @ORM\Column(type="string")
     * @Expose
     */
    protected $text;

    /**
     * The domain the document applies in
     *
     * @ORM\ManyToOne(targetEntity="\VoteBundle\Entity\Domain")
     */
    protected $domain;

    /**
     * Document constructor.
     * @param $name
     * @param $text
     * @param $domain
     */
    public function __construct($domain, $name, $text) {
        $this->domain = $domain;
        $this->name = $name;
        $this->text = $text;
    }

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getWhenCreated() {
        return $this->whenCreated;
    }

    /**
     * @return mixed
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain) {
        $this->domain = $domain;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getText() {
        return $this->text;
    }

    /**
     * @param mixed $text
     */
    public function setText($text) {
        $this->text = $text;
    }


}
