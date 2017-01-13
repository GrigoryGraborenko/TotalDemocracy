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
 * @ORM\Table(name="volunteer")
 * @ExclusionPolicy("all")
 */
class Volunteer {

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
     * @ORM\Column(type="datetime", nullable=true)
     * @Gedmo\Timestampable(on="update")
     */
    protected $date_updated;

    /**
     * @ORM\OneToOne(targetEntity="VoteBundle\Entity\User", mappedBy="volunteer")
     */
    protected $user;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected $homePostcode;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected $homeSuburb;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected $homeStreet;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected $homeStreetNumber;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $willPollBooth;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $willDoorKnock;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $willSignage;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $willCall;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $willHouseParty;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $willEnvelopes;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $willOther;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $whenAvailable;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $whenToCall;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $bestCommunication;

    /**
     * Volunteer constructor.
     * @param $user
     * @param $homePostcode
     * @param $homeSuburb
     * @param $homeStreet
     * @param $homeStreetNumber
     * @param $willPollBooth
     * @param $willDoorKnock
     * @param $willSignage
     * @param $willCall
     * @param $willHouseParty
     * @param $willEnvelopes
     */
    public function __construct($user, $homePostcode, $homeSuburb, $homeStreet, $homeStreetNumber, $willPollBooth = false, $willDoorKnock = false, $willSignage = false, $willCall = false, $willHouseParty = false, $willEnvelopes = false) {
        $this->user = $user;
        $this->homePostcode = $homePostcode;
        $this->homeSuburb = $homeSuburb;
        $this->homeStreet = $homeStreet;
        $this->homeStreetNumber = $homeStreetNumber;
        $this->willPollBooth = $willPollBooth;
        $this->willDoorKnock = $willDoorKnock;
        $this->willSignage = $willSignage;
        $this->willCall = $willCall;
        $this->willHouseParty = $willHouseParty;
        $this->willEnvelopes = $willEnvelopes;
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
    public function getDateUpdated() {
        return $this->date_updated;
    }

    /**
     * @return mixed
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user) {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getHomePostcode() {
        return $this->homePostcode;
    }

    /**
     * @param mixed $homePostcode
     */
    public function setHomePostcode($homePostcode) {
        $this->homePostcode = $homePostcode;
    }

    /**
     * @return mixed
     */
    public function getHomeSuburb() {
        return $this->homeSuburb;
    }

    /**
     * @param mixed $homeSuburb
     */
    public function setHomeSuburb($homeSuburb) {
        $this->homeSuburb = $homeSuburb;
    }

    /**
     * @return mixed
     */
    public function getHomeStreet() {
        return $this->homeStreet;
    }

    /**
     * @param mixed $homeStreet
     */
    public function setHomeStreet($homeStreet) {
        $this->homeStreet = $homeStreet;
    }

    /**
     * @return mixed
     */
    public function getHomeStreetNumber() {
        return $this->homeStreetNumber;
    }

    /**
     * @param mixed $homeStreetNumber
     */
    public function setHomeStreetNumber($homeStreetNumber) {
        $this->homeStreetNumber = $homeStreetNumber;
    }

    /**
     * @return mixed
     */
    public function getWillPollBooth() {
        return $this->willPollBooth;
    }

    /**
     * @param mixed $willPollBooth
     */
    public function setWillPollBooth($willPollBooth) {
        $this->willPollBooth = $willPollBooth;
    }

    /**
     * @return mixed
     */
    public function getWillDoorKnock() {
        return $this->willDoorKnock;
    }

    /**
     * @param mixed $willDoorKnock
     */
    public function setWillDoorKnock($willDoorKnock) {
        $this->willDoorKnock = $willDoorKnock;
    }

    /**
     * @return mixed
     */
    public function getWillSignage() {
        return $this->willSignage;
    }

    /**
     * @param mixed $willSignage
     */
    public function setWillSignage($willSignage) {
        $this->willSignage = $willSignage;
    }

    /**
     * @return mixed
     */
    public function getWillCall() {
        return $this->willCall;
    }

    /**
     * @param mixed $willCall
     */
    public function setWillCall($willCall) {
        $this->willCall = $willCall;
    }

    /**
     * @return mixed
     */
    public function getWillHouseParty() {
        return $this->willHouseParty;
    }

    /**
     * @param mixed $willHouseParty
     */
    public function setWillHouseParty($willHouseParty) {
        $this->willHouseParty = $willHouseParty;
    }

    /**
     * @return mixed
     */
    public function getWillEnvelopes() {
        return $this->willEnvelopes;
    }

    /**
     * @param mixed $willEnvelopes
     */
    public function setWillEnvelopes($willEnvelopes) {
        $this->willEnvelopes = $willEnvelopes;
    }

    /**
     * @return mixed
     */
    public function getWillOther() {
        return $this->willOther;
    }

    /**
     * @param mixed $willOther
     */
    public function setWillOther($willOther) {
        $this->willOther = $willOther;
    }

    /**
     * @return mixed
     */
    public function getWhenAvailable() {
        return $this->whenAvailable;
    }

    /**
     * @param mixed $whenAvailable
     */
    public function setWhenAvailable($whenAvailable) {
        $this->whenAvailable = $whenAvailable;
    }

    /**
     * @return mixed
     */
    public function getWhenToCall() {
        return $this->whenToCall;
    }

    /**
     * @param mixed $whenToCall
     */
    public function setWhenToCall($whenToCall) {
        $this->whenToCall = $whenToCall;
    }

    /**
     * @return mixed
     */
    public function getBestCommunication() {
        return $this->bestCommunication;
    }

    /**
     * @param mixed $bestCommunication
     */
    public function setBestCommunication($bestCommunication) {
        $this->bestCommunication = $bestCommunication;
    }

}
