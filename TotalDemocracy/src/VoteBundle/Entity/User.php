<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 20/02/2016
 * Time: 6:28 PM
 */

namespace VoteBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * @ORM\Entity
 * @ORM\Table(name="user_record")
 * @ExclusionPolicy("all")
 */
class User extends BaseUser {

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
     * @ORM\Column(type="string", nullable=true)
     */
    protected $givenNames;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $surname;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $postcode;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $suburb;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $street;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $streetNumber;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $DOB;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $whenVerified;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $phone;

    /**
     * @ORM\ManyToMany(targetEntity="VoteBundle\Entity\Electorate", inversedBy="users")
     * @ORM\JoinTable(name="user_electorate")
     */
    protected $electorates;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\UserDocumentVote", mappedBy="user")
     */
    protected $votes;

    /**
     * @ORM\OneToOne(targetEntity="VoteBundle\Entity\Volunteer", inversedBy="user")
     */
    protected $volunteer;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $isVolunteer;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $isMember;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" = false})
     */
    protected $emailOptOut;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $permanentLoginToken;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $whenFromNationBuilder;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $whenSentToNationBuilder;

    /**
     * User constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->isVolunteer = false;
        $this->isMember = false;
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
    public function getGivenNames() {
        return $this->givenNames;
    }

    /**
     * @param mixed $givenNames
     */
    public function setGivenNames($givenNames) {
        $this->givenNames = $givenNames;
    }

    /**
     * @return mixed
     */
    public function getSurname() {
        return $this->surname;
    }

    /**
     * @param mixed $surname
     */
    public function setSurname($surname) {
        $this->surname = $surname;
    }

    /**
     * @return mixed
     */
    public function getPostcode() {
        return $this->postcode;
    }

    /**
     * @param mixed $postcode
     */
    public function setPostcode($postcode) {
        $this->postcode = $postcode;
    }

    /**
     * @return mixed
     */
    public function getSuburb() {
        return $this->suburb;
    }

    /**
     * @param mixed $suburb
     */
    public function setSuburb($suburb) {
        $this->suburb = $suburb;
    }

    /**
     * @return mixed
     */
    public function getStreet() {
        return $this->street;
    }

    /**
     * @param mixed $street
     */
    public function setStreet($street) {
        $this->street = $street;
    }

    /**
     * @return mixed
     */
    public function getStreetNumber() {
        return $this->streetNumber;
    }

    /**
     * @param mixed $streetNumber
     */
    public function setStreetNumber($streetNumber) {
        $this->streetNumber = $streetNumber;
    }

    /**
     * @return mixed
     */
    public function getDOB() {
        return $this->DOB;
    }

    /**
     * @param mixed $DOB
     */
    public function setDOB($DOB) {
        $this->DOB = $DOB;
    }

    /**
     * @return mixed
     */
    public function getWhenVerified() {
        return $this->whenVerified;
    }

    /**
     * @param mixed $whenVerified
     */
    public function setWhenVerified($whenVerified) {
        $this->whenVerified = $whenVerified;
    }

    /**
     * @return mixed
     */
    public function getPhone() {
        return $this->phone;
    }

    /**
     * @param mixed $phone
     */
    public function setPhone($phone) {
        $this->phone = $phone;
    }

    /**
     * @return mixed
     */
    public function getElectorates() {
        return $this->electorates;
    }

    /**
     * @param mixed $electorates
     */
    public function setElectorates($electorates) {
        $this->electorates = $electorates;
    }

    /**
     * @param $electorate
     */
    public function addElectorate($electorate) {
        $this->electorates[] = $electorate;
    }

    /**
     *
     */
    public function clearAllElectorates() {
        $this->electorates->clear();
    }

    /**
     * @return mixed
     */
    public function getVotes() {
        return $this->votes;
    }

    /**
     * @param mixed $votes
     */
    public function setVotes($votes) {
        $this->votes = $votes;
    }

    /**
     * @return mixed
     */
    public function getVolunteer() {
        return $this->volunteer;
    }

    /**
     * @param mixed $volunteer
     */
    public function setVolunteer($volunteer) {
        $this->volunteer = $volunteer;
    }

    /**
     * @return mixed
     */
    public function getIsVolunteer() {
        return $this->isVolunteer;
    }

    /**
     * @param mixed $isVolunteer
     */
    public function setIsVolunteer($isVolunteer) {
        $this->isVolunteer = $isVolunteer;
    }

    /**
     * @return mixed
     */
    public function getIsMember() {
        return $this->isMember;
    }

    /**
     * @param mixed $isMember
     */
    public function setIsMember($isMember) {
        $this->isMember = $isMember;
    }

    /**
     * @return mixed
     */
    public function getPermanentLoginToken() {
        return $this->permanentLoginToken;
    }

    /**
     * @param mixed $permanentLoginToken
     */
    public function setPermanentLoginToken($permanentLoginToken) {
        $this->permanentLoginToken = $permanentLoginToken;
    }

    /**
     * @return mixed
     */
    public function getWhenFromNationBuilder() {
        return $this->whenFromNationBuilder;
    }

    /**
     * @param mixed $whenFromNationBuilder
     */
    public function setWhenFromNationBuilder($whenFromNationBuilder) {
        $this->whenFromNationBuilder = $whenFromNationBuilder;
    }

    /**
     * @return mixed
     */
    public function getWhenSentToNationBuilder() {
        return $this->whenSentToNationBuilder;
    }

    /**
     * @param mixed $whenSentToNationBuilder
     */
    public function setWhenSentToNationBuilder($whenSentToNationBuilder) {
        $this->whenSentToNationBuilder = $whenSentToNationBuilder;
    }

}
