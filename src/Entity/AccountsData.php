<?php

namespace App\Entity;

use Casemgr\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AccountsData
 * @package App\Entity
 *
 * @ORM\Table(name="accounts_data")
 * @ORM\Entity(repositoryClass="App\Repository\AccountsDataRepository")
 */
class AccountsData extends Entity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 50,
     *
     * )
     */
    protected $address1;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 50,
     *
     * )
     */
    protected $address2;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     *
     * )
     */
    protected $city;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     *
     * )
     */
    protected $state;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     * )
     */
    protected $country;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 5,
     * )
     */
    protected $zipCode;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\NotBlank()
     * @Assert\Length(
     *     max = 100,
     * )
     */
    protected $contactName;     // referral primaryContactName | caseMgr programContactName

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Length(
     *     max = 255,
     * )
     * @Assert\Email()
     */
    protected $emailAddress;    // referral accountEmailAddress | caseMgr programContactEmailAddress

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     * )
     */
    protected $phoneNumber;     // referral primaryPhoneNumber | caseMgr programPrimaryPhone

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false, unique=true)
     *
     * @Assert\Length(
     *     max = 255
     * )
     */
    protected $accountUrl;      // referral referralAccountUrl | caseMgr caseMgrAccountUrl

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\NotBlank()
     * @Assert\Length(
     *     max = 100,
     * )
     */
    protected $billingContactName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\NotBlank()
     * @Assert\Length(
     *     max = 255,
     * )
     * @Assert\Email()
     */
    protected $billingEmailAddress;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     * )
     */
    protected $billingPrimaryPhone;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     * )
     */
    protected $serviceCategory;


    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     * )
     */
    protected $accountOwner;


    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     *
     * @Assert\Length(
     *     max = 255,
     * )
     */
    protected $projectContact;

    /**
     * AccountsData constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    /**
     * @param string $address1
     */
    public function setAddress1(string $address1 = null)
    {
        $this->address1 = $address1;
    }

    /**
     * @return string
     */
    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    /**
     * @param string $address2
     */
    public function setAddress2(string $address2 = null)
    {
        $this->address2 = $address2;
    }

    /**
     * @return string
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity(string $city = null)
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * @param string $state
     */
    public function setState(string $state = null)
    {
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry(string $country = null)
    {
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    /**
     * @param string $zipCode
     */
    public function setZipCode(string $zipCode = null)
    {
        $this->zipCode = $zipCode;
    }

    /**
     * @return string
     */
    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    /**
     * @param string $contactName
     */
    public function setContactName(string $contactName = null)
    {
        $this->contactName = $contactName;
    }

    /**
     * @return string
     */
    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    /**
     * @param string $emailAddress
     */
    public function setEmailAddress(string $emailAddress = null)
    {
        $this->emailAddress = $emailAddress;
    }

    /**
     * @return string
     */
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /**
     * @param string $phoneNumber
     */
    public function setPhoneNumber(string $phoneNumber = null)
    {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * @return string
     */
    public function getAccountUrl(): ?string
    {
        return $this->accountUrl;
    }

    /**
     * @param string $accountUrl
     */
    public function setAccountUrl(string $accountUrl = null)
    {
        $this->accountUrl = $accountUrl;
    }

    /**
     * @return string
     */
    public function getBillingContactName(): ?string
    {
        return $this->billingContactName;
    }

    /**
     * @param string $billingContactName
     */
    public function setBillingContactName(string $billingContactName = null)
    {
        $this->billingContactName = $billingContactName;
    }

    /**
     * @return string
     */
    public function getBillingEmailAddress(): ?string
    {
        return $this->billingEmailAddress;
    }

    /**
     * @param string $billingEmailAddress
     */
    public function setBillingEmailAddress(string $billingEmailAddress = null)
    {
        $this->billingEmailAddress = $billingEmailAddress;
    }

    /**
     * @return string
     */
    public function getBillingPrimaryPhone(): ?string
    {
        return $this->billingPrimaryPhone;
    }

    /**
     * @param string $billingPrimaryPhone
     */
    public function setBillingPrimaryPhone(string $billingPrimaryPhone = null)
    {
        $this->billingPrimaryPhone = $billingPrimaryPhone;
    }

    /**
     * @return string
     */
    public function getServiceCategory(): ?string
    {
        return $this->serviceCategory;
    }

    /**
     * @param string $serviceCategory
     */
    public function setServiceCategory(string $serviceCategory = null)
    {
        $this->serviceCategory = $serviceCategory;
    }

    /**
     * @return string
     */
    public function getAccountOwner(): ?string
    {
        return $this->accountOwner;
    }

    /**
     * @param string $accountOwner
     */
    public function setAccountOwner(string $accountOwner = null)
    {
        $this->accountOwner = $accountOwner;
    }

    /**
     * @return string
     */
    public function getProjectContact(): ?string
    {
        return $this->projectContact;
    }

    /**
     * @param string $projectContact
     */
    public function setProjectContact(string $projectContact = null)
    {
        $this->projectContact = $projectContact;
    }
}
