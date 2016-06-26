<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 26/02/2016
 * Time: 6:32 PM
 */

namespace VoteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * Refers to a region affected by a piece of legislation
 *
 * @ORM\Entity
 * @ORM\Table(name="domain")
 * @ExclusionPolicy("all")
 */
class Domain {

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
     * Can be federal, state or local
     *
     * @ORM\Column(type="string")
     * @Expose
     */
    protected $level;

    /**
     * @ORM\Column(type="string")
     * @Expose
     */
    protected $name;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Expose
     */
    protected $shortName;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\Electorate", mappedBy="domain")
     */
    protected $electorates;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\Document", mappedBy="domain")
     */
    protected $documents;

    /**
     * @ORM\ManyToOne(targetEntity="VoteBundle\Entity\Domain", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", nullable=true, onDelete="set null")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\Domain", mappedBy="parent")
     */
    protected $children;

    /**
     * Domain constructor.
     * @param $level
     * @param $name
     */
    public function __construct($level, $name, $short_name = NULL) {
        $this->level = $level;
        $this->name = $name;
        $this->shortName = $short_name;
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
    public function getLevel() {
        return $this->level;
    }

    /**
     * @param mixed $level
     */
    public function setLevel($level) {
        $this->level = $level;
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
    public function getShortName() {
        return $this->shortName;
    }

    /**
     * @param mixed $shortName
     */
    public function setShortName($shortName) {
        $this->shortName = $shortName;
    }

    /**
     * @return mixed
     */
    public function getElectorates() {
        return $this->electorates;
    }

    /**
     * @return mixed
     */
    public function getDocuments() {
        return $this->documents;
    }

    /**
     * @return mixed
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     */
    public function setParent($parent) {
        $this->parent = $parent;
    }

    /**
     * @return mixed
     */
    public function getChildren() {
        return $this->children;
    }

}