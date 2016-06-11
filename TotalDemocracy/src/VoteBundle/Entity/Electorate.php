<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 26/02/2016
 * Time: 8:21 PM
 */

namespace VoteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * Refers to a region which forms an electoral boundary
 *
 * @ORM\Entity
 * @ORM\Table(name="electorate")
 * @ExclusionPolicy("all")
 */
class Electorate {

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
    protected $date_created;

    /**
     * The domain the electorate elects representatives for
     *
     * @ORM\ManyToOne(targetEntity="\VoteBundle\Entity\Domain", inversedBy="electorates")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $domain;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @Expose
     */
    protected $name;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @Expose
     */
    protected $description;

    /**
     * @ORM\ManyToMany(targetEntity="VoteBundle\Entity\User", mappedBy="electorates")
     */
    private $users;

    /**
     * Electorate constructor.
     * @param $domain
     * @param $name
     * @param null $description
     */
    public function __construct($domain, $name, $description = NULL) {
        $this->domain = $domain;
        $this->name = $name;
        if($description !== NULL) {
            $this->description = $description;
        } else if($domain->getLevel() === "federal") {
            $this->description = "Federal Division";
        } else if($domain->getLevel() === "state") {
            $this->description = "State District";
        } else {
            $this->description = "Local Ward/Division";
        }
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
    public function getDateCreated() {
        return $this->date_created;
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
    public function getDescription() {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description) {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getUsers() {
        return $this->users;
    }

    /**
     * @param mixed $users
     */
    public function setUsers($users) {
        $this->users = $users;
    }

}