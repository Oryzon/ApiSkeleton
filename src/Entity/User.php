<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use Exception;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @UniqueEntity(fields={"email"}, message="An user with this email already exist.")
 * @UniqueEntity(fields={"username"}, message="An user with this username already exist.")
 * @ORM\HasLifecycleCallbacks()
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     *
     * @Serializer\Groups({
     *     "user_personal"
     * })
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     *
     * @Assert\Email(message="The email is not a valid email.")
     * @Assert\NotBlank(message="The email can't be empty")
     *
     * @Serializer\Groups({
     *     "doc",
     *     "user_info"
     * })
     */
    private $email;

    /**
     * @ORM\Column(type="string")
     *
     * @Serializer\Groups({
     *     "doc",
     *     "user_info",
     *     "user_activate"
     * })
     */
    private $username;

    /**
     * @ORM\Column(type="json")
     *
     * @Assert\NotBlank(message="The username can't be empty")
     *
     * @Serializer\Groups({
     *     "user_personal"
     * })
     */
    private $roles = [];

    /**
     * @ORM\Column(type="string")
     *
     * @Serializer\Groups({
     *     "doc"
     * })
     */
    private $password;

    /**
     * @ORM\Column(type="boolean")
     *
     * @Serializer\Groups({
     *     "user_personal"
     * })
     */
    private $isActive;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $token;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $tokenCreatedAt;

    /**
     * @ORM\Column(type="datetime")
     *
     * @Serializer\Groups({"user_info"})
     */
    private $created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     *
     * @Serializer\Groups({
     *     "user_personal"
     * })
     */
    private $updated;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     *
     * @Serializer\Groups({
     *     "user_personal"
     * })
     */
    private $deleted;

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("is_deleted")
     *
     * @Serializer\Groups({
     *     "user_personal"
     * })
     */
    public function isDeleted() {
        return !$this->getIsActive() && $this->getDeleted() !== null;
    }

    /*
     * Getter for Id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /*
     * Getter and setter for Email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /*
     * Getter and setter for Username
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self {
        $this->username = $username;

        return $this;
    }

    /*
     * Getter and setter for Roles
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /*
     * Getter and setter for Password
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /*
     * Getter and setter for isActivate
     */
    public function getIsActive() {
        return $this->isActive;
    }

    public function setIsActive($isActive): self {
        $this->isActive = $isActive;

        return $this;
    }

    /*
     * Getter and setter for Token
     */
    public function getToken() {
        return $this->token;
    }

    public function setToken($token): self {
        $this->token = $token;

        return $this;
    }

    /*
     * Getter and setter for TokenCreatedAt
     */
    public function getTokenCreatedAt() {
        return $this->tokenCreatedAt;
    }

    public function setTokenCreatedAt($tokenCreatedAt): self {
        $this->tokenCreatedAt = $tokenCreatedAt;

        return $this;
    }

    /*
     * Getter and setter for Created
     */
    public function getCreated() {
        return $this->created;
    }

    public function setCreated(DateTime $created): self {
        $this->created = $created;

        return $this;
    }

    /*
     * Getter and setter for Updated
     */
    public function getUpdated() {
        return $this->updated;
    }

    public function setUpdated(DateTime $updated): self {
        $this->updated = $updated;

        return $this;
    }

    /*
     * Getter and setter for Deleted
     */
    public function getDeleted() {
        return $this->deleted;
    }

    public function setDeleted(DateTime $deleted): self {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * @ORM\PrePersist()
     * @throws Exception
     */
    public function onCreated() {
        $this->setCreated(new DateTime('now'));

        if (GeneralSettings::ACCOUNT_ACTIVATION_NEEDED) {
            $this->setIsActive(false);
            $this->createToken();
        }
    }

    /**
     * @ORM\PreUpdate()
     */
    public function onUpdated() {
        $this->setUpdated(new DateTime('now'));
    }

    /**
     * @ORM\PreRemove()
     */
    public function onDeleted() {
        $this->setDeleted(new DateTime('now'));
    }

    /**
     * Generate a random token
     *
     * @param int $length
     *
     * @return string
     *
     * @throws Exception
     */
    public function createToken($length = 16) {
        $this->setToken(bin2hex(random_bytes($length)));
        $this->setTokenCreatedAt(new DateTime('now'));

        return true;
    }

    /**
     * Necessary function, but not used
     */
    public function eraseCredentials() {
        // Not neccessary, we don't store plain password
    }

    public function getSalt() {
        // Not neccessary, we used global salt
    }
}
